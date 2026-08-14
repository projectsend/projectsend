<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Groups\Models\Group;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which file's file_assignments rows decide who may see a given row —
 * File::sharingOwnerId() expressed as SQL.
 *
 * A revision holds no recipients of its own: its audience IS the audience
 * of the oldest file in its version chain (see FileVersions). That is what
 * makes "a revision is always shared with the same people as the original"
 * a property of the schema rather than a convention some future write path
 * can forget — but it means every query that reads assignments has to match
 * on the chain root instead of on the row's own id.
 *
 * Kept here, in one expression, rather than repeated inline in each scope:
 * this is the join the three visibility scopes on File all depend on, and
 * having three near-identical COALESCEs drift apart is exactly the class of
 * bug ShareTargets was extracted to fix.
 */
class SharingIdentity
{
    /**
     * The column every assignment lookup should match against, in place of
     * `files.id`.
     */
    public static function column(): Expression
    {
        return DB::raw('COALESCE(files.version_root_id, files.id)');
    }

    /**
     * File ids assigned to $client directly or through one of $groupIds —
     * as a subquery, so callers can use it with whereIn/whereNotIn against
     * column() without loading anything first.
     *
     * @param  list<int>  $groupIds
     */
    public static function assignedToClient(User $client, array $groupIds): QueryBuilder
    {
        $userMorph = (new User)->getMorphClass();
        $groupMorph = (new Group)->getMorphClass();

        return FileAssignment::query()->toBase()
            ->select('file_id')
            ->where(function (QueryBuilder $query) use ($client, $groupIds, $userMorph, $groupMorph): void {
                $query->where(function (QueryBuilder $direct) use ($client, $userMorph): void {
                    $direct->where('assignable_type', $userMorph)->where('assignable_id', $client->id);
                })->orWhere(function (QueryBuilder $viaGroup) use ($groupIds, $groupMorph): void {
                    $viaGroup->where('assignable_type', $groupMorph)->whereIn('assignable_id', $groupIds);
                });
            });
    }

    /**
     * File ids assigned to any of $groupIds.
     *
     * @param  list<int>|Collection<int, mixed>  $groupIds
     */
    public static function assignedToGroups(iterable $groupIds): QueryBuilder
    {
        $groupMorph = (new Group)->getMorphClass();

        return FileAssignment::query()->toBase()
            ->select('file_id')
            ->where('assignable_type', $groupMorph)
            ->whereIn('assignable_id', $groupIds instanceof Collection ? $groupIds->all() : $groupIds);
    }
}
