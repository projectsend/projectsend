<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use App\Models\User;

/**
 * Which local account, if any, a provider identity signs in as.
 *
 * This class is the feature. Everything else moves bytes; this decides
 * whether an assertion made by a third party is allowed to become access
 * to somebody's files, and it is the exact thing v1 got wrong:
 *
 *     SELECT * FROM users WHERE user = :email OR email = :email
 *
 * — whatever address the provider returned, matched against two columns,
 * with no check that the provider had ever verified it and no restriction
 * on the account type it landed on. An identity provider permitting self
 * registration was therefore enough to become an administrator here.
 *
 * The order below is what replaces it. Read it as: the *subject* is the
 * identity; the email is only ever a one-time introduction, and only from
 * a provider willing to vouch for it.
 */
class SocialAuthenticator
{
    public function __construct(
        private readonly SocialProvisioner $provisioner,
    ) {}

    public function resolve(SocialSettings $settings, SocialIdentity $identity): SocialResolution
    {
        // 1. A provider that is off, or half-configured, behaves exactly
        //    as one that was never added.
        if (! $settings->usable()) {
            return SocialResolution::refuse(__('That sign-in method is not available.'));
        }

        // 2. Nothing here can be keyed on an identity with no address.
        if ($identity->email === null) {
            return SocialResolution::refuse(
                __(':provider did not provide an email address, which is required to sign in.', [
                    'provider' => $settings->provider->label(),
                ])
            );
        }

        if (! $settings->allowsDomain($identity->email)) {
            return SocialResolution::refuse(
                __('Accounts at :domain cannot sign in with :provider here.', [
                    'domain' => substr($identity->email, (int) strrpos($identity->email, '@') + 1),
                    'provider' => $settings->provider->label(),
                ])
            );
        }

        // 3. An existing link. The subject, not the address — so somebody
        //    who changes their email at the provider still lands on their
        //    own account, and somebody who changes it *to yours* does not
        //    land on yours.
        $linked = SocialAccount::resolve($identity);

        if ($linked !== null) {
            $this->refreshLink($identity);

            return SocialResolution::existing($linked);
        }

        // Whether this provider's word on the address is good enough to
        // act on. `require_verified_email` off is an administrator
        // explicitly accepting that it is not — the escape hatch for a
        // directory that omits the claim.
        $trusted = $identity->emailVerified || ! $settings->require_verified_email;

        $existing = User::query()->where('email', $identity->email)->first();

        if ($existing !== null) {
            // 4/5. The takeover, refused. An unverified address may not
            //      reach an account that already exists — of any type, but
            //      note that an administrator's is the interesting case.
            if (! $trusted) {
                return SocialResolution::refuse(
                    __('An account already uses this email address, and :provider did not confirm that you own it. Sign in with your password and connect :provider from your settings instead.', [
                        'provider' => $settings->provider->label(),
                    ])
                );
            }

            return $this->link($existing, $identity) === null
                ? SocialResolution::refuse(__('That sign-in method is not available.'))
                : SocialResolution::linked($existing);
        }

        // 6. Nobody here at all.
        $provisioned = $this->provisioner->provision(
            $settings,
            $identity,
            // An address nobody vouched for can still become a *new*
            // account — it takes nothing over — but it goes to the
            // approval queue whatever auto_approve says, so a person sees
            // it before it becomes access.
            autoApprove: $trusted && $settings->auto_approve,
        );

        if ($provisioned === null) {
            return SocialResolution::refuse(__('There is no account here for that address.'));
        }

        $this->link($provisioned, $identity);

        return SocialResolution::provisioned($provisioned);
    }

    /**
     * Bind an identity to an account, deliberately and once.
     *
     * Also reachable from the Connected accounts screen, where the person
     * is already signed in and the address plays no part at all — which
     * is the safest way to connect a provider that cannot verify one.
     *
     * Returns null when this identity already belongs to somebody else.
     * Moving it would not hand over their account, since whoever holds
     * the identity can already use it, but silently detaching another
     * person's sign-in method is not something a login flow should do
     * without saying so.
     */
    public function link(User $user, SocialIdentity $identity): ?SocialAccount
    {
        $boundElsewhere = SocialAccount::query()
            ->where('provider', $identity->provider->value)
            ->where('provider_user_id', $identity->subject)
            ->where('user_id', '!=', $user->getKey())
            ->exists();

        if ($boundElsewhere) {
            return null;
        }

        // Keyed on the account and provider, so reconnecting a different
        // Google account replaces the old row rather than colliding with
        // the one-link-per-provider constraint.
        /** @var SocialAccount */
        return SocialAccount::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'provider' => $identity->provider->value,
            ],
            [
                'provider_user_id' => $identity->subject,
                'email' => $identity->email,
            ],
        );
    }

    /**
     * Keep the displayed address current without letting it mean
     * anything: this is what the Connected accounts screen shows, not
     * what any decision is made on.
     */
    private function refreshLink(SocialIdentity $identity): void
    {
        SocialAccount::query()
            ->where('provider', $identity->provider->value)
            ->where('provider_user_id', $identity->subject)
            ->update(['email' => $identity->email, 'updated_at' => now()]);
    }
}
