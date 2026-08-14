<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use Throwable;

/**
 * Any standards-compliant OpenID Connect server.
 *
 * Serves both the Generic OIDC entry — Keycloak, Authentik, Authelia,
 * Dex, Okta, Auth0 — and Microsoft Entra, which is an OIDC server like
 * the rest once its issuer is built from the tenant. One implementation
 * instead of a socialiteproviders/* package per vendor.
 *
 * ## Where the claims come from, and why that is safe
 *
 * The identity is assembled from the **ID token** returned by the token
 * endpoint, merged over whatever the userinfo endpoint adds. We do not
 * verify the ID token's signature, and that is deliberate rather than
 * missing: the token did not come from the browser. It arrived in the
 * response body of a direct, server-to-server, TLS-authenticated POST to
 * the token endpoint named by the issuer's own discovery document, in
 * exchange for a single-use code bound to our client secret. Signature
 * verification defends against a token that travelled through an
 * untrusted party; none did.
 *
 * The claims that matter — `sub`, `email_verified`, and Entra's `tid` —
 * are all in the ID token, which is the reason for reading it at all:
 * Entra's userinfo endpoint does not return `tid`, and without `tid`
 * there is no way to tell a token minted by your tenant from one minted
 * by an attacker's.
 */
class OidcProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * @var array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: ?string, supports_pkce: bool}
     */
    protected array $discovery;

    /** @var list<string> */
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    /** @var array<string, mixed> */
    protected array $idTokenClaims = [];

    /**
     * @param  array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: ?string, supports_pkce: bool}  $discovery
     */
    public function __construct(
        Request $request,
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        array $discovery,
    ) {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);

        $this->discovery = $discovery;

        // PKCE binds the authorization code to this browser session, so a
        // code leaked out of a redirect — a referrer header, a proxy log,
        // a shared machine's history — cannot be redeemed by anyone else.
        // Only when the server says it supports S256; offering a
        // code_challenge to a server that ignores it buys nothing and
        // breaks some.
        if ($discovery['supports_pkce']) {
            $this->enablePKCE();
        }
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->discovery['authorization_endpoint'], $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->discovery['token_endpoint'];
    }

    /**
     * @param  string  $code
     * @return mixed
     */
    public function getAccessTokenResponse($code)
    {
        $response = parent::getAccessTokenResponse($code);

        $this->idTokenClaims = is_array($response)
            ? $this->claimsFromIdToken(Arr::get($response, 'id_token'))
            : [];

        return $response;
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        // ID token claims win: they are the ones the issuer signed and
        // the ones this application makes decisions about.
        return array_merge($this->userinfo($token), $this->idTokenClaims);
    }

    /**
     * @return array<string, mixed>
     */
    private function userinfo(string $token): array
    {
        $endpoint = $this->discovery['userinfo_endpoint'];

        if ($endpoint === null) {
            return [];
        }

        try {
            $response = $this->getHttpClient()->get($endpoint, [
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                ],
            ]);
        } catch (Throwable) {
            // An ID token alone is a complete identity. A server whose
            // userinfo endpoint is unreachable or scope-restricted should
            // not fail the login over profile decoration.
            return [];
        }

        $claims = json_decode((string) $response->getBody(), true);

        return is_array($claims) ? $claims : [];
    }

    /**
     * The payload of a JWT, without verifying its signature — see the
     * class docblock for why that is sound here and nowhere else.
     *
     * @return array<string, mixed>
     */
    private function claimsFromIdToken(mixed $idToken): array
    {
        if (! is_string($idToken)) {
            return [];
        }

        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($segments[1], '-_', '+/'), true);

        if ($payload === false) {
            return [];
        }

        $claims = json_decode($payload, true);

        return is_array($claims) ? $claims : [];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'sub'),
            'nickname' => Arr::get($user, 'preferred_username'),
            'name' => Arr::get($user, 'name') ?? trim(
                (string) Arr::get($user, 'given_name').' '.(string) Arr::get($user, 'family_name')
            ),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'picture'),
        ]);
    }
}
