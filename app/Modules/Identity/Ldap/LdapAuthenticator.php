<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

use App\Models\User;
use App\Modules\Identity\AuthSource;
use Illuminate\Support\Facades\Cache;

/**
 * The seam the login flow calls: decides whether the directory applies at
 * all, and stamps what it learns onto the local account.
 *
 * Everything that would make a directory call unnecessary or unsafe is
 * settled here, before any network traffic:
 *
 *  - the feature is off, or ext-ldap is missing, or the host/base DN are
 *    blank (LdapSettings::usable());
 *  - the account is staff. LDAP is client-only, and that is enforced on
 *    the account rather than on a setting, so no configuration mistake can
 *    turn the directory into a route to staff authority;
 *  - the password is empty or whitespace. Under RFC 4513 a simple bind
 *    with an empty password against a valid DN is an *unauthenticated*
 *    bind and succeeds — the classic LDAP auth bypass. LdapRecord guards
 *    the empty case with `empty()`, which is false for `" "`, so a
 *    whitespace-only password would otherwise reach a real bind;
 *  - the circuit breaker is open. A directory that stops answering would
 *    otherwise hold a worker on every failed client password, turning
 *    "LDAP is down" into "the login page is down".
 */
class LdapAuthenticator
{
    /**
     * Long enough that an outage does not cost every login a timeout,
     * short enough that recovery is not noticeable.
     */
    private const BREAKER_KEY = 'identity.ldap.unreachable';

    private const BREAKER_SECONDS = 60;

    public function __construct(
        private readonly LdapDirectory $directory,
    ) {}

    public function enabled(): bool
    {
        return LdapSettings::current()->usable();
    }

    /**
     * Verify a password against the directory on behalf of an account that
     * already exists locally.
     *
     * $user is null when nobody local matches — provisioning handles that
     * case, and this method still answers so the caller can decide.
     */
    public function attempt(string $email, string $password, ?User $user = null): ?LdapIdentity
    {
        if (! $this->applies($password, $user)) {
            return null;
        }

        $identity = $this->directory->authenticate($email, $password);

        if ($identity === null) {
            return null;
        }

        Cache::forget(self::BREAKER_KEY);

        return $identity;
    }

    /**
     * Record what the directory told us about an account that already
     * exists, so an administrator can see which entry it corresponds to.
     *
     * `auth_source` is set here only when the account has no source of its
     * own yet — a login is not an authenticated request and has no business
     * flipping a privilege-adjacent flag on an account it did not create.
     */
    public function stamp(User $user, LdapIdentity $identity): void
    {
        $user->forceFill([
            'ldap_dn' => $identity->dn,
            'ldap_synced_at' => now(),
        ])->save();
    }

    public function markUnreachable(): void
    {
        Cache::put(self::BREAKER_KEY, true, self::BREAKER_SECONDS);
    }

    private function applies(string $password, ?User $user): bool
    {
        if (trim($password) === '') {
            return false;
        }

        // Staff never authenticate against a directory.
        if ($user !== null && ! $user->isClient()) {
            return false;
        }

        if (Cache::get(self::BREAKER_KEY) === true) {
            return false;
        }

        return $this->enabled();
    }

    /**
     * Whether this account's password lives in the directory rather than
     * here — in which case the local hash is not consulted at all.
     */
    public function isDirectoryAccount(?User $user): bool
    {
        return $user !== null
            && $user->isClient()
            && $user->auth_source === AuthSource::Ldap;
    }
}
