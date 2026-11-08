<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Folders\ClientHomeFolders;
use App\Modules\Files\Folders\FolderReach;
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
        // Creating one requires upload too — an empty folder a client can
        // never put anything in isn't useful on its own (renaming/deleting
        // an existing one, below, doesn't carry this requirement: it stays
        // theirs to manage even if upload is later revoked).
        abort_unless($client->can('create_own_folders') && $client->can('upload'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => Rules::folderId(),
        ]);

        $parent = null;
        if (($validated['parent_id'] ?? null) !== null) {
            $parent = Folder::query()->visibleToClient($client)->whereKey($validated['parent_id'])->firstOrFail();

            // A folder made inside a public one is public too, and so is
            // its name on the public listing. So inside public ground the
            // client must be allowed to place content there
            // (GHSA-65c7-3fpf-q89h). Elsewhere a client may nest a folder in
            // a private folder staff shared with them alone, even though
            // they cannot upload into that folder itself; not in one that
            // reaches anybody else (below).
            if ($parent->isEffectivelyPublic()) {
                abort_unless(Folder::uploadableBy($client, $parent), 403);
            }
        }

        // No parent named means the top of what this client sees -- which,
        // where the installation gives them a home, is inside it rather
        // than at the root of the library. Without this a client creating a
        // folder would put it beside the staff folders, which is precisely
        // the mess the home folder exists to end.
        $parent ??= $this->homeFolders->for($client);

        // Not inside a folder that reaches anybody else: a folder staff
        // shared with a group, or with several clients, shows everything
        // in it to all of them, so a client's folder there was a way to
        // hand files to other clients. Clients do not see each other.
        if ($parent !== null && ! $parent->isEffectivelyPublic()) {
            abort_if(app(FolderReach::class)->reachesOthersThan($client, $parent), 403);
        }

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
