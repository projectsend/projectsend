<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Files\Models\File;
use Illuminate\Database\Eloquent\Builder;

/**
 * FilePolicy::view() expressed as a query instead of a per-model check —
 * the same rules, evaluated in SQL so a caller can ask "every file this
 * user may see" without loading candidates first.
 *
 * This exists because a folder is not a bag of uniformly-visible files: a
 * user may hold a folder while individual files inside it are not theirs
 * to read (a client's expired file is the live case — see
 * File::scopeVisibleToClient, which ends in notExpired()). Anything that
 * expands a folder into its contents must therefore re-derive visibility
 * per file rather than inherit it from the folder, or the folder becomes a
 * way around the per-file rule. BuildZipDownloadJob is the first such
 * caller; any future bulk operation over a subtree is the next.
 *
 * Keep this in lockstep with FilePolicy::view(). The policy stays the
 * authority for a single known file; this is its set-shaped twin.
 */
class ViewableFileScope
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    /**
     * @return Builder<File>
     */
    public function for(User $user): Builder
    {
        if (! $user->isStaff()) {
            return File::query()->visibleToClient($user);
        }

        if (! $this->permitsAnyFile($user)) {
            return File::query()->whereRaw('1 = 0');
        }

        return $this->scope->files($user);
    }

    /**
     * Whether a staff member holds any of the three keys that open file
     * reading at all — the permission half of FilePolicy::view()'s staff
     * branch, named once because more than one module has to ask it.
     *
     * It is a property of the viewer rather than of a row, so it either
     * opens the whole scope or closes it entirely. That is also why a
     * query narrowed by StaffLibraryScope alone is only half the check:
     * the library says *which* files, this says *whether any*.
     */
    public function permitsAnyFile(User $user): bool
    {
        return $user->can('upload') || $user->can('edit_files') || $user->can('edit_others_files');
    }
}
