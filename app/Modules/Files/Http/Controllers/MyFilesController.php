<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentingRules;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Folders\BreadcrumbBuilder;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Uploads\UploadExtensionPolicy;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Theming\PublicThemeRegistry;
use App\Support\ConcatenatedPagination;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's browsable file view. Root shows loose directly-assigned
 * files plus top-level shared folders; entering a shared folder shows
 * its subfolders and files. Group and internal folder names never leak:
 * a file assigned directly inside an unshared folder appears loose.
 *
 * index()'s props are consumed by every portal/themes/{key}/my-files.tsx
 * — see docs/theming-files-checklist.md before changing this method's
 * Inertia props or the behavior every theme is expected to preserve.
 */
class MyFilesController extends Controller
{
    /**
     * Folders and files share one page window — see FoldersController's
     * equivalent constant/docblock for why (folders were previously
     * fetched fully unbounded and repeated identically on every file
     * page; this is the client-portal twin of that same fix).
     */
    private const PER_PAGE = 25;

    public function __construct(
        private readonly Settings $settings,
        private readonly ClientStorageUsage $storageUsage,
        private readonly UploadExtensionPolicy $extensionPolicy,
        private readonly PublicThemeRegistry $themes,
        private readonly CapabilityRegistry $capabilities,
        private readonly BreadcrumbBuilder $breadcrumbs,
        private readonly CommentingRules $commenting,
        private readonly VisibleCommentScope $comments,
        private readonly DownloadAllowance $allowance,
        private readonly FileVersions $versions,
        private readonly FileVersionLinks $versionLinks,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'owner' => ['nullable', Rule::in(['mine', 'shared'])],
            'sort' => ['nullable', Rule::in(['name', 'size', 'date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $search = trim($validated['search'] ?? '');
        $searching = $search !== '';
        $categoryId = isset($validated['category']) ? (int) $validated['category'] : null;
        $owner = $validated['owner'] ?? null;
        $sort = $validated['sort'] ?? 'date';
        $direction = $validated['direction'] ?? 'desc';

        // Every folder the client may see: staff-shared subtrees plus any
        // folder they created themselves, anywhere in that visible tree.
        $visibleIds = array_values(Folder::query()->visibleToClient($client)->pluck('id')->map(fn ($id): int => (int) $id)->all());

        // A search term, a category filter, or an owner filter all switch to
        // a flat, global view across everything the client may see — same
        // convention as the staff library (FoldersController) uses for
        // search/category. Sorting never does: it only changes the order of
        // whichever set (flat or folder-scoped) is already being shown.
        $flat = $searching || $categoryId !== null || $owner !== null;

        if ($flat) {
            // `visibleToClient` is the single source of truth, so the
            // privacy rule (never surfacing an unshared folder's name) holds:
            // only shared folders match, and files are shown without folder
            // context in the portal rows.
            $current = null;
            $folders = $searching
                ? Folder::query()->visibleToClient($client)->where('name', 'like', "%{$search}%")->orderBy('name')
                : Folder::query()->whereRaw('1 = 0');

            $filesQuery = File::query()->visibleToClient($client)
                ->when($searching, fn (Builder $q) => $q
                    ->where(fn (Builder $w) => $w->where('name', 'like', "%{$search}%")->orWhere('original_name', 'like', "%{$search}%")));
        } else {
            // The folder being browsed must itself be visible to the client.
            $current = null;
            if ($request->integer('folder') > 0) {
                $current = Folder::query()->visibleToClient($client)->find($request->integer('folder'));
                abort_if($current === null, 404);
            }

            // Subfolders: at root, every visible folder whose parent isn't
            // itself visible (top of each shared subtree, or a client-owned
            // folder with no visible parent); inside a folder, its direct
            // children.
            if ($current === null) {
                $folders = Folder::query()
                    ->whereIn('id', $visibleIds)
                    ->where(fn ($q) => $q->whereNull('parent_id')->orWhereNotIn('parent_id', $visibleIds))
                    ->orderBy('name');
            } else {
                $folders = Folder::query()->where('parent_id', $current->id)->orderBy('name');
            }

            // Files: inside a folder, that folder's files; at root, only
            // loosely (directly/group) assigned files with no folder — the
            // ones in an unshared folder (or a visible one, browsed via its
            // own listing) show here with no folder context.
            $filesQuery = File::query()->visibleToClient($client);
            if ($current === null) {
                $filesQuery->where(fn (Builder $q) => $q->whereNull('folder_id')->orWhereNotIn('folder_id', $visibleIds));
            } else {
                $filesQuery->where('folder_id', $current->id);
            }
        }

        $filesQuery
            ->when($categoryId !== null, fn (Builder $q) => $q
                ->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $categoryId)))
            ->when($owner === 'mine', fn (Builder $q) => $q->where('uploaded_by', $client->id))
            ->when($owner === 'shared', fn (Builder $q) => $q->where('uploaded_by', '!=', $client->id));

        $column = match ($sort) {
            'name' => 'name',
            'size' => 'size',
            default => 'created_at',
        };
        // The download counts a spent-limit badge needs, attached only
        // when this installation uses limits at all — otherwise every
        // portal listing would pay two correlated subqueries per row for
        // a feature nobody here has turned on.
        $files = $this->allowance->withCounts($filesQuery, $client)
            ->with('categories', 'folder')
            ->orderBy($column, $direction);

        $page = Paginator::resolveCurrentPage();

        // ConcatenatedPagination is model-agnostic — Larastan's Builder
        // generic is invariant, so a heterogeneous array of builders
        // needs an explicit widen/narrow at the call site (sound at
        // runtime since each key's builder only ever queries its own
        // model). Same pattern as FoldersController::index().
        /** @var array<string, Builder<Model>> $sequences */
        $sequences = ['folders' => $folders, 'files' => $files];

        $sliced = ConcatenatedPagination::slice(
            $sequences,
            $page,
            self::PER_PAGE,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        if (Pagination::isPastLastPage($sliced['paginator'], $page)) {
            return redirect()->route('my-files.index', array_filter([
                'search' => $search !== '' ? $search : null,
                'folder' => $current?->id,
                'category' => $categoryId,
                'owner' => $owner,
                'sort' => $sort !== 'date' ? $sort : null,
                'direction' => $direction !== 'desc' ? $direction : null,
                'page' => Pagination::redirectPage($sliced['paginator']),
            ]));
        }

        /** @var Collection<int, Folder> $folderRows */
        $folderRows = $sliced['items']['folders'];
        /** @var Collection<int, File> $fileRows */
        $fileRows = $sliced['items']['files'];

        $commentCounts = $this->comments->countsFor($client, $fileRows);
        // Two queries for the page, not two per row. No URL resolver: the
        // portal has no per-file page to link to, so a counterpart is named
        // and not linked (see docs/theming-files-checklist.md).
        $versions = $this->versionLinks->forMany($fileRows, $client);
        $unreadComments = $this->comments->unreadCountsFor($client, array_values(array_map(intval(...), $fileRows->pluck('id')->all())));

        return Inertia::render("portal/themes/{$this->themeKey()}/my-files", [
            'folder' => $current === null ? null : ['id' => $current->id, 'name' => $current->name],
            'breadcrumb' => $flat ? [] : $this->breadcrumbs->visible($current, $visibleIds),
            'folders' => $folderRows->map(fn (Folder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'is_mine' => $folder->created_by === $client->id,
                'public' => $folder->isEffectivelyPublic(),
                'can_update' => Gate::forUser($client)->allows('update', $folder),
                'can_delete' => Gate::forUser($client)->allows('delete', $folder),
            ])->values()->all(),
            // Comment counts for the page's rows, resolved in two queries
            // for the whole page rather than one per row — see
            // VisibleCommentScope::countsFor.
            'comments_enabled' => $this->commenting->enabled(),
            'files' => $fileRows->map(fn (File $file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'description' => $file->description,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'created_at' => $file->created_at?->toIso8601String(),
                'is_mine' => $file->uploaded_by === $client->id,
                // Effective status (own flag or inherited from a public
                // folder) — same "will visitors on the public site see
                // this" badge as the staff library shows.
                'public' => $file->isEffectivelyPublic(),
                'comments_count' => $commentCounts[$file->id] ?? 0,
                'unread_comments_count' => $unreadComments[$file->id] ?? 0,
                // Already narrowed to what this client may be told: a
                // counterpart they were not given is null, not hidden by
                // the theme. A theme must never filter this itself.
                'version' => $versions[$file->id] ?? ['previous' => null, 'next' => null],
                'categories' => $file->categories->map(fn (Category $category): array => [
                    'id' => $category->id, 'name' => $category->name, 'color' => $category->color,
                ])->values()->all(),
                // Already decided — see DownloadAllowance::summaryFor.
                // `blocked` means this client may no longer take a copy;
                // the row still shows, which is the whole difference from
                // an expired file.
                'download_limit' => $this->allowance->summaryFor($file, $client),
            ])->values()->all(),
            'pagination' => Pagination::meta($sliced['paginator']),
            'search' => $search,
            'searching' => $flat,
            'category' => $categoryId,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color'])
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])->all(),
            'owner' => $owner,
            'sort' => $sort,
            'direction' => $direction,
            'can_upload' => $client->can('upload'),
            'can_upload_here' => Folder::uploadableBy($client, $current),
            'can_create_folders' => $client->can('create_own_folders'),
        ]);
    }

    public function upload(Request $request): Response
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);
        abort_unless($client->can('upload'), 403);

        $folder = null;
        if ($request->integer('folder') > 0) {
            $folder = Folder::query()->visibleToClient($client)->find($request->integer('folder'));
            abort_if($folder === null, 404);
            abort_unless(Folder::uploadableBy($client, $folder), 403);
        }

        return Inertia::render('portal/upload', [
            'allowed_extensions' => $this->extensionPolicy->hintFor($client),
            'max_file_size_mb' => (int) $this->settings->get(Setting::MaxFileSizeMb),
            'part_size_mb' => (int) config('projectsend.upload_part_size_mb'),
            'remaining_bytes' => $this->storageUsage->remainingBytes($client),
            'quota_bytes' => $this->storageUsage->quotaBytes($client) ?: null,
            'used_bytes' => $this->storageUsage->usedBytes($client),
            'theme' => $this->themeKey(),
            'folder' => $folder === null ? null : ['id' => $folder->id, 'name' => $folder->name],
            // Upload time is the only place a client can set this: there is
            // no per-file editor in the portal, and none is being added.
            'version_candidates_url' => route('my-files.version-candidates', [], false),
        ]);
    }

    /**
     * Files this client may name as the previous version of what they are
     * uploading — THEIR OWN UPLOADS ONLY.
     *
     * Not the files visible to them: a revision inherits the recipients of
     * the file it revises, so offering a file staff shared with them would
     * be offering a way to publish their upload to a recipient list they do
     * not own. FilePolicy::setVersion enforces the same rule server-side
     * when the upload completes; this only keeps the picker honest.
     */
    public function versionCandidates(Request $request): JsonResponse
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);
        abort_unless($client->can('upload'), 403);

        $candidates = $this->versions
            ->candidates(null, $client, trim((string) $request->query('search', '')))
            ->map(fn (File $file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
            ])->values();

        return response()->json(['files' => $candidates]);
    }

    private function themeKey(): string
    {
        $value = $this->settings->get(Setting::Theme);

        return $this->themes->resolve(is_string($value) ? $value : 'default', $this->capabilities);
    }
}
