<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use Illuminate\Http\Request;

/**
 * How a signed-in person proves, again, that they are who the session
 * says: their password, or — for an account with no password to type —
 * signing in once more at the provider it is connected to.
 *
 * Two kinds of account have no password here. One that came in through a
 * provider and never set one (AuthSource::Social), and staff who must
 * sign in through a provider (StaffSignInPolicy), whose password is
 * refused even if a hash exists. Before this, the first kind could not
 * pass any "confirm your password" step until they set a password, and
 * the second kind cannot set one at all.
 *
 * The provider is offered only to those accounts. An account that has a
 * password keeps proving it with the password: a provider is not
 * necessarily as strong, and offering it as an alternative would make
 * every confirmation as weak as the weakest connected account.
 *
 * A confirmation, either way, is the session's `auth.password_confirmed_at`,
 * which the framework's password.confirm middleware already reads.
 */
class Reauthentication
{
    public const CONFIRMED_AT = 'auth.password_confirmed_at';

    public function __construct(private readonly StaffSignInPolicy $policy) {}

    /** Whether this account confirms with a password typed here. */
    public function hasPassword(User $user): bool
    {
        return $user->auth_source !== AuthSource::Social && ! $this->policy->refusesPassword($user);
    }

    /**
     * The provider this account confirms with instead, or null.
     *
     * The required one for staff under StaffSignInPolicy; otherwise the
     * first usable provider the account is connected to. Null for an
     * account with a password, and for one with no usable connection,
     * which can only set a password by email.
     */
    public function provider(User $user): ?SocialProvider
    {
        if ($this->hasPassword($user)) {
            return null;
        }

        $required = $this->policy->providerFor($user);

        foreach ($required !== null ? [$required] : SocialProvider::cases() as $provider) {
            if (SocialSettings::for($provider)->usable() && $this->isConnected($user, $provider)) {
                return $provider;
            }
        }

        return null;
    }

    public function isConnected(User $user, SocialProvider $provider): bool
    {
        return SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider->value)
            ->exists();
    }

    /**
     * What a confirmation screen needs to know about this account.
     *
     * @return array{has_password: bool, confirm_with: array{provider: string, label: string}|null}
     */
    public function screen(User $user): array
    {
        $provider = $this->provider($user);

        return [
            'has_password' => $this->hasPassword($user),
            'confirm_with' => $provider === null ? null : [
                'provider' => $provider->value,
                'label' => $provider->label(),
            ],
        ];
    }

    /**
     * Whether this session confirmed recently enough to count, by the
     * same timeout password.confirm uses.
     */
    public function recentlyConfirmed(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get(self::CONFIRMED_AT, 0);

        return time() - $confirmedAt < (int) config('auth.password_timeout', 10800);
    }

    public function markConfirmed(Request $request): void
    {
        $request->session()->put(self::CONFIRMED_AT, time());
    }
}
