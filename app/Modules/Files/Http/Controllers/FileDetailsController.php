<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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

    public function __construct(
        private readonly ActivityPresenter $presenter,
        private readonly DownloadPresenter $downloadPresenter,
        private readonly ShareTargets $shareTargets,
        private readonly CommentingRules $commenting,
        private readonly FileVersionLinks $versionLinks,
        private readonly DownloadAllowance $allowance,
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

        return $this->renderHistory($file->getMorphClass(), $file->id, $file->name, route('files.edit', $file, false));
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
            ->whereIn('action', [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded]);

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

        return $this->renderHistory($folder->getMorphClass(), $folder->id, $folder->name, route('files.index', ['folder' => $folder->id], false));
    }

    private function renderHistory(string $morphClass, int $subjectId, string $subjectName, string $backUrl): Response
    {
        $entries = ActivityLog::query()
            ->where('subject_type', $morphClass)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')->orderByDesc('id')
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
            'subject_name' => $subjectName,
            'back_url' => $backUrl,
        ]);
    }
}
