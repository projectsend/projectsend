<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Folders\ClientHomeFolders;
use App\Modules\Files\Folders\FolderCreationRules;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Client-facing folder creation/rename/delete — the client portal's
 * counterpart to FoldersController, gated by create_own_folders (see that
 * permission's docblock) rather than staff middleware. A client may only
 * ever nest a new folder inside one already visible to them
 * (Folder::scopeVisibleToClient), so a created folder is automatically
 * visible to them and to any staff with access to their content — see
 * StaffLibraryScope::folders(), which already ORs in visibleToClient()
 * per assigned client.
 */
class MyFoldersController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
        private readonly ActivityLogger $activity,
        private readonly ClientHomeFolders $homeFolders,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => Rules::folderId(),
        ]);

        // Only a folder the client can see may be named as the parent.
        $parent = ($validated['parent_id'] ?? null) !== null
            ? Folder::query()->visibleToClient($client)->whereKey($validated['parent_id'])->firstOrFail()
            : null;

        // No parent named means the top of what this client sees -- which,
        // where the installation gives them a home, is inside it rather
        // than at the root of the library. Without this a client creating a
        // folder would put it beside the staff folders, which is precisely
        // the mess the home folder exists to end.
        $parent ??= $this->homeFolders->for($client);

        // Public ground, folders that reach other clients, and the
        // create_own_folders + upload pair: see FolderCreationRules, shared
        // with the staff library and folder uploads.
        app(FolderCreationRules::class)->authorize($client, $parent);

        $folder = $this->folders->create($validated['name'], $parent);

        $this->activity->log(Action::FolderCreated, subject: $folder);

        return back()->with('success', __('Folder created.'));
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $folder->update(['name' => $validated['name']]);

        $this->activity->log(Action::FolderRenamed, subject: $folder);

        return back()->with('success', __('Folder renamed.'));
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $client = auth()->user();
        assert($client !== null);

        // Deleting a folder cascades to every file in its subtree, and a
        // File's `deleted` hook removes the bytes from disk — there is no
        // restore. Owning the folder is not authority over content someone
        // else put in it: staff routinely upload into a client's own folder,
        // and the client can see those files without being able to delete
        // them individually (FilePolicy::delete denies clients outright).
        // Refuse rather than silently destroy them.
        $foreignFiles = File::query()
            ->whereIn('folder_id', $folder->subtreeFolderIds())
            ->where(fn ($query) => $query->whereNull('uploaded_by')->orWhere('uploaded_by', '!=', $client->id))
            ->count();

        if ($foreignFiles > 0) {
            return back()->with('error', trans_choice(
                'This folder cannot be deleted: it holds :count file you did not upload.|This folder cannot be deleted: it holds :count files you did not upload.',
                $foreignFiles,
                ['count' => (string) $foreignFiles],
            ));
        }

        // The same goes for folders inside it that somebody else made:
        // staff organise a client's folder too, and a folder carries its
        // own sharing and public settings even while it is empty.
        $foreignFolders = Folder::query()
            ->whereIn('id', $folder->subtreeFolderIds())
            ->where(fn ($query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $client->id))
            ->exists();

        if ($foreignFolders) {
            return back()->with('error', __('This folder cannot be deleted: it holds folders you did not create.'));
        }

        // Their own uploads go with the folder too, so deleting one that
        // holds any is deleting files, and needs the permission that
        // deleting a file one at a time needs (MyFilesController::destroy).
        // A role without delete_files could otherwise delete everything it
        // uploaded by putting it in a folder first.
        if (! $client->can('delete_files') && File::query()->whereIn('folder_id', $folder->subtreeFolderIds())->exists()) {
            return back()->with('error', __('This folder cannot be deleted while it holds files.'));
        }

        $name = $folder->name;
        $parentId = $folder->parent_id;

        $this->folders->delete($folder);

        $this->activity->log(Action::FolderDeleted, context: ['name' => $name]);

        return redirect()->route('my-files.index', $parentId !== null ? ['folder' => $parentId] : [])
            ->with('success', __('Folder deleted.'));
    }
}
