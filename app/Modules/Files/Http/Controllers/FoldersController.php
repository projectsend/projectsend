<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentingRules;
use App\Modules\Files\Access\ClientIdentityScope;
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
use App\Modules\Identity\Models\Role;
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
        private readonly ClientIdentityScope $identity,
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
            'uploader' => ['nullable', 'integer', 'exists:users,id'],
            'visibility' => ['nullable', 'in:public,private'],
            'downloads' => ['nullable', 'in:none,any'],
            'role' => ['nullable', 'integer', 'exists:roles,id'],
            // "current" is every file nothing has replaced, which includes
            // a file that was never versioned at all -- it is the current
            // version of itself. "outdated" is the same word the version
            // badge uses, so the filter and the row agree.
            'version' => ['nullable', 'in:current,outdated'],
            // Not a 'boolean' rule: that only accepts true/false/0/1/'0'/'1',
            // rejecting the literal "true"/"" the frontend checkbox sends.
            // $request->boolean() below coerces any of those safely, so
            // there's nothing for a validation rule to add here.
        ]);
        $search = trim($validated['search'] ?? '');
        $searching = $search !== '';
        $categoryId = $validated['category'] ?? null;
        // Cast, because `integer` validates a numeric string without
        // converting it -- so these arrive as "5" from the query string.
        // permitsClientId() below takes a strict ?int and 500s on a string,
        // and the props these become are typed `number | null` on the page.
        $uploaderId = isset($validated['uploader']) ? (int) $validated['uploader'] : null;
        $visibility = $validated['visibility'] ?? null;
        $downloads = $validated['downloads'] ?? null;
        $roleId = isset($validated['role']) ? (int) $validated['role'] : null;
        $version = $validated['version'] ?? null;
        $expired = $request->boolean('expired');

        // A search term or any filter switches to a flat view across the
        // whole visible library; otherwise it's folder browsing. Every
        // filter here is a property of a *file*, so in flat mode the folder
        // sequence stays empty unless there is a search term to match names
        // against -- which is what the existing branch below already does.
        $flat = $searching || $categoryId !== null || $expired
            || $uploaderId !== null || $visibility !== null
            || $downloads !== null || $roleId !== null || $version !== null;

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
                // The same guard /api/v1/files puts on `uploaded_by`, and it
                // is needed for the same reason. A filter is a question, and
                // this one asks "did user N put anything into my library".
                // fileRow() already withholds an uploader's name from a
                // viewer who may not identify them -- so answering this
                // plainly would hand back, as a row count, precisely the
                // identity the row itself is redacting. An id this caller
                // may not identify matches nothing, which is
                // indistinguishable from someone who has uploaded nothing.
                ->when($uploaderId !== null && ! $this->identity->permitsClientId($user, $uploaderId),
                    fn (Builder $q) => $q->whereRaw('1 = 0'))
                ->when($uploaderId !== null, fn (Builder $q) => $q->where('uploaded_by', $uploaderId))
                ->when($roleId !== null, fn (Builder $q) => $q
                    ->whereHas('uploader', fn (Builder $u) => $u->where('role_id', $roleId)))
                // has/doesn't-have rather than a comparison on the
                // withCount alias: an aggregate cannot be filtered in a
                // WHERE, and `downloads_count = 0` in a HAVING would be
                // applied after the pagination slice above.
                ->when($downloads === 'none', fn (Builder $q) => $q->whereDoesntHave('downloads'))
                ->when($downloads === 'any', fn (Builder $q) => $q->whereHas('downloads'))
                ->when($version === 'current', fn (Builder $q) => $q->whereDoesntHave('nextVersion'))
                ->when($version === 'outdated', fn (Builder $q) => $q->whereHas('nextVersion'))
                ->when($visibility !== null, fn (Builder $q) => $this->constrainVisibility($q, $visibility === 'public'))
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
                'uploader' => $uploaderId,
                'visibility' => $visibility,
                'downloads' => $downloads,
                'role' => $roleId,
                'version' => $version,
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

        // Two queries for the whole page, not one per row. `distinct` on an
        // indexed foreign key rather than a join, because all this needs is
        // the set of ids -- the names come back with the roles in one go.
        $uploaders = User::query()
            ->whereIn('id', $this->scope->files($user)->whereNotNull('uploaded_by')->distinct()->pluck('uploaded_by'))
            ->with('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);

        return Inertia::render('files/index', [
            'folder' => $current === null ? null : ['id' => $current->id, 'name' => $current->name],
            'breadcrumb' => $flat ? [] : $this->breadcrumbs->for($current),
            'folders' => $folderRows->map(fn (Folder $folder): array => $this->folderRow($user, $folder))->all(),
            'files' => $fileRows->map(fn (File $file): array => $this->fileRow($user, $file, $commentCounts, $pendingCounts, $versions))->all(),
            'pagination' => Pagination::meta($sliced['paginator']),
            'search' => $search,
            'searching' => $flat,
            'category' => $categoryId,
            'uploader' => $uploaderId,
            'visibility' => $visibility,
            'downloads' => $downloads,
            'role' => $roleId,
            'version' => $version,
            'expired' => $expired,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color'])
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])->all(),
            // Narrowed like every other folder listing on this screen: an
            // unscoped staff member gets the whole tree, a client-scoped
            // one only their own. Unfiltered this handed a scoped staffer
            // every folder name and id on the installation.
            'folder_options' => $this->scope->folders($user)->orderBy('path')->orderBy('name')->get()
                ->map(fn (Folder $folder): array => ['id' => $folder->id, 'name' => $folder->name])->all(),
            // Only people who actually uploaded something *this viewer can
            // see*, and their roles taken from the same set. Narrowed for
            // the reason folder_options directly above is: an unscoped list
            // would hand a client-scoped staffer the name and id of every
            // account on the installation, through a filter dropdown.
            // Through filterClientPairs, so the dropdown never offers a name
            // this viewer may not be told -- the same rule fileRow() applies
            // to the uploader on each row, asked once for the whole list.
            'uploader_options' => $this->identity->filterClientPairs($user, array_values($uploaders
                ->map(fn (User $uploader): array => ['id' => $uploader->id, 'name' => $uploader->name])->all())),
            'role_options' => $uploaders->pluck('role')->filter()->unique('id')->sortBy('name')->values()
                ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])->all(),
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
            // The whole block goes, not just the name: type and role
            // describe the same person, and "a client uploaded this" on a
            // row whose uploader is off this viewer's roster narrows who
            // it could be just as effectively as naming them.
            'uploader' => ($file->uploader !== null && $this->identity->permits($user, $file->uploader)) ? [
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
            'parent_id' => Rules::folderId(),
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
            'parent_id' => Rules::folderId(),
        ]);

        $user = $request->user();
        $newParent = $this->resolveParent($user, $validated['parent_id'] ?? null);

        // A folder carries its contents with it, and a folder inside a
        // public one is public — isEffectivelyPublic() reads the whole
        // ancestry. So dropping a private folder into a public parent
        // publishes every file in its subtree at once, which is the same
        // act the upload path refuses without `upload_public`. The flag on
        // this screen is already guarded (update() above leaves public
        // state alone without the permission); the placement was not
        // (GHSA-rxf8-wh8v-jm9j).
        if ($user !== null) {
            abort_unless(Folder::uploadableBy($user, $newParent), 403);
        }

        $this->folders->move($folder, $newParent);

        $this->activity->log(Action::FolderMoved, subject: $folder);

        return back();
    }

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $viewer = $request->user();
        assert($viewer !== null);

        // Deleting a folder cascades to every file in its subtree, and a
        // File's `deleted` hook removes the bytes from disk — there is no
        // restore. Authorizing the folder is not authorizing its contents:
        // FilePolicy::delete asks for `delete_others_files` on somebody
        // else's upload, and for the library boundary on top of that, and
        // neither question is asked anywhere on this path.
        //
        // MyFoldersController::destroy already refuses for the client half
        // of the same cascade, in the same words. This is the staff half.
        $blocked = $this->undeletableFileCount($viewer, $folder);

        if ($blocked > 0) {
            return back()->with('error', trans_choice(
                'This folder cannot be deleted: it holds :count file you may not delete.|This folder cannot be deleted: it holds :count files you may not delete.',
                $blocked,
                ['count' => (string) $blocked],
            ));
        }

        $name = $folder->name;
        $parentId = $folder->parent_id;

        $this->folders->delete($folder);

        $this->activity->log(Action::FolderDeleted, context: ['name' => $name]);

        return redirect()->route('files.index', $parentId !== null ? ['folder' => $parentId] : [])->with('success', __('Folder deleted.'));
    }

    /**
     * How many files in this folder's subtree the viewer may not delete.
     *
     * Asked as one count rather than FilePolicy::delete per file: a folder
     * can hold thousands, Gate resolves a fresh policy for every check, and
     * a per-row policy check on a listing is the cost 0a8b609e went to
     * some trouble to remove. The two halves of FilePolicy::delete are
     * expressible in SQL — the permission half is constant for this
     * viewer, and the library half is the query StaffLibraryScope already
     * memoises per request.
     *
     * Somebody holding both delete permissions and no library scope can
     * delete anything in the subtree by construction, so they never pay for
     * the query at all.
     */
    private function undeletableFileCount(User $viewer, Folder $folder): int
    {
        $mayDeleteOwn = $viewer->can('delete_files');
        $mayDeleteOthers = $viewer->can('delete_others_files');
        $scoped = $viewer->isClientScoped();

        if ($mayDeleteOwn && $mayDeleteOthers && ! $scoped) {
            return 0;
        }

        return File::query()
            ->whereIn('folder_id', $folder->subtreeFolderIds())
            ->where(function (Builder $outer) use ($viewer, $mayDeleteOwn, $mayDeleteOthers, $scoped): void {
                if (! $mayDeleteOwn) {
                    $outer->orWhere('uploaded_by', $viewer->id);
                }

                if (! $mayDeleteOthers) {
                    $outer->orWhere(fn (Builder $others): Builder => $others
                        ->whereNull('uploaded_by')->orWhere('uploaded_by', '!=', $viewer->id));
                }

                if ($scoped) {
                    $outer->orWhereNotIn('id', $this->scope->files($viewer)->select('id'));
                }
            })
            ->count();
    }

    private function resolveParent(?User $user, ?int $parentId): ?Folder
    {
        if ($user === null || $parentId === null) {
            return null;
        }

        return $this->scope->folders($user)->findOrFail($parentId);
    }

    /**
     * Narrow to files that are, or are not, publicly reachable.
     *
     * "Public" here means what the row's own badge means --
     * File::isEffectivelyPublic(), the file's own flag *or* its folder
     * sitting anywhere in a public folder's live subtree. Filtering on the
     * `public` column alone would have hidden files the same screen visibly
     * labels Public, which is a filter that argues with the list it filters.
     *
     * The folder half is resolved once into a list of ids rather than as a
     * correlated subquery, because Folder::scopePubliclyVisible() already
     * expresses the subtree rule (a LIKE per public folder) and is the only
     * place that rule should live.
     *
     * @param  Builder<File>  $query
     */
    private function constrainVisibility(Builder $query, bool $public): void
    {
        $publicFolderIds = Folder::query()->publiclyVisible()->pluck('id')->all();

        if ($public) {
            $query->where(fn (Builder $w) => $w
                ->where('public', true)
                ->orWhereIn('folder_id', $publicFolderIds));

            return;
        }

        // The null branch is not tidiness: `folder_id NOT IN (...)` is never
        // true for a NULL folder_id, so a file at the library root would
        // otherwise be neither public nor private and vanish from both
        // halves of the filter. Proved by removing it -- the private half
        // then returned nothing at all.
        $query->where('public', false)->where(fn (Builder $w) => $w
            ->whereNull('folder_id')
            ->orWhereNotIn('folder_id', $publicFolderIds));
    }
}
