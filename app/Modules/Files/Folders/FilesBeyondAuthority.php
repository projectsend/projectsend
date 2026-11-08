<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many files in a folder's subtree a staff member could not act on
 * one by one, for a FilePolicy own/others permission pair.
 *
 * Acting on a folder acts on everything in it. Deleting one cascades to its
 * files (UndeletableFiles asks the delete pair). Sharing one, publishing
 * one, or moving one under a shared or public parent hands out every file
 * inside: the edit pair, the one FilePolicy::update asks before a single
 * file is shared or published. Authorizing the folder is not authorizing
 * its contents, so a folder owner without edit_others_files could share a
 * team folder and with it every colleague's file in it, and a client-scoped
 * staff member could hand a client files from outside their library that
 * an administrator had dropped into their folder.
 *
 * Asked as one count rather than a policy check per file: a folder can hold
 * thousands. Both halves of the policy are expressible in SQL — the
 * permission half is constant for this viewer, and the library half is the
 * query StaffLibraryScope already memoises per request. Somebody holding
 * both permissions and no library scope never pays for the query at all.
 */
class FilesBeyondAuthority
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    public function count(User $viewer, Folder $folder, string $ownPermission, string $othersPermission): int
    {
        $mayOwn = $viewer->can($ownPermission);
        $mayOthers = $viewer->can($othersPermission);
        $scoped = $viewer->isClientScoped();

        if ($mayOwn && $mayOthers && ! $scoped) {
            return 0;
        }

        return File::query()
            ->whereIn('folder_id', $folder->subtreeFolderIds())
            ->where(function (Builder $outer) use ($viewer, $mayOwn, $mayOthers, $scoped): void {
                if (! $mayOwn) {
                    $outer->orWhere('uploaded_by', $viewer->id);
                }

                if (! $mayOthers) {
                    $outer->orWhere(fn (Builder $others): Builder => $others
                        ->whereNull('uploaded_by')->orWhere('uploaded_by', '!=', $viewer->id));
                }

                if ($scoped) {
                    $outer->orWhereNotIn('id', $this->scope->files($viewer)->select('id'));
                }
            })
            ->count();
    }

    /** Files in the subtree this viewer could not share or publish one by one. */
    public function unshareable(User $viewer, Folder $folder): int
    {
        return $this->count($viewer, $folder, 'edit_files', 'edit_others_files');
    }

    /**
     * Why $viewer may not hand out $folder's contents — share it, publish
     * it, or move it under a shared or public parent — or null when they
     * may. $act is 'share', 'publish' or 'move'.
     */
    public function refusal(User $viewer, Folder $folder, string $act): ?string
    {
        $blocked = $this->unshareable($viewer, $folder);

        if ($blocked === 0) {
            return null;
        }

        $replace = ['count' => (string) $blocked];

        return match ($act) {
            'publish' => trans_choice('This folder cannot be made public: it holds :count file you may not share.|This folder cannot be made public: it holds :count files you may not share.', $blocked, $replace),
            'move' => trans_choice('This folder cannot go there: that folder is shared or public, and this one holds :count file you may not share.|This folder cannot go there: that folder is shared or public, and this one holds :count files you may not share.', $blocked, $replace),
            default => trans_choice('This folder cannot be shared: it holds :count file you may not share.|This folder cannot be shared: it holds :count files you may not share.', $blocked, $replace),
        };
    }

    /**
     * Whether placing something under $parent hands it out: the parent is
     * public, or reaches somebody. Moving a folder elsewhere is filing.
     */
    public function exposes(?Folder $parent): bool
    {
        return $parent !== null
            && ($parent->isEffectivelyPublic() || app(FolderReach::class)->assignments($parent)->isNotEmpty());
    }
}
