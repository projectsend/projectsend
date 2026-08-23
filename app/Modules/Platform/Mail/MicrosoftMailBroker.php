<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Microsoft identity platform (v2.0) tokens for sending through Graph.
 *
 * Delegated flow on purpose: an administrator signs into the mailbox the
 * installation should send as, and the resulting token can send as that
 * mailbox and nothing else — no admin consent, no PowerShell
 * ApplicationAccessPolicy, and it works for work/school and personal
 * accounts alike. The price of delegated is that the grant can die
 * behind our back (password reset, Conditional Access change), which is
 * why refresh() records `last_error` for the health check to surface
 * instead of letting mail stop silently.
 *
 * Plain HTTP against the token endpoint rather than an SDK — the
 * project ships no Graph/Google SDKs and two POST requests do not
 * justify one.
 */
class MicrosoftMailBroker implements MailOAuthBroker
{
    /**
     * Mail.Send is the one Graph permission sending needs; offline_access
     * buys the refresh token; openid/profile/email buy the id_token this
     * class reads the connected mailbox's address from — which is what
     * lets the whole flow avoid User.Read and a Graph /me call entirely.
     */
    private const SCOPE = 'offline_access openid profile email https://graph.microsoft.com/Mail.Send';

    /** Refresh when the access token has less life left than this. */
    private const EXPIRY_MARGIN_SECONDS = 120;

    public function authorizeUrl(MailOAuthConnection $connection, string $state, string $redirectUri): string
    {
        // select_account, always: the admin doing this is often signed
        // into their own mailbox, and the one the installation should
        // send as (noreply@, portal@) is usually a different one.
        return 'https://login.microsoftonline.com/'.$this->tenant($connection).'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => (string) $connection->client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => self::SCOPE,
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    public function exchange(MailOAuthConnection $connection, string $code, string $redirectUri): void
    {
        $response = Http::asForm()->post($this->tokenEndpoint($connection), [
            'client_id' => (string) $connection->client_id,
            'client_secret' => (string) $connection->client_secret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'scope' => self::SCOPE,
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
            'scope' => self::SCOPE,
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

    private function tenant(MailOAuthConnection $connection): string
    {
        $tenant = $connection->tenant_id;

        return is_string($tenant) && trim($tenant) !== '' ? trim($tenant) : 'common';
    }

    private function tokenEndpoint(MailOAuthConnection $connection): string
    {
        return 'https://login.microsoftonline.com/'.$this->tenant($connection).'/oauth2/v2.0/token';
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

        // Microsoft rotates the refresh token on every use; a response
        // without one (some resource-scoped edge cases) keeps the old.
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
     * The signed-in mailbox's address, read from the id_token's claims.
     *
     * Deliberately without signature verification: this token arrived in
     * the token endpoint's own TLS response — not from the browser — and
     * feeds a display/From value, not an authentication decision. That
     * is the trade that lets sending work with Mail.Send alone.
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
            : (is_string($error) && $error !== '' ? $error : 'The Microsoft token endpoint answered HTTP '.$response->status().'.');

        // invalid_grant covers everything that kills a delegated grant:
        // revoked consent, password reset, Conditional Access changes, an
        // expired refresh token. invalid_client means the app
        // registration itself (secret expired?) — also unfixable by retry.
        $needsReconnect = in_array($error, ['invalid_grant', 'invalid_client'], true);

        return new MailOAuthException($message, needsReconnect: $needsReconnect);
    }
}
