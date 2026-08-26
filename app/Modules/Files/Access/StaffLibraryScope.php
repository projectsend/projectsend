<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single point that decides which library content a staff member
 * sees. An unscoped staff member sees the whole shared library; a
 * client-scoped one (see User::isClientScoped) sees only the files &
 * folders they created, plus everything belonging to the clients
 * assigned to them.
 *
 * Every staff listing goes through here, and the policies consult
 * allowsFile()/allowsFolder() so direct access (download, details,
 * edit…) respects the same boundary.
 *
 * @method Builder<File> files(User $user)
 * @method Builder<Folder> folders(User $user)
 */
class StaffLibraryScope
{
    /**
     * @return Builder<File>
     */
    public function files(User $user): Builder
    {
        $query = File::query();

        if (! $user->isClientScoped()) {
            return $query;
        }

        // Own uploads ∪ files visible to each assigned client. The
        // per-client visibility is File::scopeVisibleToClient — the single
        // source of truth for client file access — so no rule is duplicated.
        return $query->where(function (Builder $outer) use ($user): void {
            $outer->where('uploaded_by', $user->id);

            foreach ($user->assignedClients as $client) {
                $outer->orWhere(fn (Builder $scoped) => $scoped->visibleToClient($client));
            }
        });
    }

    /**
     * @return Builder<Folder>
     */
    public function folders(User $user): Builder
    {
        $query = Folder::query();

        if (! $user->isClientScoped()) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($user): void {
            $outer->where('created_by', $user->id);

            foreach ($user->assignedClients as $client) {
                $outer->orWhere(fn (Builder $scoped) => $scoped->visibleToClient($client));
            }
        });
    }

    /**
     * Whether a scoped staff member may reach this specific file. Unscoped
     * staff always may; the policies AND this into their permission checks
     * so direct access respects the same boundary as the listings.
     */
    public function allowsFile(User $user, File $file): bool
    {
        if (! $user->isClientScoped()) {
            return true;
        }

        return $this->files($user)->whereKey($file->getKey())->exists();
    }

    public function allowsFolder(User $user, Folder $folder): bool
    {
        if (! $user->isClientScoped()) {
            return true;
        }

        return $this->folders($user)->whereKey($folder->getKey())->exists();
    }

    /**
     * Client ids a user may share with, or null when unrestricted (the
     * whole roster). A scoped user may only share with their assigned
     * clients.
     *
     * @return list<int>|null
     */
    public function assignableClientIds(User $user): ?array
    {
        if (! $user->isClientScoped()) {
            return null;
        }

        return array_values($user->assignedClients()->pluck('users.id')->map(fn ($id): int => (int) $id)->all());
    }

    /**
     * Group ids a user may share with, or null when unrestricted. A scoped
     * user may share with any group that contains at least one of their
     * assigned clients.
     *
     * @return list<int>|null
     */
    public function assignableGroupIds(User $user): ?array
    {
        if (! $user->isClientScoped()) {
            return null;
        }

        $clientIds = $this->assignableClientIds($user) ?? [];

        if ($clientIds === []) {
            return [];
        }

        return array_values(Group::query()
            ->whereHas('members', fn (Builder $members) => $members->whereIn('users.id', $clientIds))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    public function canAssignClient(User $user, User $client): bool
    {
        $ids = $this->assignableClientIds($user);

        return $ids === null || in_array($client->id, $ids, true);
    }

    public function canAssignGroup(User $user, Group $group): bool
    {
        $ids = $this->assignableGroupIds($user);

        return $ids === null || in_array($group->id, $ids, true);
    }

    /**
     * Whether a staff member may put a client into a group, or take one
     * out again.
     *
     * Not canAssignGroup(): that answers "may I share with this group",
     * and it answers it *from* the membership — a group counts as the
     * user's because one of their clients is in it. Deciding membership
     * with a predicate derived from membership means whoever may edit
     * the list also decides what the list entitles them to, which is not
     * a boundary at all. It is also the wrong answer here in the other
     * direction: a group nobody has joined yet belongs to nobody, so a
     * scoped staff member could never put the first member into a group
     * they had just created.
     *
     * The question membership actually asks is about reach. Joining a
     * group hands the new member everything shared with it, and — when
     * that member is one of the actor's own clients — hands the actor
     * the same content back through File::scopeVisibleToClient, which is
     * what StaffLibraryScope::files() is built on. So both sides have to
     * hold: the client must be one this staff member holds, and the
     * group must not already reach beyond their library. A group with
     * nothing shared with it passes trivially, which is what keeps a
     * newly created one usable.
     *
     * Unscoped staff are unaffected — both halves are true for them by
     * construction.
     */
    public function allowsGroupMembership(User $user, Group $group, User $client): bool
    {
        return $this->canAssignClient($user, $client) && $this->groupReachesNoFurther($user, $group);
    }

    /**
     * Whether everything shared with this group is already inside the
     * user's library — files assigned to it, and the folders whose
     * subtrees it can browse.
     */
    private function groupReachesNoFurther(User $user, Group $group): bool
    {
        if (! $user->isClientScoped()) {
            return true;
        }

        $morph = $group->getMorphClass();

        $fileIds = FileAssignment::query()
            ->where('assignable_type', $morph)->where('assignable_id', $group->id)
            ->pluck('file_id')->unique();

        if ($fileIds->isNotEmpty() && $this->files($user)->whereIn('id', $fileIds)->count() !== $fileIds->count()) {
            return false;
        }

        $folderIds = FolderAssignment::query()
            ->where('assignable_type', $morph)->where('assignable_id', $group->id)
            ->pluck('folder_id')->unique();

        return $folderIds->isEmpty()
            || $this->folders($user)->whereIn('id', $folderIds)->count() === $folderIds->count();
    }
}
