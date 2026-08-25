<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The authorization-code machinery every OAuth mail provider shares:
 * exchanging the callback's code, refreshing on demand, storing what
 * came back. Providers differ only in their endpoints, their scope
 * string, and how the consent URL is parameterized — which is exactly
 * the surface the abstract methods cover.
 *
 * Plain HTTP against the token endpoints rather than vendor SDKs — the
 * project ships none, and two POST requests per provider do not justify
 * one.
 */
abstract class OAuthCodeFlowBroker implements MailOAuthBroker
{
    /** Refresh when the access token has less life left than this. */
    private const EXPIRY_MARGIN_SECONDS = 120;

    abstract public function authorizeUrl(MailOAuthConnection $connection, string $state, string $redirectUri): string;

    /** The provider's OAuth token endpoint for this connection. */
    abstract protected function tokenEndpoint(MailOAuthConnection $connection): string;

    /** The scope string this provider's tokens are requested with. */
    abstract protected function scope(): string;

    public function exchange(MailOAuthConnection $connection, string $code, string $redirectUri): void
    {
        $response = Http::asForm()->post($this->tokenEndpoint($connection), [
            'client_id' => (string) $connection->client_id,
            'client_secret' => (string) $connection->client_secret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'scope' => $this->scope(),
        ]);

        if ($response->failed()) {
            throw $this->failure($response);
        }

        $this->storeTokens($connection, $response);
    }

    public function refresh(MailOAuthConnection $connection): void
    {
        $refreshToken = $connection->refresh_token;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new MailOAuthException('No mailbox is connected.', needsReconnect: true);
        }

        $response = Http::asForm()->post($this->tokenEndpoint($connection), [
            'client_id' => (string) $connection->client_id,
            'client_secret' => (string) $connection->client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $this->scope(),
        ]);

        if ($response->failed()) {
            $failure = $this->failure($response);

            // Only a dead grant is worth alarming the admin over; a
            // transient endpoint problem heals on the next attempt and
            // must not paint the settings page red in the meantime.
            if ($failure->needsReconnect) {
                $connection->last_error = $failure->getMessage();
                $connection->save();
            }

            throw $failure;
        }

        $this->storeTokens($connection, $response);
    }

    public function freshAccessToken(MailOAuthConnection $connection): string
    {
        $token = $connection->access_token;
        $expiresAt = $connection->token_expires_at;

        if (is_string($token) && $token !== '' && $expiresAt !== null && $expiresAt->gt(now()->addSeconds(self::EXPIRY_MARGIN_SECONDS))) {
            return $token;
        }

        $this->refresh($connection);

        return (string) $connection->access_token;
    }

    private function storeTokens(MailOAuthConnection $connection, Response $response): void
    {
        $accessToken = $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new MailOAuthException('The token response did not include an access token.');
        }

        $connection->access_token = $accessToken;
        $connection->token_expires_at = now()->addSeconds(is_numeric($expiresIn) ? (int) $expiresIn : 3600);

        // Microsoft rotates the refresh token on every use; Google hands
        // one out only on the initial consent. Same rule covers both: a
        // response without one keeps what is already stored.
        $newRefreshToken = $response->json('refresh_token');
        if (is_string($newRefreshToken) && $newRefreshToken !== '') {
            $connection->refresh_token = $newRefreshToken;
        }

        $email = $this->emailFromIdToken($response->json('id_token'));
        if ($email !== null) {
            $connection->account_email = $email;
        }

        $connection->last_refreshed_at = now();
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * The signed-in mailbox's address, read from the id_token's claims
     * (`preferred_username` on Microsoft, `email` on Google).
     *
     * Deliberately without signature verification: this token arrived in
     * the token endpoint's own TLS response — not from the browser — and
     * feeds a display/From value, not an authentication decision. That
     * is the trade that lets sending work with the send scope alone,
     * with no userinfo permission.
     */
    private function emailFromIdToken(mixed $idToken): ?string
    {
        if (! is_string($idToken) || substr_count($idToken, '.') !== 2) {
            return null;
        }

        [, $payload] = explode('.', $idToken);

        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $claims = json_decode($decoded, true);

        if (! is_array($claims)) {
            return null;
        }

        foreach (['preferred_username', 'email'] as $claim) {
            $value = $claims[$claim] ?? null;

            if (is_string($value) && str_contains($value, '@')) {
                return $value;
            }
        }

        return null;
    }

    private function failure(Response $response): MailOAuthException
    {
        $error = $response->json('error');
        $description = $response->json('error_description');

        $message = is_string($description) && $description !== ''
            ? $description
            : (is_string($error) && $error !== '' ? $error : 'The token endpoint answered HTTP '.$response->status().'.');

        // Both vendors speak RFC 6749 here. invalid_grant covers
        // everything that kills a grant: revoked consent, a password
        // reset or Conditional Access change (Microsoft), the 7-day
        // testing-status expiry (Google). invalid_client means the app
        // registration itself (secret expired?) — also unfixable by
        // retry.
        $needsReconnect = in_array($error, ['invalid_grant', 'invalid_client'], true);

        return new MailOAuthException($message, needsReconnect: $needsReconnect);
    }
}
