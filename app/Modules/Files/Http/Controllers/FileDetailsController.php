<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityPresenter;
use App\Modules\Audit\DownloadPresenter;
use App\Modules\Comments\CommentingRules;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Access\ShareTargets;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * JSON feeds for the details slide-over (Details / Sharing / Activity),
 * so a file's panel opens over the list without navigating away.
 */
class FileDetailsController extends Controller
{
    /** Raw rows considered when grouping downloads() by actor — see that method's docblock. */
    private const DOWNLOADS_SUMMARY_LIMIT = 500;

    /**
     * How a file leaves: three actions, because *how* it left matters —
     * a signed-in recipient, somebody following a public link, and a
     * visitor to a public group listing are all recorded separately.
     *
     * @var non-empty-list<Action>
     */
    private const DOWNLOAD_ACTIONS = [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded];

    /**
     * Looking at a file without taking it. One action today; if a second
     * way to preview is ever recorded separately, add it here and give
     * previews an ACTION_GROUPS entry the way downloads has one.
     *
     * @var non-empty-list<Action>
     */
    private const PREVIEW_ACTIONS = [Action::FilePreviewed];

    /**
     * Filters that stand for a question rather than for one logged action.
     *
     * Nobody reading a file's history wants to ask "who downloaded this?"
     * three times, so this offers it once — and only when the file's own
     * log holds more than one of the members, since otherwise it would
     * filter to exactly what its single member already offers.
     *
     * @var array<string, array{label: string, actions: non-empty-list<Action>}>
     */
    private const ACTION_GROUPS = [
        'downloads' => [
            'label' => 'All downloads',
            'actions' => self::DOWNLOAD_ACTIONS,
        ],
    ];

    public function __construct(
        private readonly ActivityPresenter $presenter,
        private readonly DownloadPresenter $downloadPresenter,
        private readonly ShareTargets $shareTargets,
        private readonly CommentingRules $commenting,
        private readonly FileVersionLinks $versionLinks,
        private readonly DownloadAllowance $allowance,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function show(Request $request, File $file): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);

        return response()->json([
            'type' => 'file',
            'id' => $file->id,
            'name' => $file->name,
            'description' => $file->description,
            'original_name' => $file->original_name,
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'checksum' => $file->checksum,
            'uploader' => $file->uploader?->name,
            'folder' => $file->folder?->only('id', 'name'),
            'categories' => $file->categories()->orderBy('name')->get()
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])
                ->values(),
            'created_at' => $file->created_at?->toIso8601String(),

            // The two rules that decide whether this file can still be
            // taken. The panel is where someone looks to find out why a
            // client says they cannot download something, so it has to
            // answer that without sending them to the edit page.
            'expires_at' => $file->expires_at?->toIso8601String(),
            'expired' => $file->isExpired(),
            'download_limit' => $file->download_limit,
            'download_limit_scope' => ($file->download_limit_scope ?? DownloadLimitScope::Total)->value,
            // The file's total downloads, whatever the scope. Under a
            // per-user limit no single figure can stand for "used", so
            // the panel presents this as the file's own count rather
            // than as a share of anyone's allowance.
            'downloads_used' => $file->downloads()->count(),
            // What *this* viewer has left, which is what the panel's own
            // download button obeys. Not the same question as the row
            // above: staff who did not upload the file are subject to
            // its limit like anybody else.
            'download_allowance' => $this->allowance->summaryFor($file, $viewer),

            'version' => $this->versionLinks->for($file, $viewer, fn (File $other): string => route('files.edit', $other, false)),
            'download_url' => route('files.download', $file, false),
            'edit_url' => route('files.edit', $file, false),
            'can_update' => Gate::forUser($viewer)->allows('update', $file),
            'can_view_activity' => $viewer->can('view_actions_log'),
            // Whether the panel offers a Comments tab at all. False only
            // when the install has commenting off, or this file falls
            // outside the configured scope — existing comments on a file
            // that has left the scope stay readable, so this stays true
            // while there is anything to read.
            'comments_enabled' => $this->commenting->enabled(),
            // Resolved from the chain root for a revision (ShareTargets
            // does that), so this names who really has the file. The panel
            // says where those recipients are set.
            'shares' => $this->shareTargets->assigned($file),
            'sharing_root' => $file->isRevision()
                ? File::query()->find($file->sharingOwnerId())?->only('id', 'name')
                : null,
            // Read-only here: creating/revoking a public link is edited
            // from the file's own edit page, not this info panel.
            'share_links' => $file->shareLinks()->orderByDesc('created_at')->get()
                ->map(fn (ShareLink $link): array => [
                    'id' => $link->id,
                    'url' => route('share.show', $link->token),
                    'expires_at' => $link->expires_at?->toIso8601String(),
                    'max_downloads' => $link->max_downloads,
                    'downloads_count' => $link->downloads_count,
                ])->values(),
        ]);
    }

    public function activity(Request $request, File $file): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);
        abort_unless($viewer->can('view_actions_log'), 403);

        $query = ActivityLog::query()
            ->where('subject_type', $file->getMorphClass())
            ->where('subject_id', $file->id);

        $total = (clone $query)->count();

        $entries = $query
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(20)->get()
            ->map(fn (ActivityLog $entry): array => $this->presenter->present($entry));

        return response()->json(['entries' => $entries, 'total' => $total]);
    }

    /**
     * Who has actually had this file: its downloads and previews, newest
     * first, with a count of each.
     *
     * A narrower question than activity() and a much more frequent one —
     * "did they ever actually get it?" — which the full log answers only
     * by being read past everything else that has happened to the file.
     */
    public function access(Request $request, File $file): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);
        abort_unless($viewer->can('view_actions_log'), 403);

        $base = fn (): Builder => ActivityLog::query()
            ->where('subject_type', $file->getMorphClass())
            ->where('subject_id', $file->id);

        $entries = $base()
            ->whereIn('action', [...self::DOWNLOAD_ACTIONS, ...self::PREVIEW_ACTIONS])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(20)->get()
            ->map(fn (ActivityLog $entry): array => [
                // The sentence the presenter builds already says which of
                // the two this was ("Downloaded the file …"), so nothing
                // here has to label the row a second time.
                ...$this->presenter->present($entry),
                // Subject to the privacy setting that decides whether an
                // address is recorded at all, so it is often null.
                'ip_address' => $entry->ip_address,
            ]);

        return response()->json([
            'entries' => $entries,
            'downloads_total' => $base()->whereIn('action', self::DOWNLOAD_ACTIONS)->count(),
            'previews_total' => $base()->whereIn('action', self::PREVIEW_ACTIONS)->count(),
            // Built here rather than in the page: which filter value stands
            // for "every download" is a fact about the log's vocabulary,
            // and a group key only exists while the group does.
            'downloads_url' => $this->historyUrl($file, 'downloads', self::DOWNLOAD_ACTIONS),
            'previews_url' => $this->historyUrl($file, 'previews', self::PREVIEW_ACTIONS),
        ]);
    }

    /**
     * The file's history, pre-filtered to one question: by the group when
     * one covers these actions, and by the action itself when the group
     * would have a single member and therefore does not exist.
     *
     * @param  non-empty-list<Action>  $actions
     */
    private function historyUrl(File $file, string $groupKey, array $actions): string
    {
        $filter = isset(self::ACTION_GROUPS[$groupKey]) ? $groupKey : $actions[0]->value;

        return route('files.activity.history', $file, false).'?action='.$filter;
    }

    /**
     * Full, paginated activity history for a file — the "View full
     * history" destination linked from the details panel's Activity tab,
     * which only shows the most recent 20 entries.
     */
    public function activityHistory(Request $request, File $file): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);
        abort_unless($viewer->can('view_actions_log'), 403);

        return $this->renderHistory(
            $request,
            $file->getMorphClass(),
            $file->id,
            $file->name,
            route('files.edit', $file, false).'?tab=activity',
            'files.activity.history',
            ['file' => $file->id],
        );
    }

    /**
     * Who downloaded this file and how many times, grouped by actor,
     * with each individual download's timestamp and IP address so the
     * list can be expanded per person.
     *
     * Grouping happens in PHP (the group key mixes actor and link/public
     * cases, not a single column), so it runs over the most recent
     * DOWNLOADS_SUMMARY_LIMIT raw rows rather than the whole table —
     * accurate for typical files, but a heavy downloader's count could
     * undercount past that window. `total` is a true, unbounded count;
     * the full unbounded per-row list lives at downloadsHistory().
     */
    public function downloads(Request $request, File $file): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);
        abort_unless($viewer->can('view_actions_log'), 403);

        $query = ActivityLog::query()
            ->where('subject_type', $file->getMorphClass())
            ->where('subject_id', $file->id)
            ->whereIn('action', self::DOWNLOAD_ACTIONS);

        $total = (clone $query)->count();

        $entries = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::DOWNLOADS_SUMMARY_LIMIT)
            ->get();

        $downloaders = $entries
            ->groupBy(function (ActivityLog $entry): string {
                return match ($entry->action) {
                    Action::ShareLinkDownloaded => 'share_link',
                    Action::PublicFileDownloaded => 'public_listing',
                    default => $entry->actor_id !== null ? 'user:'.$entry->actor_id : 'deleted:'.$entry->actor_name,
                };
            })
            ->map(function (Collection $group): array {
                /** @var ActivityLog $first */
                $first = $group->first();
                $entry = $this->downloadPresenter->present($first);

                return [
                    'actor_id' => $first->actor_id,
                    'actor_name' => $entry['actor_name'],
                    'actor_type' => $entry['actor_type'],
                    'count' => $group->count(),
                    'downloads' => $group->map(fn (ActivityLog $entry): array => [
                        'created_at' => $entry->created_at->toIso8601String(),
                        'ip_address' => $entry->ip_address,
                    ])->values(),
                ];
            })
            ->sortByDesc('count')
            ->values();

        return response()->json(['downloaders' => $downloaders, 'total' => $total]);
    }

    /**
     * Full, paginated download history for a file — the flat, one-row-
     * per-download counterpart to downloads() above, which only groups
     * and caps for the details panel's Downloads tab.
     */
    public function downloadsHistory(Request $request, File $file): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);
        abort_unless($viewer->can('view_actions_log'), 403);

        $entries = ActivityLog::query()
            ->where('subject_type', $file->getMorphClass())
            ->where('subject_id', $file->id)
            ->whereIn('action', [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('activity/downloads', [
            'entries' => $entries->getCollection()->map(fn (ActivityLog $entry): array => $this->downloadPresenter->present($entry))->all(),
            'pagination' => [
                'page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
                'total' => $entries->total(),
            ],
            'subject_name' => $file->name,
            'back_url' => route('files.edit', $file, false),
        ]);
    }

    public function showFolder(Request $request, Folder $folder): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $folder);

        return response()->json([
            'type' => 'folder',
            'id' => $folder->id,
            'name' => $folder->name,
            'files_count' => $folder->files()->count(),
            'children_count' => $folder->children()->count(),
            'creator' => $folder->creator?->name,
            'created_at' => $folder->created_at?->toIso8601String(),
            'open_url' => route('files.index', ['folder' => $folder->id], false),
            // Read-only here, same as a file's shares — sharing (and every
            // other editable field) is changed from the folder's own edit
            // page, not this info panel.
            'edit_url' => route('folders.share', $folder, false),
            'can_update' => Gate::forUser($viewer)->allows('update', $folder),
            'can_view_activity' => $viewer->can('view_actions_log'),
            'shares' => $this->shareTargets->assigned($folder),
        ]);
    }

    public function folderActivity(Request $request, Folder $folder): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $folder);
        abort_unless($viewer->can('view_actions_log'), 403);

        $query = ActivityLog::query()
            ->where('subject_type', $folder->getMorphClass())
            ->where('subject_id', $folder->id);

        $total = (clone $query)->count();

        $entries = $query
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(20)->get()
            ->map(fn (ActivityLog $entry): array => $this->presenter->present($entry));

        return response()->json(['entries' => $entries, 'total' => $total]);
    }

    /**
     * Full, paginated activity history for a folder — same idea as
     * activityHistory(), for the folder details panel.
     */
    public function folderActivityHistory(Request $request, Folder $folder): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $folder);
        abort_unless($viewer->can('view_actions_log'), 403);

        return $this->renderHistory(
            $request,
            $folder->getMorphClass(),
            $folder->id,
            $folder->name,
            route('files.index', ['folder' => $folder->id], false),
            'folders.activity.history',
            ['folder' => $folder->id],
        );
    }

    /**
     * @param  array<string, mixed>  $routeParams
     */
    private function renderHistory(
        Request $request,
        string $morphClass,
        int $subjectId,
        string $subjectName,
        string $backUrl,
        string $routeName,
        array $routeParams,
    ): Response {
        $viewer = $request->user();
        assert($viewer !== null);

        $filters = $this->validatedHistoryFilters($request);

        $entries = $this->historyQuery($morphClass, $subjectId, $filters, $viewer)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('activity/subject', [
            'entries' => $entries->getCollection()
                ->map(fn (ActivityLog $entry): array => $this->presenter->present($entry))
                ->all(),
            'pagination' => [
                'page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
                'total' => $entries->total(),
            ],
            'filters' => $filters,
            'action_options' => $this->actionOptions($morphClass, $subjectId, $filters['action']),
            'subject_name' => $subjectName,
            'back_url' => $backUrl,
            'route_name' => $routeName,
            'route_params' => $routeParams,
        ]);
    }

    /**
     * The actions this subject's history actually contains, with how many
     * times each happened.
     *
     * Built from the log rather than from `Action::cases()`: the enum has
     * over eighty members and all but a handful can never appear against a
     * file, so offering them all would be a dropdown you scroll past the
     * answer in. What is here is what happened.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function actionOptions(string $morphClass, int $subjectId, ?string $active): array
    {
        /** @var array<string, int> $counts */
        $counts = ActivityLog::query()
            ->where('subject_type', $morphClass)
            ->where('subject_id', $subjectId)
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $options = [];

        foreach (self::ACTION_GROUPS as $key => $group) {
            $present = array_filter($group['actions'], fn (Action $action): bool => isset($counts[$action->value]));

            // One member present means the group would filter to exactly
            // what its member already offers, under a vaguer name — unless
            // this *is* what is currently being filtered on (the file
            // page's "View all downloads" button links straight to it), in
            // which case the dropdown has to be able to show its own value.
            if (count($present) < 2 && $active !== $key) {
                continue;
            }

            $options[] = [
                'key' => $key,
                'label' => $group['label'],
                'count' => array_sum(array_map(fn (Action $action): int => $counts[$action->value], $present)),
            ];
        }

        // Enum order, not count order, so the list does not rearrange
        // itself under the reader every time the file is downloaded.
        foreach (Action::cases() as $action) {
            // Same reason as the group above: a filter arrived at from a
            // link stays visible in the dropdown even at a count of zero,
            // rather than leaving it blank over an empty table.
            if (! isset($counts[$action->value]) && $active !== $action->value) {
                continue;
            }

            $options[] = [
                'key' => $action->value,
                'label' => $action->description(),
                'count' => $counts[$action->value] ?? 0,
            ];
        }

        return $options;
    }

    /**
     * @return array{action: ?string, actor: ?string, from: ?string, to: ?string}
     */
    private function validatedHistoryFilters(Request $request): array
    {
        $validated = $request->validate([
            'action' => ['nullable', Rule::in([
                ...array_keys(self::ACTION_GROUPS),
                ...array_column(Action::cases(), 'value'),
            ])],
            'actor' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'action' => $validated['action'] ?? null,
            'actor' => $validated['actor'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * @param  array{action: ?string, actor: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<ActivityLog>
     */
    private function historyQuery(string $morphClass, int $subjectId, array $filters, User $viewer): Builder
    {
        $timezone = $this->timezones->resolve($viewer);

        return ActivityLog::query()
            ->where('subject_type', $morphClass)
            ->where('subject_id', $subjectId)
            ->when($filters['action'], function (Builder $query, string $action): void {
                $group = self::ACTION_GROUPS[$action] ?? null;

                $group === null
                    ? $query->where('action', $action)
                    : $query->whereIn('action', array_map(fn (Action $member): string => $member->value, $group['actions']));
            })
            // Matched on the name snapshotted onto the entry, the same as
            // the main log: an account deleted since is still findable by
            // the name it acted under, which is the whole point of the
            // snapshot.
            ->when($filters['actor'], fn (Builder $query, string $actor) => $query->where('actor_name', 'like', "%{$actor}%"))
            // The reader's own calendar day, not the UTC one — see LocalDay.
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
