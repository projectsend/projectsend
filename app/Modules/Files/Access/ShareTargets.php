<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who a file or folder is shared with, and who it could still be shared
 * with — the lists behind every sharing panel.
 *
 * Splitting a subject's assignments into clients and groups was written out
 * at four call sites and had already started to drift: one compared
 * assignable_type against User::class while the others used the morph class.
 * Those agree only for as long as no morph map is registered, which is
 * exactly the kind of difference that goes unnoticed until it doesn't.
 *
 * Scoping is not re-derived here. Which clients and groups a staff member
 * may share with belongs to StaffLibraryScope, and this asks it.
 */
class ShareTargets
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly ClientIdentityScope $identity,
    ) {}

    /**
     * The clients and groups a subject is already shared with, as id/name
     * pairs. Neutral keys, so callers can nest it ('shares' on the details
     * panel) or flatten it (the edit pages' assigned_* props).
     *
     * **This is the unfiltered truth, and it is not what a screen should
     * show.** Everyone a file is really in front of is the right answer for
     * deciding something — VisibleCommentScope resolves notification
     * recipients from it, and a recipient left out of that list is one who
     * never hears about a message addressed to them. It is the wrong answer
     * for telling somebody, because a client-scoped viewer may hold a file
     * that is also shared with a client they have no business knowing
     * exists. Anything rendering these names wants assignedFor() below.
     *
     * @return array{clients: list<array{id: int, name: string}>, groups: list<array{id: int, name: string}>}
     */
    public function assigned(File|Folder $subject): array
    {
        [$clientIds, $groupIds] = $this->assignedIds($subject);

        return [
            'clients' => $this->clients($clientIds),
            'groups' => $this->groups($groupIds),
        ];
    }

    /**
     * assigned(), narrowed to the recipients this viewer may be told
     * about. The display half of the pair — see the warning above.
     *
     * @return array{clients: list<array{id: int, name: string}>, groups: list<array{id: int, name: string}>}
     */
    public function assignedFor(File|Folder $subject, ?User $viewer): array
    {
        return $this->identity->filterShares($viewer, $this->assigned($subject));
    }

    /**
     * The assigned lists plus everything still available to share with,
     * narrowed to what this viewer is allowed to reach.
     *
     * @return array{assigned_clients: list<array{id: int, name: string}>, assigned_groups: list<array{id: int, name: string}>, available_clients: list<array{id: int, name: string}>, available_groups: list<array{id: int, name: string}>}
     */
    public function forSubject(File|Folder $subject, User $viewer): array
    {
        [$clientIds, $groupIds] = $this->assignedIds($subject);

        // Resolved once each: these hit the database, and the call sites this
        // replaces evaluated them twice apiece — once to decide whether to
        // filter, once to supply the ids.
        $assignableClientIds = $this->scope->assignableClientIds($viewer);
        $assignableGroupIds = $this->scope->assignableGroupIds($viewer);

        $availableClients = User::query()
            ->where('type', UserType::Client)
            ->whereNotIn('id', $clientIds)
            ->when($assignableClientIds !== null, fn (Builder $query) => $query->whereIn('id', $assignableClientIds))
            ->orderBy('name')
            ->get();

        $availableGroups = Group::query()
            ->whereNotIn('id', $groupIds)
            ->when($assignableGroupIds !== null, fn (Builder $query) => $query->whereIn('id', $assignableGroupIds))
            ->orderBy('name')
            ->get();

        // assignedFor, not assigned: an edit page listing a recipient this
        // viewer may not identify would both name them and offer a control
        // for a share the viewer cannot otherwise reach. available_* below
        // was already narrowed this way; assigned_* was not, which is the
        // asymmetry that made the whole panel a roster listing.
        $assigned = $this->assignedFor($subject, $viewer);

        return [
            'assigned_clients' => $assigned['clients'],
            'assigned_groups' => $assigned['groups'],
            'available_clients' => $this->pairs($availableClients),
            'available_groups' => $this->pairs($availableGroups),
        ];
    }

    /**
     * @return array{0: Collection<int, mixed>, 1: Collection<int, mixed>}
     */
    private function assignedIds(File|Folder $subject): array
    {
        // A file revision owns no assignment rows — it inherits the
        // recipients of its version chain's root (File::sharingOwnerId).
        // Reading its own rows would show every revision as shared with
        // nobody, which is the opposite of what is true.
        $assigned = $subject instanceof File
            ? FileAssignment::query()->where('file_id', $subject->sharingOwnerId())->get()
            : $subject->assignments()->get();

        // getMorphClass() rather than ::class, so this keeps agreeing with
        // what was written to assignable_type even if a morph map is added.
        return [
            $assigned->where('assignable_type', (new User)->getMorphClass())->pluck('assignable_id'),
            $assigned->where('assignable_type', (new Group)->getMorphClass())->pluck('assignable_id'),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return list<array{id: int, name: string}>
     */
    private function clients(Collection $ids): array
    {
        return $this->pairs(User::query()->whereIn('id', $ids)->orderBy('name')->get());
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return list<array{id: int, name: string}>
     */
    private function groups(Collection $ids): array
    {
        return $this->pairs(Group::query()->whereIn('id', $ids)->orderBy('name')->get());
    }

    /**
     * Built by iteration rather than map()->values(): Collection's value
     * template is invariant, so passing a Collection<Group> where
     * Collection<User|Group> is declared does not type-check, and the
     * result of map() is not provably a list either.
     *
     * @param  iterable<User|Group>  $models
     * @return list<array{id: int, name: string}>
     */
    private function pairs(iterable $models): array
    {
        $pairs = [];

        foreach ($models as $model) {
            $pairs[] = ['id' => (int) $model->getKey(), 'name' => $model->name];
        }

        return $pairs;
    }
}
