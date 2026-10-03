<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Support\PollingQuery;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Folders\FolderTrails;
use App\Modules\Files\Folders\UndeletableFiles;
use App\Modules\Files\Http\Resources\Api\FolderResource;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Support\Rules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The staff library's folders.
 *
 * Which folders a token sees is the same question the library screen
 * answers, so a staff member limited to their assigned clients gets exactly
 * the folders they see on the web. Every write goes through the same
 * service, policy and placement rule as the web screen.
 */
class FoldersController extends Controller
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly PollingQuery $polling,
        private readonly FolderService $folders,
        private readonly FolderTrails $trails,
        private readonly UndeletableFiles $undeletable,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * List folders.
     *
     * Cursor paginated, like every list. Pass `updated_since` to poll for
     * folders created, renamed or moved since a point in time. `parent_id`
     * lists the folders directly inside one folder, and `top_level=1` the
     * folders at the top of the library.
     *
     * Moving a folder updates the folder itself and every folder under it,
     * so a poll sees the whole moved subtree.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user !== null);

        $filters = $request->validate($this->polling->rules() + [
            'parent_id' => ['nullable', 'integer'],
            'top_level' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = $this->scope->folders($user);

        if (($filters['parent_id'] ?? null) !== null) {
            $query->where('folders.parent_id', (int) $filters['parent_id']);
        }

        if ($request->boolean('top_level')) {
            $query->whereNull('folders.parent_id');
        }

        if (($filters['search'] ?? null) !== null) {
            $query->where('folders.name', 'like', '%'.$filters['search'].'%');
        }

        $page = $this->polling->paginate($request, $query, 'folders');

        /** @var Collection<int, Folder> $items */
        $items = collect($page->items());
        $this->attachTrails($items, $user);

        return FolderResource::collection($page);
    }

    /**
     * Show a folder, with the clients and groups it is shared with.
     */
    public function show(Request $request, Folder $folder): FolderResource
    {
        Gate::authorize('view', $folder);

        return $this->resource($folder, $request->user());
    }

    /**
     * Create a folder.
     *
     * At the top of the library, or inside `parent_id`. Requires the
     * `create_own_folders` ability, and `upload` with it.
     *
     * If a folder with the same name already exists in the same place, that
     * folder is returned with a 200 instead of a second one being made, so
     * retrying a request is safe. A new folder answers 201.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        // The same pair FoldersController::store asks on the web: a folder
        // nobody can put anything into is no use.
        abort_unless($user->can('create_own_folders') && $user->can('upload'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => Rules::folderId(),
        ]);

        $parent = $this->resolveParent($user, $validated['parent_id'] ?? null);

        // A folder inside a public one is public, so creating one there is
        // placing content into it (Folder::uploadableBy).
        abort_unless(Folder::uploadableBy($user, $parent), 403);

        $existing = $this->scope->folders($user)
            ->where('folders.parent_id', $parent?->id)
            ->where('folders.name', $validated['name'])
            ->orderBy('folders.id')
            ->first();

        if ($existing instanceof Folder) {
            return $this->resource($existing, $user)->response()->setStatusCode(200);
        }

        $folder = $this->folders->create($validated['name'], $parent);

        $this->activity->log(Action::FolderCreated, subject: $folder);

        return $this->resource($folder, $user)->response()->setStatusCode(201);
    }

    /**
     * Rename or move a folder.
     *
     * Only the fields you send change. `parent_id: null` moves the folder to
     * the top of the library. A folder moves with everything inside it, and
     * cannot be moved into itself or one of its own subfolders.
     */
    public function update(Request $request, Folder $folder): FolderResource
    {
        $user = $request->user();
        assert($user !== null);

        Gate::authorize('update', $folder);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', ...Rules::folderId()],
        ]);

        if (array_key_exists('name', $validated) && $validated['name'] !== $folder->name) {
            $folder->update(['name' => $validated['name']]);
            $this->activity->log(Action::FolderRenamed, subject: $folder);
        }

        if (array_key_exists('parent_id', $validated)) {
            $newParentId = $validated['parent_id'] === null ? null : (int) $validated['parent_id'];

            if ($newParentId !== $folder->parent_id) {
                $newParent = $this->resolveParent($user, $newParentId);

                // Dropping a folder into a public parent publishes its whole
                // subtree, the act FoldersController::move refuses without
                // `upload_public` (GHSA-rxf8-wh8v-jm9j).
                abort_unless(Folder::uploadableBy($user, $newParent), 403);

                $this->folders->move($folder, $newParent);
                $this->activity->log(Action::FolderMoved, subject: $folder);
            }
        }

        return $this->resource($folder->fresh() ?? $folder, $user);
    }

    /**
     * Delete a folder.
     *
     * An empty folder is deleted straight away. A folder holding files or
     * other folders answers 409 unless you send
     * `content_action=cascade_delete`, which deletes the folder, every folder
     * under it and every file inside them, as the web screen does. There is
     * no restore.
     *
     * A cascade is refused with 403 if the folder holds any file this token
     * may not delete itself.
     */
    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        Gate::authorize('delete', $folder);

        $validated = $request->validate([
            'content_action' => ['nullable', Rule::in(['cascade_delete'])],
        ]);

        $subtree = $folder->subtreeFolderIds();
        $hasContent = count($subtree) > 1
            || File::query()->whereIn('folder_id', $subtree)->exists();

        // A sync job with a bug in it must not be one request away from
        // emptying a client's folder: the cascade has to be asked for.
        abort_if(
            $hasContent && ($validated['content_action'] ?? null) !== 'cascade_delete',
            409,
            __('This folder is not empty. Send content_action=cascade_delete to delete it with everything inside it.'),
        );

        $blocked = $this->undeletable->count($user, $folder);

        abort_if($blocked > 0, 403, trans_choice(
            'This folder cannot be deleted: it holds :count file you may not delete.|This folder cannot be deleted: it holds :count files you may not delete.',
            $blocked,
            ['count' => (string) $blocked],
        ));

        $name = $folder->name;

        $this->folders->delete($folder);

        $this->activity->log(Action::FolderDeleted, context: ['name' => $name]);

        return response()->json(status: 204);
    }

    private function resource(Folder $folder, ?User $user): FolderResource
    {
        $folder->load('assignments.assignable');

        if ($user !== null) {
            $this->attachTrails(collect([$folder]), $user);
        }

        return new FolderResource($folder);
    }

    /**
     * @param  Collection<int, Folder>  $folders
     */
    private function attachTrails(Collection $folders, User $user): void
    {
        $trails = $this->trails->ancestors($folders, $user);

        foreach ($folders as $folder) {
            $folder->setRelation('trail', collect($trails[$folder->id] ?? []));
        }
    }

    /**
     * The parent must be a folder this caller's library shows them — the
     * same lookup the web screen makes, answering 404 otherwise.
     */
    private function resolveParent(User $user, ?int $parentId): ?Folder
    {
        if ($parentId === null) {
            return null;
        }

        /** @var Builder<Folder> $folders */
        $folders = $this->scope->folders($user);

        return $folders->findOrFail($parentId);
    }
}
