<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Models\User;
use App\Modules\Api\Auth\ApiTokens;
use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Audit\ActivityOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The numbers behind the API dashboard.
 *
 * Every method takes a `$scope`: either the viewer's own tokens, or the
 * whole installation for a viewer permitted to see it. The scoping lives
 * here rather than in the controller so no query can accidentally skip it —
 * showing every staff member's integrations to every other staff member
 * would be a new leak, and it would be one query's oversight away.
 */
class ApiUsage
{
    public function __construct(
        private readonly ApiUsageScope $scope,
        private readonly ActivityLogScope $activityLog,
    ) {}

    /**
     * @return array<string, int|float|null>
     */
    public function summary(User $viewer, bool $installWide): array
    {
        $requests = $this->requests($viewer, $installWide);
        $since = now()->subDays(7);

        $recent = (clone $requests)->where('created_at', '>=', $since);

        return [
            'tokens' => $this->tokens($viewer, $installWide)->count(),
            'tokens_expired' => $this->tokens($viewer, $installWide)
                ->whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
            'requests_7d' => (clone $recent)->count(),
            'failed_7d' => (clone $recent)->failed()->count(),
            // Null rather than 0 when nothing has been called: "no requests"
            // and "no failures out of many" are different states, and a
            // 0% badge on an unused API reads as a health claim it cannot make.
            'median_ms' => $this->medianDuration((clone $recent)),
        ];
    }

    /**
     * Daily request counts for the chart, zero-filled so gaps read as
     * quiet days rather than as missing data.
     *
     * @return list<array{date: string, requests: int, failed: int}>
     */
    public function daily(User $viewer, bool $installWide, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = $this->requests($viewer, $installWide)
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day')
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(case when status >= 400 then 1 else 0 end) as failed')
            ->groupBy('day')
            ->pluck('requests', 'day');

        $failures = $this->requests($viewer, $installWide)
            ->where('created_at', '>=', $from)
            ->failed()
            ->selectRaw('date(created_at) as day')
            ->selectRaw('count(*) as failed')
            ->groupBy('day')
            ->pluck('failed', 'day');

        $series = [];

        for ($day = $from->copy(); $day <= now(); $day->addDay()) {
            $key = $day->toDateString();

            $series[] = [
                'date' => $key,
                'requests' => (int) ($rows[$key] ?? 0),
                'failed' => (int) ($failures[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Per-token usage: what each token is, and what it has been doing.
     *
     * @return list<array<string, mixed>>
     */
    public function tokenUsage(User $viewer, bool $installWide): array
    {
        $tokens = $this->tokens($viewer, $installWide)
            ->with('tokenable')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $counts = $this->requests($viewer, $installWide)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('api_token_id, count(*) as total')
            ->selectRaw('sum(case when status >= 400 then 1 else 0 end) as failed')
            ->groupBy('api_token_id')
            ->get()
            ->keyBy('api_token_id');

        return array_values($tokens->map(function (PersonalAccessToken $token) use ($counts): array {
            $row = $counts->get($token->getKey());
            $owner = $token->tokenable;

            return [
                'id' => (string) $token->getKey(),
                'name' => $token->name,
                'owner' => $owner instanceof User ? $owner->name : null,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'expired' => ! ApiTokens::isActive($token),
                'requests_7d' => (int) ($row->total ?? 0),
                'failed_7d' => (int) ($row->failed ?? 0),
            ];
        })->all());
    }

    /**
     * The most recent *domain actions* taken through the API — what a token
     * actually changed, as opposed to how many requests it made.
     *
     * Read from the activity log rather than the request log on purpose:
     * this answers "what did it do", which is the audit trail's question,
     * and each row links back into the full log.
     *
     * @return list<array<string, mixed>>
     */
    public function recentActions(User $viewer, bool $installWide, int $limit = 15): array
    {
        // Narrowed through ActivityLogScope, exactly as the activity page,
        // the download history and the dashboard widget are.
        // `view_actions_log` decides whether the install-wide view opens at
        // all, but it is not the whole answer for a client-scoped viewer: a
        // row carries the subject's name, so an unscoped feed reads out file
        // and client names to somebody who gets a 403 on the files
        // themselves. The Client Manager role ships with the permission, so
        // this is the default configuration, not an exotic one.
        //
        // Applied on both sides of the branch rather than only in the
        // install-wide one: the own-actor filter below already stays inside
        // what the scope allows, and a boundary that only exists in one arm
        // of an `if` is one refactor away from not existing.
        $query = $this->activityLog->apply(
            ActivityLog::query()->where('origin', ActivityOrigin::Api),
            $viewer,
        );

        if (! $installWide) {
            $query->where('actor_id', $viewer->id);
        }

        return array_values($query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $entry): array => [
                'id' => $entry->id,
                'created_at' => $entry->created_at->toIso8601String(),
                'actor_name' => $entry->actor_name,
                'token_name' => $entry->api_token_name,
                'template' => $entry->action->template(),
                'replacements' => $this->replacements($entry),
            ])
            ->all());
    }

    /**
     * @return array<string, string>
     */
    private function replacements(ActivityLog $entry): array
    {
        $replacements = ['subject' => $entry->subject_name ?? ''];

        foreach ($entry->context ?? [] as $key => $value) {
            if (is_scalar($value)) {
                $replacements[$key] = (string) $value;
            }
        }

        return $replacements;
    }

    /**
     * @return Builder<ApiRequestLog>
     */
    private function requests(User $viewer, bool $installWide): Builder
    {
        return $this->scope->requests($viewer, $installWide);
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    private function tokens(User $viewer, bool $installWide): Builder
    {
        return $this->scope->tokens($viewer, $installWide);
    }

    /**
     * @param  Builder<ApiRequestLog>  $requests
     */
    private function medianDuration(Builder $requests): ?int
    {
        $count = (clone $requests)->count();

        if ($count === 0) {
            return null;
        }

        // Median rather than mean: one slow upload should not make every
        // other call look slow, which is exactly what an average does on a
        // long-tailed distribution like request duration.
        $offset = (int) floor(($count - 1) / 2);

        return (int) $requests->orderBy('duration_ms')->offset($offset)->limit(1)->value('duration_ms');
    }

    /**
     * Convenience for the dashboard widget, which shows one number.
     */
    public function requestsSince(User $viewer, bool $installWide, Carbon $since): int
    {
        return $this->requests($viewer, $installWide)->where('created_at', '>=', $since)->count();
    }

    /**
     * The busiest endpoints, so an operator can see what an integration
     * actually leans on.
     *
     * @return list<array{route: string, method: string, requests: int}>
     */
    public function topEndpoints(User $viewer, bool $installWide, int $limit = 8): array
    {
        return array_values($this->requests($viewer, $installWide)
            ->where('created_at', '>=', now()->subDays(7))
            ->select('route', 'method', DB::raw('count(*) as requests'))
            ->groupBy('route', 'method')
            ->orderByDesc('requests')
            ->limit($limit)
            ->get()
            ->map(fn (ApiRequestLog $row): array => [
                'route' => $row->route,
                'method' => $row->method,
                'requests' => (int) $row->getAttribute('requests'),
            ])
            ->all());
    }
}
