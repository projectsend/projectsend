<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * "Staff sign in through this provider, and nothing else" (#1828).
 *
 * For an organisation whose staff live in a directory such as Entra ID:
 * switching somebody off there should be enough to keep them out of
 * here, which it is not while a ProjectSend password of theirs still
 * works, or can be set again from their mailbox.
 *
 * Staff only. Clients keep their passwords whatever this says.
 *
 * Enforced only while the provider can actually be used. A provider that
 * an administrator switches off, or whose configuration breaks, lets
 * passwords back in rather than leaving nobody able to sign in — the same
 * rule SocialSettings::skipsTwoFactor() follows. The other way back in is
 * `php artisan projectsend:staff-passwords-on`.
 *
 * What this does not do, and the documentation says so: switching an
 * account off at the provider stops its next sign-in, not a session that
 * is already open, and API tokens and AI assistant connections are
 * credentials of their own. Deactivating the account here ends all three.
 */
class StaffSignInPolicy
{
    public function __construct(private readonly Settings $settings) {}

    /** The provider staff must use, while it is usable, or null. */
    public function provider(): ?SocialProvider
    {
        $provider = $this->configuredProvider();

        if ($provider === null || ! SocialSettings::for($provider)->usable()) {
            return null;
        }

        return $provider;
    }

    /**
     * What the setting says, usable or not — for the settings screen,
     * which has to show the choice even while it is not being enforced.
     */
    public function configuredProvider(): ?SocialProvider
    {
        $value = $this->settings->get(Setting::StaffSignInProvider);

        return is_string($value) && $value !== '' ? SocialProvider::tryFrom($value) : null;
    }

    /** Whether this account may not use a password at all. */
    public function refusesPassword(User $user): bool
    {
        return $user->isStaff() && $this->provider() !== null;
    }

    /** The provider this account must sign in with, or null. */
    public function providerFor(User $user): ?SocialProvider
    {
        return $user->isStaff() ? $this->provider() : null;
    }
}
