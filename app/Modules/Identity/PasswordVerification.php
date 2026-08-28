<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Ldap\LdapAuthenticator;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Whether a password is this account's password.
 *
 * The sibling of SignIn, on the other side of the line it draws. SignIn is
 * everything that happens *after* a credential checks out; this is the one
 * question asked before it, for the one credential source that has two
 * possible homes -- the local hash, or the directory the account was
 * provisioned from.
 *
 * It exists for the reason SignIn gives for existing: "the way they get
 * broken is by being written twice". The sign-in form asked this question
 * properly, taking a directory bind when the local hash is a placeholder
 * nobody holds. The confirm-password screen asked only half of it, and so
 * refused every directory account the password it actually has.
 *
 * The order is the sign-in form's, and matters: the local hash is tried
 * first so an account that answers locally never generates directory
 * traffic, and an account whose credentials are *known* to live in the
 * directory skips the local check entirely, because there the local hash
 * is a Str::password(64) placeholder that cannot match anything.
 */
class PasswordVerification
{
    public function __construct(private readonly LdapAuthenticator $ldap) {}

    public function verify(User $user, string $password): bool
    {
        if (! $this->ldap->isDirectoryAccount($user)
            && Auth::guard('web')->validate(['email' => $user->email, 'password' => $password])) {
            $this->rehashIfStale($user, $password);

            return true;
        }

        $identity = $this->ldap->attempt($user->email, $password, $user);

        if ($identity === null) {
            return false;
        }

        $this->ldap->stamp($user, $identity);

        return true;
    }

    /**
     * Re-hash a password stored under weaker settings than this
     * installation now uses.
     *
     * Laravel does this inside SessionGuard::attempt(), which neither
     * caller uses -- they verify and then hand the account to SignIn,
     * which calls Auth::login(). Neither re-hashes, so without this an
     * account keeps whatever cost it was created under forever, and
     * raising BCRYPT_ROUNDS would quietly apply to new accounts only.
     *
     * That is not hypothetical: every account the v1 migration carries
     * across arrives as `$2y$08$…`, because v1 hashed at cost 8, and would
     * otherwise stay four times cheaper to attack than an account created
     * here.
     *
     * **Only ever called on the local branch.** On the directory branch the
     * submitted plaintext is the *LDAP* password and the local hash is a
     * placeholder nobody holds; writing the directory credential into it
     * would mint a second way into the account that keeps working after
     * LDAP is switched off.
     */
    private function rehashIfStale(User $user, string $password): void
    {
        $guard = Auth::guard('web');

        // getProvider() is on SessionGuard rather than on the StatefulGuard
        // contract. This guard is a SessionGuard in every configuration this
        // application ships; the check is here so a custom driver degrades
        // to "no re-hash" instead of a fatal on the login path.
        if (! $guard instanceof SessionGuard) {
            return;
        }

        // No-ops unless the hasher says the stored digest needs it, so this
        // costs an already-current account nothing.
        $guard->getProvider()->rehashPasswordIfRequired($user, ['password' => $password]);
    }
}
