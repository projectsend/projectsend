<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\StaffSignInPolicy;

/**
 * The provider identities linked to an account, ended at the moments its
 * API tokens and assistant connections end: a password changed, reset or
 * replaced, or the account converted between staff and client.
 *
 * A link is a way in that no password check ever reaches. Linking needs a
 * confirmed password, so whoever held the account for a moment could link
 * an identity of their own, and signing in through it again after the
 * owner changed the password was the whole of taking the account back.
 *
 * The owner signs in with the password just set, and connects their
 * provider again from Connected accounts. A provider identity that
 * vouches for the account's own confirmed address links itself again at
 * sign-in (SocialAuthenticator); one under another address does not.
 *
 * Two links are never ended, because each is the way in itself: the
 * provider staff are required to sign in with (StaffSignInPolicy), and
 * every link of an account that has no password of its own
 * (AuthSource::Social), the same rule the Connected accounts screen
 * applies to removing the last one. Whoever sets a password on such an
 * account makes it Local first, as NewPasswordController does.
 */
final class ProviderLinks
{
    public function __construct(
        private readonly StaffSignInPolicy $signIn,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Returns how many links were ended.
     */
    public function endAllFor(User $user): int
    {
        if ($user->auth_source === AuthSource::Social) {
            return 0;
        }

        // As configured, not only while usable: a provider down for a
        // moment is still the one staff will be made to use when it is
        // back, and passwords are refused again then.
        $required = $user->isStaff() ? $this->signIn->configuredProvider() : null;

        $links = SocialAccount::query()
            ->where('user_id', $user->getKey())
            ->when($required !== null, fn ($query) => $query->where('provider', '!=', $required?->value))
            ->get();

        foreach ($links as $link) {
            $link->delete();

            $this->activity->log(Action::SocialAccountUnlinked, subject: $user, context: ['provider' => $link->provider->label()]);
        }

        return $links->count();
    }
}
