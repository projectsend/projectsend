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
