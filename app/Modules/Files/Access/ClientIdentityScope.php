<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Groups\Models\Group;

/**
 * Whether a viewer may be told who a client is.
 *
 * A different question from whether they may read a file, and the gap
 * between the two is the whole reason this exists. A stranger client's
 * upload can sit legitimately inside a client-scoped staff member's
 * library — shared with a group one of their own clients belongs to, or
 * assigned to one of their clients alongside somebody else's. The file is
 * theirs to read. The other client's name is not theirs to see.
 *
 * Commit 12a8ebe3 said exactly that while fixing one dashboard widget, and
 * then the rule stayed in that widget. Every other place that serialises a
 * file went on publishing the uploader and each recipient by name, so a
 * manager assigned to one client could read the names and ids of clients
 * on nobody's roster but their own out of ordinary file metadata. That is
 * what this class ends: one statement of the rule, asked by every surface
 * that names a client.
 *
 * Two things it deliberately is not:
 *
 *  - It is not a download check. The file boundary is StaffLibraryScope's
 *    and FilePolicy's, and it is already correct — a file belonging only
 *    to a client off the roster is a 403 today. This narrows what a
 *    permitted response is allowed to say, nothing more.
 *  - It is not applied to staff. A colleague's name is not a client
 *    identity, and hiding it would hide who uploaded most of the library
 *    from the people who work in it.
 *
 * Unscoped staff are unaffected: they may identify everyone, which is what
 * `null` means everywhere StaffLibraryScope answers this shape of question.
 */
class ClientIdentityScope
{
    /**
     * Memoised per viewer, since the listings ask once per row and each
     * miss is a roster query. Registered as `scoped`, so this lasts a
     * request and is dropped between queue jobs — the same lifetime, and
     * for the same reason, as StaffLibraryScope's own memo.
     *
     * @var array<int, list<int>|null>
     */
    private array $clientIds = [];

    /** @var array<int, list<int>|null> */
    private array $groupIds = [];

    public function __construct(private readonly StaffLibraryScope $scope) {}

    /**
     * Whether $viewer may be told that $subject exists, and what they are
     * called.
     *
     * A null subject is permitted: there is no identity to leak, and every
     * caller here is reading an optional relation.
     */
    public function permits(?User $viewer, ?User $subject): bool
    {
        if ($subject === null) {
            return true;
        }

        if (! $subject->isClient()) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        if ($viewer->is($subject)) {
            return true;
        }

        $ids = $this->identifiableClientIds($viewer);

        return $ids === null || in_array($subject->id, $ids, true);
    }

    /**
     * The same question about a client known only by id — used where a
     * caller has a foreign key rather than a loaded model.
     *
     * An id that belongs to nobody, or to a staff member, is permitted:
     * there is no client identity behind it to protect.
     */
    public function permitsClientId(?User $viewer, ?int $id): bool
    {
        if ($id === null) {
            return true;
        }

        return $this->permits($viewer, User::query()->find($id));
    }

    /**
     * Whether $viewer may be told a group exists.
     *
     * A group is a list of clients wearing one name, so naming one to
     * somebody who may reach none of its members says the same thing
     * naming a client would. The set is StaffLibraryScope's
     * assignableGroupIds — every group holding at least one of the
     * viewer's own clients.
     */
    public function permitsGroupId(?User $viewer, ?int $id): bool
    {
        if ($id === null) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        $ids = $this->identifiableGroupIds($viewer);

        return $ids === null || in_array($id, $ids, true);
    }

    /**
     * A client's name, or null when this viewer may not be told it.
     *
     * Null rather than a placeholder on purpose: every consumer of these
     * fields already renders "no uploader recorded" for a null, because a
     * deleted account leaves one behind. Inventing a "Hidden" string would
     * be a new thing for sixteen locales to translate and would itself
     * announce that there is somebody there to hide.
     */
    public function nameOf(?User $viewer, ?User $subject): ?string
    {
        return $this->permits($viewer, $subject) ? $subject?->name : null;
    }

    /**
     * Drop the entries this viewer may not be told about from a list of
     * id/name pairs describing clients.
     *
     * @param  list<array{id: int, name: string}>  $pairs
     * @return list<array{id: int, name: string}>
     */
    public function filterClientPairs(?User $viewer, array $pairs): array
    {
        if ($this->identifiableClientIds($viewer) === null) {
            return $pairs;
        }

        return array_values(array_filter(
            $pairs,
            fn (array $pair): bool => $this->permitsClientId($viewer, $pair['id']),
        ));
    }

    /**
     * @param  list<array{id: int, name: string}>  $pairs
     * @return list<array{id: int, name: string}>
     */
    public function filterGroupPairs(?User $viewer, array $pairs): array
    {
        if ($this->identifiableGroupIds($viewer) === null) {
            return $pairs;
        }

        return array_values(array_filter(
            $pairs,
            fn (array $pair): bool => $this->permitsGroupId($viewer, $pair['id']),
        ));
    }

    /**
     * Both halves of a `shares` payload at once, since the two lists are
     * always filtered together.
     *
     * @param  array{clients: list<array{id: int, name: string}>, groups: list<array{id: int, name: string}>}  $shares
     * @return array{clients: list<array{id: int, name: string}>, groups: list<array{id: int, name: string}>}
     */
    public function filterShares(?User $viewer, array $shares): array
    {
        return [
            'clients' => $this->filterClientPairs($viewer, $shares['clients']),
            'groups' => $this->filterGroupPairs($viewer, $shares['groups']),
        ];
    }

    /**
     * Whether this viewer is narrowed at all. Callers use it to skip
     * per-row work for the common unscoped case.
     */
    public function isNarrowed(?User $viewer): bool
    {
        return $viewer === null || $this->identifiableClientIds($viewer) !== null;
    }

    /**
     * @return list<int>|null
     */
    private function identifiableClientIds(?User $viewer): ?array
    {
        if ($viewer === null) {
            return [];
        }

        // Deliberately the same set as "who may I share with". A client on
        // the roster is one this viewer already works with by name; a
        // client off it is one they have no business knowing exists.
        return $this->clientIds[$viewer->id] ??= $this->scope->assignableClientIds($viewer);
    }

    /**
     * @return list<int>|null
     */
    private function identifiableGroupIds(?User $viewer): ?array
    {
        if ($viewer === null) {
            return [];
        }

        return $this->groupIds[$viewer->id] ??= $this->scope->assignableGroupIds($viewer);
    }
}
