<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\Folder;

/**
 * Folder ownership rules, mirroring FilePolicy: own vs others' via the
 * v1 permission pairs. Which staff see which folders is the
 * StaffLibraryScope's job — and for client-scoped staff the policy AND's
 * that scope into every action so direct access stays inside the boundary.
 *
 * Clients may create/rename/delete only folders they created themselves
 * (create_own_folders doubles as the single toggle for the whole client
 * folder-management feature, since clients have no edit_files/delete_files
 * equivalent) — see MyFoldersController.
 */
class FolderPolicy
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    public function view(User $user, Folder $folder): bool
    {
        if ($user->isStaff()) {
            return ($user->can('upload') || $user->can('edit_files') || $user->can('edit_others_files'))
                && $this->scope->allowsFolder($user, $folder);
        }

        return Folder::query()->whereKey($folder->id)->visibleToClient($user)->exists();
    }

    public function update(User $user, Folder $folder): bool
    {
        if (! $user->isStaff()) {
            // A client owns their home folder -- created_by is them, which
            // is how they can see it at all -- so ownership alone would let
            // them rename it. It is structure rather than something of
            // theirs to arrange: its name follows the account, and the
            // administrator reading /files relies on that. Renaming it is
            // refused rather than allowed and then silently overwritten the
            // next time the account is edited.
            if ($folder->isHome()) {
                return false;
            }

            return $folder->isOwnedBy($user) && $user->can('create_own_folders');
        }

        $permitted = $folder->isOwnedBy($user) ? $user->can('edit_files') : $user->can('edit_others_files');

        return $permitted && $this->scope->allowsFolder($user, $folder);
    }

    public function delete(User $user, Folder $folder): bool
    {
        // Nobody deletes a home folder from a folder screen, staff
        // included. Deleting one cascades over everything the client has,
        // and it would leave their portal pointing at a folder that is not
        // there -- an account still gets erased through the erasure flow,
        // which is where destroying somebody's content is the declared
        // intent rather than a side effect of tidying a tree.
        if ($folder->isHome()) {
            return false;
        }

        if (! $user->isStaff()) {
            return $folder->isOwnedBy($user) && $user->can('create_own_folders');
        }

        $permitted = $folder->isOwnedBy($user) ? $user->can('delete_files') : $user->can('delete_others_files');

        return $permitted && $this->scope->allowsFolder($user, $folder);
    }
}
