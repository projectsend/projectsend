<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\DB;

/**
 * Creating the very first administrator is a claim, not a check followed
 * by an insert.
 *
 * "Has this installation been set up" is answered by asking whether any
 * staff row exists, and an empty result has nothing in it to lock. So two
 * unauthenticated setup requests arriving together both read "no staff",
 * both spend a quarter of a second hashing a password, and both insert a
 * System Administrator. The operator's own setup succeeds and looks
 * entirely normal, which is the point: a stranger walks away with a
 * second, permanent administrator account and nothing says so.
 * (GHSA-w3w9-prpw-qx77, reported by @ry2811.)
 *
 * What gets locked is the System Administrator role row. It is the thing
 * being claimed; it is written by the roles migration and rewritten on
 * every boot, so unlike the staff rows it is always there to be locked.
 * The second caller waits on it, and by the time it has the lock the
 * first caller's user row is committed and visible — so its own re-check,
 * asked inside the claim this time, sees an installation that is already
 * set up and creates nothing.
 *
 * Locking the staff query itself would not do. There are no matching rows
 * on a fresh install, and a lock over nothing serialises nothing.
 */
final class FirstAdministrator
{
    /**
     * Create the initial administrator, or nothing if somebody else got
     * there first.
     *
     * @param  callable(): bool  $stillNeeded  asked again with the claim held
     * @param  callable(): User  $create  runs only if it is still needed
     * @return User|null null when the claim was lost
     */
    public static function claim(callable $stillNeeded, callable $create): ?User
    {
        // The lock needs a row to bite on. This is idempotent and already
        // runs on every boot; asking again costs one query on the one
        // request in the life of an installation that comes through here,
        // and means a database somehow missing its roles gets them back
        // rather than quietly racing.
        (new EnsureSystemRoles)->ensure();

        return DB::transaction(function () use ($stillNeeded, $create): ?User {
            Role::query()
                ->where('name', SystemRole::SystemAdministrator->value)
                ->lockForUpdate()
                ->value('id');

            return $stillNeeded() ? $create() : null;
        });
    }
}
