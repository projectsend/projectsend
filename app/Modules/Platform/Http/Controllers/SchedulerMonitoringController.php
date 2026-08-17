<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Scheduling\ScheduledTaskRun;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Updates\LatestReleaseInfo;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Community-only (Capability::SchedulerMonitoring, enforced entirely by
 * route middleware — see routes/settings.php, same all-or-nothing shape as
 * ExternalStorageSettingsController): visibility into scheduled-command run
 * history and failed queue jobs, since neither has any in-app surface
 * otherwise — a failure only ever lands in the container's own logs.
 */
class SchedulerMonitoringController extends Controller
{
    /**
     * Every command this app schedules (routes/console.php) — a plain
     * label map rather than a backed enum, since it'd just have to be kept
     * in sync with 7 already-scattered `protected $signature` values
     * either way.
     *
     * @var array<string, string>
     */
    private const KNOWN_COMMANDS = [
        'projectsend:purge-erasures' => 'Purge erased accounts',
        'projectsend:purge-stale-uploads' => 'Purge stale chunked uploads',
        'projectsend:purge-zip-downloads' => 'Purge zip downloads',
        'projectsend:check-for-updates' => 'Check for updates',
        'projectsend:fetch-news' => 'Fetch dashboard news',
        'projectsend:purge-expired-files' => 'Purge expired files',
        'projectsend:purge-orphan-files' => 'Purge orphan files',
    ];

    private const FAILED_PER_PAGE = 20;

    public function __construct(
        private readonly FailedJobProviderInterface $failer,
        private readonly LatestReleaseInfo $latestRelease,
        private readonly Settings $settings,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $runs = ScheduledTaskRun::query()->get()->keyBy('command');
        $details = $this->details();

        $tasks = collect(self::KNOWN_COMMANDS)->map(function (string $label, string $command) use ($runs, $details): array {
            $run = $runs->get($command);

            return [
                'command' => $command,
                'label' => $label,
                'status' => $run?->status->value,
                'message' => $run?->message,
                'detail' => $details[$command] ?? null,
                'duration_ms' => $run?->duration_ms,
                'ran_at' => $run?->ran_at?->toIso8601String(),
            ];
        })->values()->all();

        /** @var list<object{id: string, connection: string, queue: string, exception: string, failed_at: string}> $failedJobRows */
        $failedJobRows = $this->failer->all();

        // The provider hands back the whole list as an array (no LIMIT of its
        // own), so page it in memory the same way the orphan-files repair
        // tool does — a backlog big enough to matter is exactly when a single
        // 100-row wall stops being usable.
        $failed = collect($failedJobRows)
            ->sortByDesc('failed_at')
            ->map(fn (object $job): array => [
                'id' => $job->id,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'exception' => strtok((string) $job->exception, "\n") ?: null,
                'failed_at' => $job->failed_at,
            ])->values();

        $page = Paginator::resolveCurrentPage();

        $paginator = new LengthAwarePaginator(
            $failed->forPage($page, self::FAILED_PER_PAGE)->values()->all(),
            $failed->count(),
            self::FAILED_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // A ?page= past the end (deleting a page's worth, or a stale bookmark)
        // would render an empty list rather than the real last page.
        if (Pagination::isPastLastPage($paginator, $page)) {
            return redirect()->route('system-settings.scheduler.index', array_filter([
                'tab' => 'failed',
                'page' => Pagination::redirectPage($paginator),
            ]));
        }

        return Inertia::render('system/settings/scheduler', [
            'tab' => $request->query('tab') === 'failed' ? 'failed' : 'tasks',
            'tasks' => $tasks,
            'failed_jobs' => $paginator->items(),
            'failed_pagination' => Pagination::meta($paginator),
            'failed_total' => $failed->count(),
            'pending_jobs_count' => DB::table('jobs')->count(),
        ]);
    }

    public function retryFailedJob(string $uuid): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('success', __('Job queued for retry.'));
    }

    public function destroyFailedJob(string $uuid): RedirectResponse
    {
        $this->failer->forget($uuid);

        return back()->with('success', __('Failed job deleted.'));
    }

    public function destroyAllFailedJobs(): RedirectResponse
    {
        // flush() with no argument clears every failed job regardless of age
        // — the same as `queue:flush`. The list only shows the most recent
        // 100, but this deletes all of them, which is the intent of a
        // "clear the backlog" button.
        $this->failer->flush();

        return back()->with('success', __('All failed jobs deleted.'));
    }

    /**
     * What a task actually found, for the tasks that find something.
     *
     * The run rows answer "did it work"; they cannot answer "and what did
     * it say", because Laravel's ScheduledTaskFinished event fires after
     * the command returns and carries no output — which is why a
     * successful check for updates has always shown an empty Message.
     * Joined here at render time instead, from what the command itself
     * wrote to the settings, so the line stays true whether the daily job
     * or somebody pressing the button did the work.
     *
     * @return array<string, string>
     */
    private function details(): array
    {
        $release = $this->latestRelease->current();
        $checkedAt = $this->settings->get(Setting::LatestVersionCheckedAt);
        $everChecked = is_string($checkedAt) && $checkedAt !== '';

        $updates = match (true) {
            $release !== null => (string) __(':version is available', ['version' => $release['version']]),
            $everChecked => (string) __('Up to date'),
            default => null,
        };

        return array_filter(['projectsend:check-for-updates' => $updates]);
    }
}
