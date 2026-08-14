<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\LinkedInOpenIdProvider;

/**
 * The providers this installation knows how to talk to.
 *
 * A closed enum rather than an open registry, because each case makes a
 * different claim about how trustworthy the email address it hands back
 * is, and that claim is code — see SocialIdentity. A provider nobody has
 * reasoned about must not be configurable.
 *
 * Google, Microsoft and OpenID Connect carry the business case; Facebook,
 * LinkedIn and GitHub are parity with v1's provider list, minus the three
 * (X, Yahoo, Windows Live) that were dead weight in a file-sharing tool.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin';
    case Github = 'github';
    case Oidc = 'oidc';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Microsoft => 'Microsoft',
            self::Facebook => 'Facebook',
            self::LinkedIn => 'LinkedIn',
            self::Github => 'GitHub',
            self::Oidc => 'OpenID Connect',
        };
    }

    /**
     * Whether this case is served by our own discovery-driven OIDC
     * provider rather than one of Socialite's.
     *
     * Microsoft Entra is an OpenID Connect server like any other, so it
     * gets the same implementation with its issuer built from the tenant.
     * That avoids a socialiteproviders/* dependency for a protocol we
     * already speak, and it is what lets `tid` be read at all.
     */
    public function speaksOidc(): bool
    {
        return $this === self::Microsoft || $this === self::Oidc;
    }

    /**
     * @return class-string<AbstractProvider>
     */
    public function driver(): string
    {
        return match ($this) {
            self::Google => GoogleProvider::class,
            self::Facebook => FacebookProvider::class,
            self::LinkedIn => LinkedInOpenIdProvider::class,
            self::Github => GithubProvider::class,
            self::Microsoft, self::Oidc => OidcProvider::class,
        };
    }

    /** Generic OIDC is configured entirely by its discovery document. */
    public function needsIssuerUrl(): bool
    {
        return $this === self::Oidc;
    }

    /**
     * Microsoft, and only Microsoft, requires a tenant — see
     * SocialIdentity for why that is a security control rather than a
     * convenience.
     */
    public function needsTenantId(): bool
    {
        return $this === self::Microsoft;
    }

    /**
     * Whether this provider can ever tell us an address was verified.
     *
     * False here does not disable the provider; it means the address can
     * never be used to reach an *existing* account while
     * `require_verified_email` is on. The settings screen says so on the
     * card, because an administrator who does not know this will read the
     * checkbox as doing nothing.
     */
    public function canReportVerifiedEmail(): bool
    {
        return $this !== self::Facebook;
    }

    /** The URI to register with the provider. Shown on the settings card. */
    public function redirectUri(): string
    {
        return route('social.callback', ['provider' => $this->value]);
    }
}
