<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * The production gateway: Socialite, configured from the database.
 *
 * Credentials are read per request from `social_login_providers` and
 * handed to `Socialite::buildProvider()`, so nothing ever reaches
 * `config/services.php` or `.env`. That is what lets an administrator
 * configure a provider from the settings screen instead of a deploy, and
 * it keeps the secret in exactly one place — an encrypted column.
 *
 * `stateless()` is never called. The OAuth `state` parameter is the CSRF
 * defence for a redirect flow, and Socialite only checks it when the
 * session holds the value it wrote.
 */
class SocialiteGateway implements SocialGateway
{
    public function __construct(
        private readonly OidcDiscovery $discovery,
    ) {}

    public function redirect(SocialSettings $settings): RedirectResponse
    {
        return $this->driver($settings)->redirect();
    }

    public function identity(SocialSettings $settings): ?SocialIdentity
    {
        try {
            $user = $this->driver($settings)->user();
        } catch (Throwable $e) {
            // Never the message: a provider's error text can carry the
            // address that was attempted, and this is written on an
            // unauthenticated request. The class is enough to tell a
            // state mismatch from a dead token endpoint.
            Log::warning('Social login failed.', [
                'provider' => $settings->provider->value,
                'exception' => $e::class,
            ]);

            return null;
        }

        // Socialite's contract does not expose getRaw(), and the raw claims
        // are where `email_verified` and `tid` live — the two things this
        // whole feature turns on. Every provider returns the concrete
        // class; refuse rather than guess if one ever does not.
        if (! $user instanceof AbstractUser) {
            return null;
        }

        return SocialIdentity::fromSocialite($settings->provider, $user, $settings);
    }

    private function driver(SocialSettings $settings): AbstractProvider
    {
        $provider = $settings->provider;

        // Resolved per call, never held: a Socialite provider reads the
        // current request's `code` and `state`, and writes to the current
        // session. Holding the request the gateway was built with would
        // make the object correct exactly once.
        $request = app(Request::class);

        if ($provider->speaksOidc()) {
            $issuer = $settings->issuer();

            // usable() has already established this, but the type system
            // has not.
            if ($issuer === null) {
                throw new \RuntimeException('This provider has no issuer configured.');
            }

            return new OidcProvider(
                $request,
                (string) $settings->client_id,
                (string) $settings->client_secret,
                $provider->redirectUri(),
                $this->discovery->for($issuer),
            );
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::buildProvider($provider->driver(), [
            'client_id' => (string) $settings->client_id,
            'client_secret' => (string) $settings->client_secret,
            'redirect' => $provider->redirectUri(),
        ]);

        return $driver;
    }
}
