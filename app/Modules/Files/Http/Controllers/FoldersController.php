<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentingRules;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Access\ShareTargets;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\BreadcrumbBuilder;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Groups\Models\Group;
use App\Support\ConcatenatedPagination;
use App\Support\Pagination;
use App\Support\PublicUrl;
use App\Support\Rules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff folder library (staff-only, permission-refined). Folders are
 * never assigned to staff — visibility comes from StaffLibraryScope.
 */
class FoldersController extends Controller
{
    /**
     * Folders and files share one page window (folders first, files
     * filling whatever room is left) rather than folders being an
     * unbounded, always-fully-shown block above a separately paginated
     * file list — see index()'s own docblock for why.
     */
    private const PER_PAGE = 25;

    public function __construct(
        private readonly FolderService $folders,
        private readonly StaffLibraryScope $scope,
        private readonly ActivityLogger $activity,
        private readonly PublicUrl $publicUrl,
        private readonly ShareTargets $shareTargets,
        private readonly BreadcrumbBuilder $breadcrumbs,
        private readonly CommentingRules $commenting,
        private readonly VisibleCommentScope $comments,
        private readonly FileVersionLinks $versionLinks,
        private readonly DownloadAllowance $allowance,
    ) {}

    /**
     * Folders and files are two independently-ordered sequences
     * (folders by name, files by whatever the current mode sorts by)
     * concatenated into one flat sequence and sliced into fixed
     * PER_PAGE-sized pages — folders first, files filling whatever room
     * is left once folders run out. This keeps every folder and file
     * reachable via Next/Previous with a bounded per-page query cost,
     * regardless of how many folders or files exist — the previous
     * "fetch every folder, unpaginated, on every file page" approach
     * both repeated the same folder rows on every page and had no cap
     * at all for a directory with hundreds/thousands of subfolders.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            // Not a 'boolean' rule: that only accepts true/false/0/1/'0'/'1',
            // rejecting the literal "true"/"" the frontend checkbox sends.
            // $request->boolean() below coerces any of those safely, so
            // there's nothing for a validation rule to add here.
        ]);
        $search = trim($validated['search'] ?? '');
        $searching = $search !== '';
        $categoryId = $validated['category'] ?? null;
        $expired = $request->boolean('expired');

        // A search term, a category filter, or the expired-only filter all
        // switch to a flat view across the whole visible library;
        // otherwise it's folder browsing.
        $flat = $searching || $categoryId !== null || $expired;

        $folderQuery = $this->scope->folders($user)->withCount(['children', 'files']);
        // `downloads` unconditionally — the library has always shown a
        // downloads column. The viewer's own count is only added when
        // something on this install is actually limited.
        $fileQuery = $this->allowance->withOwnCount(
            $this->scope->files($user)->with('uploader.role', 'categories', 'folder')
                ->withCount(['assignments', 'downloads']),
            $user,
        );

        if ($flat) {
            $current = null;
            $folders = $searching
                ? $folderQuery->where('name', 'like', "%{$search}%")->orderBy('name')
                : $folderQuery->whereRaw('1 = 0');
            $files = $fileQuery
                ->when($searching, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', "%{$search}%")->orWhere('original_name', 'like', "%{$search}%")))
                ->when($categoryId !== null, fn (Builder $q) => $q
                    ->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $categoryId)))
                ->when($expired, fn (Builder $q) => $q->expired())
                ->orderBy('name');
        } else {
            $current = $request->integer('folder') > 0
                ? $this->scope->folders($user)->find($request->integer('folder'))
                : null;
            $folders = $folderQuery
                ->where('parent_id', $current?->id)
                ->orderBy('name');
            $files = $fileQuery
                ->where('folder_id', $current?->id)
                ->orderByDesc('created_at');
        }

        $page = Paginator::resolveCurrentPage();

        // ConcatenatedPagination is intentionally model-agnostic (Folder
        // here, File/Group elsewhere) — Larastan's Builder generic is
        // invariant, so a heterogeneous array of builders needs an
        // explicit widen/narrow at each call site; sound at runtime since
        // each key's builder only ever queries its own model.
        /** @var array<string, Builder<Model>> $sequences */
        $sequences = ['folders' => $folders, 'files' => $files];

        $sliced = ConcatenatedPagination::slice(
            $sequences,
            $page,
            self::PER_PAGE,
            ['path' => $request->url(), 'query' => $request->query()],
        );
        /** @var Collection<int, Folder> $folderRows */
        $folderRows = $sliced['items']['folders'];
        /** @var Collection<int, File> $fileRows */
        $fileRows = $sliced['items']['files'];

        // A stale/guessed ?page= beyond what actually exists (same
        // protection OrphanFilesController::index() already has) would
        // otherwise silently render an empty page instead of the real one.
        if (Pagination::isPastLastPage($sliced['paginator'], $page)) {
            return redirect()->route('files.index', array_filter([
                'search' => $search !== '' ? $search : null,
                'folder' => $current?->id,
                'category' => $categoryId,
                'expired' => $expired ? 'true' : null,
                'page' => Pagination::redirectPage($sliced['paginator']),
            ]));
        }

        // Resolved once for the whole page rather than per row — see
        // VisibleCommentScope::countsFor.
        $fileIds = array_values(array_map(intval(...), $fileRows->pluck('id')->all()));
        $commentCounts = $this->comments->countsFor($user, $fileRows);
        $pendingCounts = $this->comments->pendingCountsFor($user, $fileIds);
        // Two queries for the page, not two per row — same batching shape
        // as the comment counts above.
        $versions = $this->versionLinks->forMany($fileRows, $user, fn (File $other): string => route('files.edit', $other, false));

        return Inertia::render('files/index', [
            'folder' => $current === null ? null : ['id' => $current->id, 'name' => $current->name],
            'breadcrumb' => $flat ? [] : $this->breadcrumbs->for($current),
            'folders' => $folderRows->map(fn (Folder $folder): array => $this->folderRow($user, $folder))->all(),
            'files' => $fileRows->map(fn (File $file): array => $this->fileRow($user, $file, $commentCounts, $pendingCounts, $versions))->all(),
            'pagination' => Pagination::meta($sliced['paginator']),
            'search' => $search,
            'searching' => $flat,
            'category' => $categoryId,
            'expired' => $expired,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color'])
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])->all(),
            'folder_options' => Folder::query()->orderBy('path')->orderBy('name')->get()
                ->map(fn (Folder $folder): array => ['id' => $folder->id, 'name' => $folder->name])->all(),
            'can_create_folders' => $user->can('create_own_folders'),
            'can_upload' => $user->can('upload'),
            'can_manage_public' => $user->can('upload_public'),
            'comments_enabled' => $this->commenting->enabled(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function folderRow(User $user, Folder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            // Effective status (self or inherited from a public ancestor),
            // not just the folder's own flag — this is the "will visitors
            // on the public site see this" badge, same as fileRow() below.
            'public' => $folder->isEffectivelyPublic(),
            // Unlike the badge above, a working public page only exists for
            // a folder that is *itself* flagged public — PublicFoldersController
            // looks up strictly on `public = true`, so a folder that's only
            // effectively public via an ancestor has no page of its own.
            'public_url' => $folder->public
                ? $this->publicUrl->for($folder)
                : null,
            'children_count' => $folder->children_count,
            'files_count' => $folder->files_count,
            'shared_count' => $folder->assignments()->count(),
            'can_update' => Gate::forUser($user)->allows('update', $folder),
            'can_delete' => Gate::forUser($user)->allows('delete', $folder),
        ];
    }

    /**
     * @param  array<int, int>  $commentCounts
     * @param  array<int, int>  $pendingCounts
     * @param  array<int, array{previous: array{id: int, name: string, url: string|null}|null, next: array{id: int, name: string, url: string|null}|null}>  $versions
     * @return array<string, mixed>
     */
    private function fileRow(User $user, File $file, array $commentCounts = [], array $pendingCounts = [], array $versions = []): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'uploader' => $file->uploader ? [
                'name' => $file->uploader->name,
                'type' => $file->uploader->type->value,
                'role' => $file->uploader->role?->name,
            ] : null,
            'public' => $file->isEffectivelyPublic(),
            'expired' => $file->isExpired(),
            // No link at all once expired — the public route 404s past
            // expiry too (see File::scopeNotExpired's callers), so there's
            // no point offering a button that leads to a dead page.
            'public_url' => ($file->isEffectivelyPublic() && ! $file->isExpired())
                ? $this->publicUrl->for($file)
                : null,
            'assignments_count' => $file->assignments_count,
            'downloads_count' => $file->downloads_count,
            // Staff are subject to a limit like anyone else — only the
            // file's own uploader is exempt — so the library shows the
            // same spent state the portal does.
            'download_limit' => $this->allowance->summaryFor($file, $user),
            'comments_count' => $commentCounts[$file->id] ?? 0,
            // Only ever non-zero for a moderator: a staff member who
            // cannot approve should not be shown a badge asking them to.
            'pending_comments_count' => $pendingCounts[$file->id] ?? 0,
            'created_at' => $file->created_at?->toIso8601String(),
            // Already narrowed to what this viewer may be told about; a
            // null end is "no such link, or not yours to know".
            'version' => $versions[$file->id] ?? ['previous' => null, 'next' => null],
            'can_update' => Gate::forUser($user)->allows('update', $file),
            'can_delete' => Gate::forUser($user)->allows('delete', $file),
            'categories' => $file->categories->map(fn (Category $category): array => [
                'id' => $category->id, 'name' => $category->name, 'color' => $category->color,
            ])->values()->all(),
        ];
    }

    public function edit(Request $request, Folder $folder): Response
    {
        $user = $request->user();
        assert($user !== null);
        Gate::forUser($user)->authorize('view', $folder);

        return Inertia::render('files/folder', [
            'folder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
                'public' => $folder->public,
                'allow_client_uploads' => $folder->allow_client_uploads,
                'slug' => $folder->slug,
            ],
            'public_url' => $folder->public
                ? $this->publicUrl->for($folder)
                : null,
            'breadcrumb' => $this->breadcrumbs->for($folder),
            'can_update' => Gate::forUser($user)->allows('update', $folder),
            'can_manage_public' => $user->can('upload_public'),
            ...$this->shareTargets->forSubject($folder, $user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        // Requires upload too, same as the client portal's MyFoldersController::store()
        // — a folder nobody can put anything in isn't useful on its own.
        abort_unless($user !== null && $user->can('create_own_folders') && $user->can('upload'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
            'public' => ['sometimes', 'boolean'],
            'slug' => Rules::slug('folders'),
            'allow_client_uploads' => ['sometimes', 'boolean'],
        ]);

        $parent = $this->resolveParent($user, $validated['parent_id'] ?? null);

        $folder = $this->folders->create($validated['name'], $parent);

        // Only a user who can manage public state may set it on create —
        // create_own_folders alone doesn't imply upload_public.
        if ($user->can('upload_public')) {
            $public = $validated['public'] ?? false;
            $folder->update([
                'public' => $public,
                'slug' => $public ? (($validated['slug'] ?? '') ?: $folder->slug) : $folder->slug,
                'allow_client_uploads' => $public && ($validated['allow_client_uploads'] ?? false),
            ]);
        }

        $this->activity->log(Action::FolderCreated, subject: $folder);

        if ($folder->public) {
            $this->activity->log(Action::FolderMadePublic, subject: $folder, context: [
                'allow_client_uploads' => $folder->allow_client_uploads,
                'slug' => $folder->slug,
            ]);
        }

        return back()->with('success', __('Folder created.'));
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $folder);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'public' => ['sometimes', 'boolean'],
            // Omitting the field on an update leaves the current slug
            // alone — it must not silently change just because the name
            // did, same rule as FilesController::update.
            'slug' => Rules::slug('folders', $folder->id),
            'allow_client_uploads' => ['sometimes', 'boolean'],
        ]);

        $attributes = ['name' => $validated['name']];

        $wasPublic = $folder->public;
        $wasAllowClientUploads = $folder->allow_client_uploads;

        // Only a user who can manage public state may change it — a user
        // who can rename/share a folder but lacks upload_public leaves its
        // public state exactly as it was.
        if ($user?->can('upload_public') === true) {
            $public = $validated['public'] ?? $folder->public;
            $attributes['public'] = $public;
            $attributes['slug'] = $public ? (($validated['slug'] ?? '') ?: ($folder->slug ?: Folder::uniqueSlugFrom($validated['name'], $folder->id))) : $folder->slug;
            $attributes['allow_client_uploads'] = $public && ($validated['allow_client_uploads'] ?? false);
        }

        $folder->update($attributes);

        $this->activity->log(Action::FolderRenamed, subject: $folder);

        if ($folder->public && (! $wasPublic || $folder->allow_client_uploads !== $wasAllowClientUploads)) {
            $this->activity->log(Action::FolderMadePublic, subject: $folder, context: [
                'allow_client_uploads' => $folder->allow_client_uploads,
                'slug' => $folder->slug,
            ]);
        } elseif ($wasPublic && ! $folder->public) {
            $this->activity->log(Action::FolderMadePrivate, subject: $folder);
        }

        return back();
    }

    public function move(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $newParent = $this->resolveParent($request->user(), $validated['parent_id'] ?? null);

        $this->folders->move($folder, $newParent);

        $this->activity->log(Action::FolderMoved, subject: $folder);

        return back();
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $name = $folder->name;
        $parentId = $folder->parent_id;

        $this->folders->delete($folder);

        $this->activity->log(Action::FolderDeleted, context: ['name' => $name]);

        return redirect()->route('files.index', $parentId !== null ? ['folder' => $parentId] : [])->with('success', __('Folder deleted.'));
    }

    private function resolveParent(?User $user, ?int $parentId): ?Folder
    {
        if ($user === null || $parentId === null) {
            return null;
        }

        return $this->scope->folders($user)->findOrFail($parentId);
    }
}
