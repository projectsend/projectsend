<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
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
 */
class ActivityLogScope
{
    public function __construct(
        private readonly StaffLibraryScope $library,
    ) {}

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
