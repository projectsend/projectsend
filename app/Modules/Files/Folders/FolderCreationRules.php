<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Files\Models\Folder;

/**
 * Whether someone may create a folder inside another one.
 *
 * One answer for every way a folder gets made: the New folder button on the
 * staff library (FoldersController) and in the portal (MyFoldersController),
 * and uploading a folder with subfolders (UploadFolderPath). Written out in
 * each, the rules drifted once already — GHSA-65c7-3fpf-q89h was the portal
 * missing a check the library had.
 *
 * The caller has already made sure the person can see `$parent`. This
 * answers only whether they may put a new folder in it. It aborts with 403
 * rather than returning, so no caller can forget to act on the answer.
 */
final class FolderCreationRules
{
    public function __construct(
        private readonly FolderReach $reach,
    ) {}

    public function authorize(User $user, ?Folder $parent): void
    {
        // Upload too, not only create_own_folders: an empty folder nobody
        // can put anything in is no use to anyone.
        abort_unless($user->can('create_own_folders') && $user->can('upload'), 403);

        // A folder inside a public one is public, so creating it there is
        // placing content into a public folder: the question every other
        // write of a parent asks (Folder::uploadableBy).
        if ($user->isStaff()) {
            abort_unless(Folder::uploadableBy($user, $parent), 403);

            return;
        }

        if ($parent === null) {
            return;
        }

        // A client may nest a folder in a private folder staff shared with
        // them alone, even one they cannot upload into themselves. Not in
        // public ground they may not place content in, and not in a folder
        // that reaches anybody else: a group, or several clients, would see
        // whatever went into it, and clients do not see each other.
        if ($parent->isEffectivelyPublic()) {
            abort_unless(Folder::uploadableBy($user, $parent), 403);
        } else {
            abort_if($this->reach->reachesOthersThan($user, $parent), 403);
        }
    }
}
