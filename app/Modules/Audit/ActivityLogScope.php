<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Models\User;
use App\Modules\Files\Access\ClientIdentityScope;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Identity\UserType;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which activity-log entries a viewer may read.
 *
 * `view_actions_log` alone is not the whole answer for a client-scoped
 * staff member (see User::isClientScoped). Scoping restricts which library
 * content they can reach, but an activity row carries the subject's *name*
 * — and, for downloads, who fetched it and from which IP — so an unscoped
 * log hands over exactly the information the scope exists to withhold:
 * a Client Manager could read the names of every file in the installation
 * and who touched them, while getting a 403 on the files themselves. The
 * Client Manager system role ships with `view_actions_log`, so this is the
 * default configuration, not an exotic one.
 *
 * A scoped viewer therefore sees an entry when it is about something in
 * their scope, or when they did it themselves:
 *
 *   - File / Folder subjects, limited to StaffLibraryScope
 *   - User subjects, limited to the clients assigned to them
 *   - anything they are the actor of (their own audit trail stays whole)
 *
 * Everything else — other staff's logins, settings changes, entries about
 * files since deleted (no live row left to test the scope against) —
 * is not theirs to read.
 *
 * An unscoped viewer's log is unchanged: installation-wide, as before.
 *
 * Seeing an entry is not the same as being told everybody in it. A file in
 * a scoped library can also reach a client off the viewer's roster (a
 * group holds clients of several managers), and that client downloading
 * it, or being shared it, writes an entry about a file the viewer may
 * see. redact() is the second half: it keeps the entry and drops the
 * names, address and targets the viewer may not be told, the same rule
 * ClientIdentityScope applies to every other surface that names a client.
 * Every surface that shows an entry asks it.
 */
class ActivityLogScope
{
    /** @var array<int, User|null> */
    private array $accounts = [];

    public function __construct(
        private readonly StaffLibraryScope $library,
        private readonly ClientIdentityScope $identity,
    ) {}

    /**
     * The entry as $viewer may read it.
     *
     * For a narrowed viewer, a client actor off their roster becomes "A
     * client" with no id and no address; so does a client subject; and the
     * context values that name somebody (a share target, a group member)
     * or something (the original a file revises) go when the viewer may
     * not be told them. Entries written before the ids were recorded carry
     * only the name, so their names go for every narrowed viewer: there is
     * nothing to check them against.
     *
     * Returns a copy; the stored entry is never touched.
     */
    public function redact(?User $viewer, ActivityLog $entry): ActivityLog
    {
        if (! $this->identity->isNarrowed($viewer)) {
            return $entry;
        }

        $entry = clone $entry;

        if ($entry->actor_type === UserType::Client->value && ! $this->clientPermitted($viewer, $entry->actor_id)) {
            $entry->actor_id = null;
            $entry->actor_name = (string) __('A client');
            $entry->ip_address = null;
        }

        if ($entry->subject_type === (new User)->getMorphClass() && ! $this->clientPermitted($viewer, $entry->subject_id)) {
            $entry->subject_name = (string) __('A client');
        }

        $context = $entry->context ?? [];

        if (array_key_exists('target', $context)) {
            $id = isset($context['target_id']) ? (int) $context['target_id'] : null;
            $type = $context['target_type'] ?? null;

            $permitted = match (true) {
                $id === null => false,
                $type === 'group' => $this->identity->permitsGroupId($viewer, $id),
                default => $this->clientPermitted($viewer, $id),
            };

            if (! $permitted) {
                $context['target'] = (string) match ($type) {
                    'group' => __('a group'),
                    'client' => __('a client'),
                    default => __('a client or group'),
                };
                unset($context['target_id']);
            }
        }

        if (array_key_exists('member', $context)) {
            $id = isset($context['member_id']) ? (int) $context['member_id'] : null;

            if ($id === null || ! $this->clientPermitted($viewer, $id)) {
                $context['member'] = (string) __('a client');
                unset($context['member_id']);
            }
        }

        if (array_key_exists('previous', $context)) {
            $id = isset($context['previous_id']) ? (int) $context['previous_id'] : null;

            if ($viewer === null || $id === null || $this->openableFileIds($viewer, [$id]) === []) {
                $context['previous'] = (string) __('another file');
                unset($context['previous_id']);
            }
        }

        if ($context !== []) {
            $entry->context = $context;
        }

        return $entry;
    }

    /**
     * Whether a client known by id may be named to $viewer. Unlike
     * ClientIdentityScope::permitsClientId, an id that resolves to nobody
     * (a deleted account) is refused: the entry still carries the name.
     */
    private function clientPermitted(?User $viewer, ?int $id): bool
    {
        if ($id === null) {
            return false;
        }

        if ($viewer !== null && $viewer->id === $id) {
            return true;
        }

        if (! array_key_exists($id, $this->accounts)) {
            $this->accounts[$id] = User::withTrashed()->find($id);
        }

        $account = $this->accounts[$id];

        return $account !== null && $this->identity->permits($viewer, $account);
    }

    /**
     * Whether a name typed into an "actor" filter may be matched against
     * this entry's actor: everybody's for an unscoped viewer, and for a
     * narrowed one never a client they may not be told about. Otherwise
     * the filter answers whether a hidden name contains what was typed.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function whereActorNameLike(Builder $query, User $viewer, string $term): Builder
    {
        $query->where('actor_name', 'like', '%'.$term.'%');

        if (! $this->identity->isNarrowed($viewer)) {
            return $query;
        }

        $clientIds = $this->library->assignableClientIds($viewer) ?? [];

        return $query->where(fn (Builder $actors) => $actors
            ->whereNull('actor_type')
            ->orWhere('actor_type', '!=', UserType::Client->value)
            ->orWhereIn('actor_id', [$viewer->id, ...$clientIds]));
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function apply(Builder $query, User $viewer): Builder
    {
        if (! $viewer->isClientScoped()) {
            return $query;
        }

        $fileMorph = (new File)->getMorphClass();
        $folderMorph = (new Folder)->getMorphClass();
        $userMorph = (new User)->getMorphClass();
        $clientIds = $this->library->assignableClientIds($viewer) ?? [];

        return $query->where(function (Builder $outer) use ($viewer, $fileMorph, $folderMorph, $userMorph, $clientIds): void {
            $outer->where('actor_id', $viewer->id);

            $outer->orWhere(fn (Builder $files) => $files
                ->where('subject_type', $fileMorph)
                ->whereIn('subject_id', $this->library->files($viewer)->select('id')));

            $outer->orWhere(fn (Builder $folders) => $folders
                ->where('subject_type', $folderMorph)
                ->whereIn('subject_id', $this->library->folders($viewer)->select('id')));

            if ($clientIds !== []) {
                $outer->orWhere(fn (Builder $users) => $users
                    ->where('subject_type', $userMorph)
                    ->whereIn('subject_id', $clientIds));
            }
        });
    }

    /**
     * File ids from $ids the viewer may actually open — used to decide
     * whether a row links anywhere. Permission alone was the old test,
     * which produced links to files the viewer would get a 403 on.
     *
     * @param  iterable<int>  $ids
     * @return array<int, bool> keyed by id; presence is the answer
     */
    public function openableFileIds(User $viewer, iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->library->files($viewer)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @param  iterable<int>  $ids
     * @return array<int, bool> keyed by id; presence is the answer
     */
    public function openableFolderIds(User $viewer, iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->library->folders($viewer)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }
}
