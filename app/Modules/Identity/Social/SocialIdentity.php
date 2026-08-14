<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use Laravel\Socialite\AbstractUser as SocialiteUser;

/**
 * An identity a provider has just asserted.
 *
 * Only the four things this app acts on. Everything else a provider knows
 * about a person stays with the provider.
 *
 * `emailVerified` is the security-critical field, and it is computed
 * here — once, per provider — rather than read from a claim that may not
 * exist. What it means is narrow and worth stating exactly: *this
 * provider is willing to say the person signing in controls this
 * address.* It is what licenses binding the identity to an account that
 * already exists, which is where v1 was taken over.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}

    /**
     * Read a Socialite user, applying each provider's own answer to "is
     * this address verified?".
     *
     * | Provider | Basis |
     * |---|---|
     * | Google | `email_verified` claim |
     * | LinkedIn | `email_verified` claim |
     * | OpenID Connect | `email_verified` claim, from the ID token |
     * | GitHub | An address at all — Socialite's GithubProvider replaces `email` with the result of `getEmailByToken()`, which only ever returns one that is **primary and verified** |
     * | Microsoft | The token's `tid` matching the configured tenant. Entra does not emit a usable `email_verified`, and its `email` claim is user-mutable — pinning the tenant is what makes it mean anything (this is the *nOAuth* class of bug) |
     * | Facebook | Nothing. The Graph API has no equivalent claim, so an address from Facebook is never treated as verified |
     */
    public static function fromSocialite(
        SocialProvider $provider,
        SocialiteUser $user,
        SocialSettings $settings,
    ): ?self {
        $raw = $user->getRaw();

        $subject = trim((string) $user->getId());

        // No stable subject means nothing to bind an account to, and the
        // email is not an acceptable substitute. Refuse rather than fall
        // back — falling back is the bug.
        if ($subject === '') {
            return null;
        }

        $email = $user->getEmail();
        $email = is_string($email) && trim($email) !== '' ? trim($email) : null;

        return new self(
            provider: $provider,
            subject: $subject,
            email: $email,
            emailVerified: $email !== null && match ($provider) {
                SocialProvider::Google,
                SocialProvider::LinkedIn,
                SocialProvider::Oidc => ($raw['email_verified'] ?? false) === true
                    || ($raw['email_verified'] ?? null) === 'true',
                SocialProvider::Github => true,
                SocialProvider::Microsoft => is_string($settings->tenant_id)
                    && ($raw['tid'] ?? null) === $settings->tenant_id,
                SocialProvider::Facebook => false,
            },
            name: is_string($user->getName()) && trim($user->getName()) !== ''
                ? trim($user->getName())
                : ($user->getNickname() ?: $email),
        );
    }
}
