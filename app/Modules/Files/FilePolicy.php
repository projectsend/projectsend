<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;

/**
 * Ownership rules as policy methods (brief §6.13): "own" versus
 * "others'" files map onto the v1 permission pairs. Clients may only
 * view/download what is assigned to them, directly or via a group, and may
 * edit or delete only what they uploaded themselves. For client-scoped
 * staff, every action is additionally gated by the StaffLibraryScope, so
 * direct access can't reach out-of-scope files.
 *
 * Every method here branches on isStaff() before it reaches the scope.
 * That is not stylistic: StaffLibraryScope answers "is this *restricted*
 * staff member allowed?", and its "no restriction" answer is `true`. A
 * client falling through to it is handed the whole library. See update().
 */
class FilePolicy
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    public function view(User $user, File $file): bool
    {
        if ($user->isStaff()) {
            return ($user->can('upload') || $user->can('edit_files') || $user->can('edit_others_files'))
                && $this->scope->allowsFile($user, $file);
        }

        return File::query()->whereKey($file->id)->visibleToClient($user)->exists();
    }

    public function update(User $user, File $file): bool
    {
        // A client edits what they uploaded and nothing else. Deliberately
        // its own branch rather than a shared one, because the staff branch
        // below is unsafe for a client in two ways at once.
        //
        // First, `edit_others_files` must never be reachable here. It is a
        // staff key by construction: a client has no "others' files" they
        // could hold a legitimate claim over, only files somebody shared
        // with them, and being shown a file is not being given it. Granting
        // that key to the Client role does nothing, and a test pins that.
        //
        // Second, and the trap: StaffLibraryScope::allowsFile() returns
        // true outright for anyone who is not client-*scoped* staff —
        // User::isClientScoped() is `isStaff() && role->client_scoped`, so
        // it is false for every client. That predicate means "this staff
        // member is unrestricted", and a client reaching it would inherit
        // "unrestricted" over the whole library. Nothing here may touch the
        // staff scope.
        if (! $user->isStaff()) {
            return $file->isOwnedBy($user) && $user->can('edit_files');
        }

        $permitted = $file->isOwnedBy($user) ? $user->can('edit_files') : $user->can('edit_others_files');

        return $permitted && $this->scope->allowsFile($user, $file);
    }

    /**
     * May this user use $file as either end of a version link?
     *
     * Checked on BOTH ends by FileVersions::link(), and that is the whole
     * control — the candidate endpoints filter the same way, but a
     * previous_file_id can be posted directly, so filtering the picker is
     * a courtesy and this is the boundary.
     *
     * Staff get the same rule as editing, because linking moves the
     * subject's assignment rows onto the original and so widens the
     * original's audience — a strictly bigger act than reading it.
     *
     * A client gets their OWN UPLOADS ONLY, deliberately not
     * ViewableFileScope: a client can see every file staff shared with
     * them, and since a revision inherits the original's recipients,
     * letting a client name a shared file as their upload's original
     * would hand that upload the entire recipient list of a file they do
     * not own. That is the escalation this method exists to stop.
     */
    public function setVersion(User $user, File $file): bool
    {
        if ($user->isStaff()) {
            return $this->update($user, $file);
        }

        return $file->isOwnedBy($user);
    }

    public function delete(User $user, File $file): bool
    {
        // Their own upload, and only with the key — same two reasons as
        // update() above, `delete_others_files` standing in for
        // `edit_others_files`.
        if (! $user->isStaff()) {
            return $file->isOwnedBy($user) && $user->can('delete_files');
        }

        $permitted = $file->isOwnedBy($user) ? $user->can('delete_files') : $user->can('delete_others_files');

        return $permitted && $this->scope->allowsFile($user, $file);
    }
}
