<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use Illuminate\Support\Collection;

/**
 * Who a folder reaches: everybody it, or any folder above it, is shared
 * with. Folder::scopeVisibleToClient gives a client every folder under one
 * shared with them, and File::scopeVisibleToClient every file in those, so
 * putting something in a folder shares it with that whole list.
 *
 * Two questions are asked of it.
 *
 *  - Whether a client-scoped staff member may place something there. They
 *    may share only with their own clients (ShareTargets), and placing a
 *    file in a folder that also reaches somebody else's client was sharing
 *    it with them by the side door. Version linking already refuses the
 *    same move (FileVersions::guardRecipientsAssignable).
 *  - Whether a client may make or fill a folder there. A client's folder
 *    inside a folder staff shared with a group, or with several clients,
 *    showed everything the client put in it to all of them. Clients do not
 *    see each other.
 *
 * And the reverse of the first: taking something out of a folder takes it
 * away from everybody the folder reached and the new place does not. That
 * is unsharing, and a client-scoped staff member may not unshare from
 * somebody else's client (ResolvesShareTargets::guardAssignable refuses
 * the direct route), so they may not move content out from under them
 * either.
 */
class FolderReach
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    /**
     * @return Collection<int, FolderAssignment>
     */
    public function assignments(Folder $folder): Collection
    {
        return FolderAssignment::query()
            ->whereIn('folder_id', [$folder->id, ...$folder->ancestorIds()])
            ->get();
    }

    /**
     * Whether everybody the folder reaches is somebody $staff may share
     * with. Always true for staff who are not client-scoped.
     */
    public function withinRosterOf(User $staff, Folder $folder): bool
    {
        $clientIds = $this->scope->assignableClientIds($staff);

        if ($clientIds === null) {
            return true;
        }

        $groupIds = $this->scope->assignableGroupIds($staff) ?? [];
        $groupMorph = (new Group)->getMorphClass();

        foreach ($this->assignments($folder) as $assignment) {
            $id = (int) $assignment->assignable_id;
            $allowed = $assignment->assignable_type === $groupMorph
                ? in_array($id, $groupIds, true)
                : in_array($id, $clientIds, true);

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Everybody who reaches what sits in $from and would not reach it in
     * $to. Null means the top level, which reaches nobody.
     *
     * @return Collection<int, FolderAssignment>
     */
    public function lostBetween(?Folder $from, ?Folder $to): Collection
    {
        if ($from === null) {
            return new Collection;
        }

        $key = fn (FolderAssignment $assignment): string => $assignment->assignable_type.':'.$assignment->assignable_id;
        $kept = $to === null ? [] : $this->assignments($to)->map($key)->all();

        return $this->assignments($from)
            ->reject(fn (FolderAssignment $assignment): bool => in_array($key($assignment), $kept, true))
            ->values();
    }

    /**
     * Whether moving something from $from to $to takes it away from
     * somebody $staff may not unshare from. Always false for staff who are
     * not client-scoped.
     */
    public function strandsBeyondRosterOf(User $staff, ?Folder $from, ?Folder $to): bool
    {
        $clientIds = $this->scope->assignableClientIds($staff);

        if ($clientIds === null) {
            return false;
        }

        $groupIds = $this->scope->assignableGroupIds($staff) ?? [];
        $groupMorph = (new Group)->getMorphClass();

        foreach ($this->lostBetween($from, $to) as $assignment) {
            $id = (int) $assignment->assignable_id;
            $allowed = $assignment->assignable_type === $groupMorph
                ? in_array($id, $groupIds, true)
                : in_array($id, $clientIds, true);

            if (! $allowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why $staff may not move a file from $from to $to, or null when they
     * may.
     */
    public function fileMoveRefusal(User $staff, ?Folder $from, ?Folder $to): ?string
    {
        return $this->strandsBeyondRosterOf($staff, $from, $to)
            ? (string) __('This cannot be moved out of there: the folder it is in is shared with people outside your clients, and they would lose it.')
            : null;
    }

    /**
     * Whether the folder reaches anybody besides $client: a group, or
     * another client.
     */
    public function reachesOthersThan(User $client, Folder $folder): bool
    {
        $groupMorph = (new Group)->getMorphClass();

        foreach ($this->assignments($folder) as $assignment) {
            if ($assignment->assignable_type === $groupMorph || (int) $assignment->assignable_id !== $client->id) {
                return true;
            }
        }

        return false;
    }
}
