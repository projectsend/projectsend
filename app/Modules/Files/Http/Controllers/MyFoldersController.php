<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
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
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $parent = null;
        if (($validated['parent_id'] ?? null) !== null) {
            $parent = Folder::query()->visibleToClient($client)->whereKey($validated['parent_id'])->firstOrFail();
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

        $name = $folder->name;
        $parentId = $folder->parent_id;

        $this->folders->delete($folder);

        $this->activity->log(Action::FolderDeleted, context: ['name' => $name]);

        return redirect()->route('my-files.index', $parentId !== null ? ['folder' => $parentId] : [])
            ->with('success', __('Folder deleted.'));
    }
}
