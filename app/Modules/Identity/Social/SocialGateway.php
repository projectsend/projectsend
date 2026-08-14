<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Talking to an identity provider.
 *
 * An interface with one production implementation, so the login flow can
 * be tested without one — the same strategy LdapDirectory established,
 * and for the same reason: everything above the wire is where the bugs
 * would be. Whether an unverified address may reach an existing account,
 * whether a subject beats an email, whether two-factor still challenges —
 * none of that should need an OAuth server to exercise, and all of it is
 * where v1 went wrong.
 */
interface SocialGateway
{
    /**
     * Begin the exchange: where to send the browser.
     */
    public function redirect(SocialSettings $settings): RedirectResponse;

    /**
     * The identity this provider just asserted.
     *
     * Returns null for every failure — a mismatched state, a refused
     * consent screen, an unreachable token endpoint, a response with no
     * subject — because the caller must not be able to tell them apart
     * and neither must the person signing in.
     */
    public function identity(SocialSettings $settings): ?SocialIdentity;
}
