<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many files in a folder's subtree a staff member may not delete.
 *
 * Deleting a folder cascades to every file in its subtree, and a File's
 * `deleted` hook removes the bytes from disk — there is no restore.
 * Authorizing the folder is not authorizing its contents: FilePolicy::delete
 * asks for `delete_others_files` on somebody else's upload, and for the
 * library boundary on top of that, and neither question is asked by
 * FolderPolicy. Every staff path that deletes a folder asks this first, so
 * the web screen and the API cannot disagree about what a cascade may take.
 *
 * Asked as one count rather than FilePolicy::delete per file: a folder can
 * hold thousands, Gate resolves a fresh policy for every check, and a
 * per-row policy check on a listing is the cost 0a8b609e went to some
 * trouble to remove. The two halves of FilePolicy::delete are expressible
 * in SQL — the permission half is constant for this viewer, and the
 * library half is the query StaffLibraryScope already memoises per request.
 *
 * Somebody holding both delete permissions and no library scope can delete
 * anything in the subtree by construction, so they never pay for the query
 * at all.
 *
 * The client half of the same rule is MyFoldersController::destroy.
 */
class UndeletableFiles
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    public function count(User $viewer, Folder $folder): int
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
}
