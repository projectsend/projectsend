<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Clients\ClientProvisioning;
use App\Modules\Identity\AuthSource;
use Illuminate\Support\Str;

/**
 * A directory identity signing in for the first time, with no local
 * account yet.
 *
 * Always a client, never staff — there is no role parameter on this path,
 * so no configuration mistake can make a directory mint an account with
 * authority over the installation. v1's equivalent had a "default role"
 * setting that offered staff roles and bypassed the permission checks
 * while it did so.
 *
 * Approval is the directory's own setting, `auto_approve`, rather than the
 * `ClientsAutoApprove` a self-registration asks. Those look like the same
 * question and are not: one is about strangers arriving at a public form,
 * the other about people your directory has already authenticated, and an
 * installation can reasonably answer them differently — make the web wait,
 * let staff in. `ClientsAutoGroup` is still shared, since which group a new
 * client joins does not turn on how they arrived.
 *
 * When approval is required, the login that created the account is refused
 * by the ordinary inactive-account branch with the ordinary "not approved
 * yet" message, and the account waits in the account-requests queue.
 */
class LdapProvisioner
{
    public function __construct(
        private readonly LdapAuthenticator $ldap,
        private readonly ClientProvisioning $clients,
    ) {}

    public function provision(string $email, string $password): ?User
    {
        $settings = LdapSettings::current();

        if (! $this->ldap->enabled() || ! $settings->auto_provision) {
            return null;
        }

        // Same refusals as an ordinary LDAP login — an empty or
        // whitespace-only password must never reach a bind.
        $identity = $this->ldap->attempt($email, $password);

        if ($identity === null) {
            return null;
        }

        return $this->clients->provision(
            name: $identity->name,
            email: $identity->email,
            // A password they will never use and never learn: this account
            // authenticates against the directory. Generated rather than
            // left null so nothing downstream has to special-case an empty
            // hash, and never the directory password.
            password: Str::password(64),
            action: Action::LdapClientProvisioned,
            source: AuthSource::Ldap,
            ldapDn: $identity->dn,
            autoApprove: $settings->auto_approve,
        );
    }
}
