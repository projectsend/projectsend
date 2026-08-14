<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

/**
 * Talking to a directory server.
 *
 * An interface with one production implementation, so the login flow can
 * be tested without one. That is the whole testing strategy for this
 * feature: everything above the wire — which account types may
 * authenticate, provisioning, rate limiting, the two-factor hand-off — is
 * where the bugs would be, and none of it should need a directory to
 * exercise.
 */
interface LdapDirectory
{
    /**
     * Verify a password against the directory.
     *
     * Returns null for every failure — no such entry, wrong password,
     * server unreachable — because the caller must not be able to tell
     * those apart, and neither must the login form.
     */
    public function authenticate(string $email, string $password): ?LdapIdentity;

    /**
     * Exercise the configuration and report which stage failed, for the
     * settings screen's test button. This is the one place that is allowed
     * to be specific about failures: it is behind `edit_settings`, and it
     * is the difference between a working directory and v1's checkbox that
     * silently did nothing.
     */
    public function probe(?string $email = null, ?string $password = null): LdapProbeResult;
}
