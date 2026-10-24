<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Mcp\OAuth\Models\McpAuthorizationCode;
use App\Modules\Mcp\OAuth\Models\McpClient;
use App\Modules\Mcp\OAuth\Models\McpConnection;
use App\Modules\Mcp\Support\Audit;
use App\Modules\Mcp\Support\Connector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Codes, tokens and connections (design §4.3 and §5). The access token
 * is the app's; everything around it — the code that earns it, the
 * refresh that renews it, the family that dies when a refresh is
 * reused — is here.
 */
final class Grants
{
    public const ACCESS_LIFETIME_SECONDS = 3600;

    /**
     * The person approved: a code the client exchanges within a minute.
     *
     * @param  list<string>  $scopes
     * @return string the code, in clear, once
     */
    public function issueCode(AuthorizationRequest $request, User $user, array $scopes): string
    {
        $code = Pkce::secret();

        McpAuthorizationCode::query()->create([
            'code_hash' => McpAuthorizationCode::hashOf($code),
            'mcp_client_id' => $request->client->getKey(),
            'user_id' => $user->getKey(),
            'scopes' => $scopes,
            'code_challenge' => $request->codeChallenge,
            'redirect_uri' => $request->redirectUri,
            'resource' => $request->resource,
            'expires_at' => now()->addSeconds(McpAuthorizationCode::LIFETIME_SECONDS),
        ]);

        $this->audit(Action::ApiTokenCreated, $user, [
            'via' => 'oauth',
            'stage' => 'authorized',
            'client' => $request->client->name,
            'client_host' => $request->client->identity(),
            'scope' => implode(' ', $scopes),
        ]);

        return $code;
    }

    /**
     * `grant_type=authorization_code`.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthError
     */
    public function exchangeCode(string $code, string $clientId, string $redirectUri, string $verifier, ?string $resource): array
    {
        $record = $code !== '' ? McpAuthorizationCode::query()->where('code_hash', McpAuthorizationCode::hashOf($code))->first() : null;

        if ($record === null) {
            throw new OAuthError('invalid_grant', 'The authorization code is not valid.');
        }

        if ($record->used_at !== null) {
            // A code arriving twice was intercepted, and the first to use
            // it may not have been the legitimate client: what it earned
            // is withdrawn.
            $this->revokeIssuedBy($record, 'authorization code reused');

            throw new OAuthError('invalid_grant', 'The authorization code was already used.');
        }

        if ($record->isExpired()) {
            throw new OAuthError('invalid_grant', 'The authorization code has expired.');
        }

        $client = $record->client;

        if (! hash_equals($client->client_id, $clientId)
            || ! hash_equals($record->redirect_uri, $redirectUri)
            || ($resource !== null && ! hash_equals($record->resource, $resource))) {
            throw new OAuthError('invalid_grant', 'The client, redirect URI or resource does not match the authorization.');
        }

        if (! Pkce::verifies($verifier, $record->code_challenge)) {
            throw new OAuthError('invalid_grant', 'The code verifier does not match the challenge.');
        }

        $user = User::query()->find($record->user_id);

        if ($user === null) {
            throw new OAuthError('invalid_grant', 'The account that authorized this no longer exists.');
        }

        return DB::transaction(function () use ($record, $client, $user): array {
            $record->forceFill(['used_at' => now()])->save();

            $connection = new McpConnection([
                'mcp_client_id' => $client->getKey(),
                'user_id' => $user->getKey(),
                'scopes' => $record->scopes,
                'family' => (string) Str::uuid(),
            ]);

            $response = $this->mint($connection, $client, $user);

            $record->forceFill(['issued_token_id' => $connection->access_token_id])->save();

            return $response;
        });
    }

    /**
     * `grant_type=refresh_token`: rotation within a family, and a family
     * that ends the moment a refresh is presented twice.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthError
     */
    public function refresh(string $refreshToken, string $clientId, ?string $scope): array
    {
        $hash = $refreshToken !== '' ? McpConnection::hashOf($refreshToken) : null;

        if ($hash === null) {
            throw new OAuthError('invalid_grant', 'The refresh token is not valid.');
        }

        $reused = McpConnection::query()->where('previous_refresh_token_hash', $hash)->first();

        if ($reused !== null) {
            $this->revoke($reused, 'refresh token reuse');

            throw new OAuthError('invalid_grant', 'The refresh token was already used; the connection has been revoked.');
        }

        $connection = McpConnection::query()->where('refresh_token_hash', $hash)->first();

        if ($connection === null) {
            throw new OAuthError('invalid_grant', 'The refresh token is not valid.');
        }

        if ($connection->revoked_at !== null) {
            throw new OAuthError('invalid_grant', 'The connection was revoked.');
        }

        if ($connection->refresh_expires_at->isPast()) {
            $connection->forceFill(['revoked_at' => now(), 'revoked_reason' => 'refresh token expired'])->save();
            Tokens::delete($connection->access_token_id);

            throw new OAuthError('invalid_grant', 'The refresh token has expired. Connect the assistant again.');
        }

        $client = $connection->client;

        if (! hash_equals($client->client_id, $clientId)) {
            throw new OAuthError('invalid_grant', 'The client does not match the connection.');
        }

        if ($scope !== null && ! Scopes::covers($connection->scopes, Scopes::parse($scope))) {
            throw new OAuthError('invalid_scope', 'A refresh may not widen what was granted.');
        }

        $user = User::query()->find($connection->user_id);

        if ($user === null) {
            $this->revoke($connection, 'account gone');

            throw new OAuthError('invalid_grant', 'The account behind this connection no longer exists.');
        }

        return DB::transaction(function () use ($connection, $client, $user): array {
            Tokens::delete($connection->access_token_id);

            return $this->mint($connection, $client, $user);
        });
    }

    /**
     * RFC 7009: whatever the token is, the connection it belongs to
     * falls. Silent when it names nothing: nobody is told what exists.
     */
    public function revokeByToken(string $token, ?string $hint): void
    {
        if ($token === '') {
            return;
        }

        $hash = McpConnection::hashOf($token);
        $connection = McpConnection::query()->where('refresh_token_hash', $hash)->orWhere('previous_refresh_token_hash', $hash)->first();

        if ($connection === null && $hint !== 'refresh_token') {
            $tokenId = Tokens::idOf($token);

            if ($tokenId !== null) {
                $connection = McpConnection::query()->where('access_token_id', $tokenId)->first();

                if ($connection === null) {
                    // An access token this server issued and lost track
                    // of, or a personal token somebody chose to revoke
                    // through here: gone either way.
                    Tokens::delete($tokenId);
                }
            }
        }

        if ($connection !== null) {
            $this->revoke($connection, 'revoked by the client');
        }
    }

    public function revoke(McpConnection $connection, string $reason): void
    {
        Tokens::delete($connection->access_token_id);

        if ($connection->revoked_at === null) {
            $connection->forceFill([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'access_token_id' => null,
            ])->save();

            $user = User::query()->find($connection->user_id);

            if ($user !== null) {
                $this->audit(Action::ApiTokenRevoked, $user, [
                    'via' => 'oauth',
                    'client' => $connection->client->name,
                    'reason' => $reason,
                ]);
            }
        }
    }

    /**
     * A new access token and a new refresh token on the connection, in
     * one place for the first issue and every rotation.
     *
     * @return array<string, mixed>
     */
    private function mint(McpConnection $connection, McpClient $client, User $user): array
    {
        $refresh = Pkce::secret();
        $expiresAt = now()->addSeconds(self::ACCESS_LIFETIME_SECONDS);

        $token = Tokens::mint($user, Connector::TOKEN_PREFIX.$client->name, $connection->scopes, $expiresAt);

        $connection->forceFill([
            'access_token_id' => $token['id'],
            'previous_refresh_token_hash' => $connection->refresh_token_hash,
            'refresh_token_hash' => McpConnection::hashOf($refresh),
            'refresh_expires_at' => now()->addDays(McpConnection::REFRESH_LIFETIME_DAYS),
            'last_used_at' => now(),
        ])->save();

        return [
            'access_token' => $token['plain'],
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_LIFETIME_SECONDS,
            'refresh_token' => $refresh,
            'scope' => implode(' ', $connection->scopes),
        ];
    }

    private function revokeIssuedBy(McpAuthorizationCode $record, string $reason): void
    {
        if ($record->issued_token_id === null) {
            return;
        }

        $connection = McpConnection::query()->where('access_token_id', $record->issued_token_id)->first();

        if ($connection !== null) {
            $this->revoke($connection, $reason);
        } else {
            Tokens::delete($record->issued_token_id);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(Action $action, User $user, array $context): void
    {
        Audit::logFor($action, $user, null, $context);
    }
}
