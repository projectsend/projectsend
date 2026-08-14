<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * An OpenID Connect server describing itself.
 *
 * The reason this application can speak to Keycloak, Authentik, Authelia,
 * Okta, Auth0 and Entra without a class for each: every one of them
 * publishes its endpoints at a well-known path, so the entire
 * configuration of an unknown server is its issuer URL.
 *
 * HTTPS only, and not negotiable. The document names the URL we will send
 * a person's browser to and the URL we will post a client secret to; over
 * plaintext, anyone on the path chooses both.
 */
class OidcDiscovery
{
    /**
     * Endpoints change on the order of never, but an administrator who
     * has just fixed a typo should not wait a day to find out. A short
     * life plus an explicit flush when settings are saved covers both.
     */
    private const TTL_SECONDS = 3600;

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: ?string, supports_pkce: bool}
     */
    public function for(string $issuerUrl): array
    {
        $issuer = rtrim(trim($issuerUrl), '/');

        if (! str_starts_with($issuer, 'https://')) {
            throw new RuntimeException('An OpenID Connect issuer must be an https:// URL.');
        }

        /** @var array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: ?string, supports_pkce: bool} */
        return Cache::remember(
            self::cacheKey($issuer),
            self::TTL_SECONDS,
            fn (): array => $this->fetch($issuer),
        );
    }

    public function forget(string $issuerUrl): void
    {
        Cache::forget(self::cacheKey(rtrim(trim($issuerUrl), '/')));
    }

    private static function cacheKey(string $issuer): string
    {
        return 'identity.oidc.discovery.v1.'.sha1($issuer);
    }

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: ?string, supports_pkce: bool}
     */
    private function fetch(string $issuer): array
    {
        $url = $issuer.'/.well-known/openid-configuration';

        // Short, because this runs inside a person's click on "Sign in".
        // A provider that has stopped answering must fail the login, not
        // hold the worker — the same reasoning as LdapAuthenticator's
        // circuit breaker.
        $response = Http::timeout(5)->connectTimeout(5)->acceptJson()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("The OpenID Connect discovery document at {$url} could not be read.");
        }

        $document = $response->json();

        if (! is_array($document)) {
            throw new RuntimeException("The OpenID Connect discovery document at {$url} is not JSON.");
        }

        foreach (['authorization_endpoint', 'token_endpoint'] as $required) {
            if (! is_string($document[$required] ?? null) || $document[$required] === '') {
                throw new RuntimeException("The OpenID Connect discovery document at {$url} has no {$required}.");
            }
        }

        $methods = $document['code_challenge_methods_supported'] ?? [];

        return [
            'authorization_endpoint' => $document['authorization_endpoint'],
            'token_endpoint' => $document['token_endpoint'],
            'userinfo_endpoint' => is_string($document['userinfo_endpoint'] ?? null)
                ? $document['userinfo_endpoint']
                : null,
            'supports_pkce' => is_array($methods) && in_array('S256', $methods, true),
        ];
    }
}
