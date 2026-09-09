<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;

/**
 * Finding the account that holds an address, exactly.
 *
 * `where('email', $address)` is not an exact match. It is whatever the
 * database's collation says equality means, and the documented one here —
 * `utf8mb4_unicode_ci`, in INSTALL.md and in config/database.php — folds
 * accents:
 *
 *     administrator@example.com  =  administrator@éxample.com   -> 1
 *
 * Those are two different domains. `éxample.com` is `xn--xample-9ua.com`,
 * a name somebody else can own and prove they own. So an attacker could
 * register the second at an OIDC provider, verify it honestly, sign in,
 * and be handed the first account — no password, no interaction from its
 * owner, and an administrator session if that account was one
 * (GHSA-wgxf-v8cr-37mj).
 *
 * ### Loose is right when refusing and wrong when selecting
 *
 * The same looseness protects elsewhere and is deliberately left alone.
 * `AvailableEmailRule` and `ClientProvisioning::emailIsAvailable()` ask
 * "is this address free?", and a collation that answers "no" to a
 * near-miss refuses *more* registrations, which is the safe direction.
 * This class is for the other question — "which account is this?" — where
 * matching more than you meant hands somebody an account.
 *
 * ### Why the filtering is in PHP
 *
 * A `COLLATE utf8mb4_bin` in the query would work on MySQL and break
 * everywhere else, and the test suite runs on SQLite, which is byte-exact
 * and would never have shown the bug in the first place. Comparing here
 * gives one answer on every driver, and it is the answer that does not
 * depend on how somebody created their database.
 *
 * Case is still folded, because that is a real requirement rather than an
 * accident: addresses are stored lowercased and a provider may send any
 * case. `mb_strtolower` folds case without folding accents, which is
 * exactly the line to draw.
 */
class AccountLookup
{
    /**
     * The account whose address is exactly this one, or null.
     *
     * @param  bool  $withTrashed  include soft-deleted accounts — a
     *                             deleted account still holds its address
     *                             until erasure
     */
    public function byEmail(string $email, bool $withTrashed = false): ?User
    {
        $query = $withTrashed ? User::withTrashed() : User::query();

        // The database narrows, this decides. A collation that matches too
        // much returns extra rows here and they are dropped; one that
        // matches too little was never going to return the right row at
        // all, which is a different bug and not one anybody has.
        return $query->where('email', $email)->get()
            ->first(fn (User $user): bool => $this->isSameAddress($user->email, $email));
    }

    /**
     * Whether two strings name the same mailbox: case-insensitively, and
     * byte-exact about everything else.
     */
    public function isSameAddress(?string $stored, ?string $given): bool
    {
        if ($stored === null || $given === null) {
            return false;
        }

        return mb_strtolower(trim($stored), 'UTF-8') === mb_strtolower(trim($given), 'UTF-8');
    }
}
