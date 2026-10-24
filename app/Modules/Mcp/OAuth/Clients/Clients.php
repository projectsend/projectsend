<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Clients;

use App\Modules\Mcp\OAuth\Models\McpClient;
use App\Modules\Mcp\OAuth\OAuthError;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * How an assistant comes to be known here (design §3): by the document
 * it publishes at the URL that is its `client_id`, cached; or, for a
 * client that has no such document yet, by registering itself, which
 * is an open write and is treated as one.
 */
final class Clients
{
    public const MAX_REDIRECT_URIS = 10;

    public function __construct(private readonly ClientMetadataFetcher $fetcher) {}

    /** A `client_id` that is a URL names a metadata document. */
    public static function isMetadataUrl(string $clientId): bool
    {
        $parts = parse_url($clientId);

        return $parts !== false
            && strtolower($parts['scheme'] ?? '') === 'https'
            && isset($parts['host']) && $parts['host'] !== ''
            && isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/'
            && ! isset($parts['fragment']) && ! isset($parts['user']) && ! isset($parts['pass']);
    }

    /**
     * The client a request names, or null when there is no such client.
     * A metadata document is read on first sight and again when its
     * cache runs out; a registered client is looked up.
     */
    public function resolve(string $clientId): ?McpClient
    {
        if ($clientId === '' || strlen($clientId) > 2048) {
            return null;
        }

        $known = McpClient::findByClientId($clientId);

        if (! self::isMetadataUrl($clientId)) {
            return $known?->kind === McpClient::KIND_DYNAMIC ? $known : null;
        }

        if ($known !== null && $known->metadata_expires_at !== null && $known->metadata_expires_at->isFuture()) {
            return $known;
        }

        try {
            ['document' => $document, 'ttl' => $ttl] = $this->fetcher->fetch($clientId);
            $attributes = $this->fromDocument($clientId, $document);
        } catch (ClientMetadataUnavailable $e) {
            Log::info('The AI connector could not read a client metadata document.', ['client_id' => $clientId, 'reason' => $e->getMessage()]);

            // A document that was readable once and is not right now:
            // the last good copy stands until it can be read again.
            return $known;
        }

        $attributes['metadata_fetched_at'] = now();
        $attributes['metadata_expires_at'] = now()->addSeconds($ttl);

        if ($known !== null) {
            $known->fill($attributes)->save();

            return $known;
        }

        return McpClient::query()->create($attributes + [
            'client_id' => $clientId,
            'client_id_hash' => McpClient::hashOf($clientId),
            'kind' => McpClient::KIND_METADATA_DOCUMENT,
        ]);
    }

    /**
     * Dynamic registration, RFC 7591: public clients only, the two grant
     * types this server has, and a repeated registration finds the row
     * it already made.
     *
     * @param  array<string, mixed>  $input
     * @return array{client: McpClient, created: bool}
     *
     * @throws OAuthError
     */
    public function register(array $input): array
    {
        $redirectUris = $this->redirectUrisFrom($input['redirect_uris'] ?? null, 'invalid_redirect_uri');
        $name = $this->nameFrom($input['client_name'] ?? null, $redirectUris[0]);

        $authMethod = $input['token_endpoint_auth_method'] ?? 'none';

        if ($authMethod !== 'none') {
            throw new OAuthError('invalid_client_metadata', 'Only public clients register here: token_endpoint_auth_method must be "none".');
        }

        foreach ((array) ($input['grant_types'] ?? ['authorization_code']) as $grant) {
            if (! in_array($grant, ['authorization_code', 'refresh_token'], true)) {
                throw new OAuthError('invalid_client_metadata', 'Supported grant types are authorization_code and refresh_token.');
            }
        }

        foreach ((array) ($input['response_types'] ?? ['code']) as $type) {
            if ($type !== 'code') {
                throw new OAuthError('invalid_client_metadata', 'The only supported response type is code.');
            }
        }

        $fingerprint = hash('sha256', json_encode([$name, $redirectUris], JSON_THROW_ON_ERROR));
        $existing = McpClient::query()->where('kind', McpClient::KIND_DYNAMIC)->where('fingerprint', $fingerprint)->first();

        if ($existing !== null) {
            return ['client' => $existing, 'created' => false];
        }

        $clientId = (string) Str::uuid();

        $client = McpClient::query()->create([
            'client_id' => $clientId,
            'client_id_hash' => McpClient::hashOf($clientId),
            'kind' => McpClient::KIND_DYNAMIC,
            'name' => $name,
            'client_uri' => $this->httpsUrlFrom($input['client_uri'] ?? null),
            'logo_uri' => $this->httpsUrlFrom($input['logo_uri'] ?? null),
            'redirect_uris' => $redirectUris,
            'metadata' => $input,
            'fingerprint' => $fingerprint,
        ]);

        return ['client' => $client, 'created' => true];
    }

    public function redirectMatches(McpClient $client, string $presented): bool
    {
        foreach ($client->redirect_uris as $registered) {
            if (RedirectUris::matches($registered, $presented)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the document says, held to the same rules as a registration,
     * plus the one that makes it an identity: it names itself by the URL
     * it was read from.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     *
     * @throws ClientMetadataUnavailable
     */
    private function fromDocument(string $clientId, array $document): array
    {
        if (($document['client_id'] ?? null) !== $clientId) {
            throw new ClientMetadataUnavailable('The document does not name the URL it was read from as its client_id.');
        }

        try {
            $redirectUris = $this->redirectUrisFrom($document['redirect_uris'] ?? null, 'invalid_client_metadata');
        } catch (OAuthError $e) {
            throw new ClientMetadataUnavailable($e->description);
        }

        return [
            'name' => $this->nameFrom($document['client_name'] ?? null, $clientId),
            'client_uri' => $this->httpsUrlFrom($document['client_uri'] ?? null),
            'logo_uri' => $this->httpsUrlFrom($document['logo_uri'] ?? null),
            'redirect_uris' => $redirectUris,
            'metadata' => $document,
        ];
    }

    /**
     * @return non-empty-list<string>
     *
     * @throws OAuthError
     */
    private function redirectUrisFrom(mixed $value, string $error): array
    {
        if (! is_array($value) || $value === [] || count($value) > self::MAX_REDIRECT_URIS) {
            throw new OAuthError($error, 'redirect_uris must list between one and '.self::MAX_REDIRECT_URIS.' addresses.');
        }

        $uris = [];

        foreach ($value as $uri) {
            if (! is_string($uri) || ! RedirectUris::isAcceptable($uri)) {
                throw new OAuthError($error, 'A redirect URI is an HTTPS address, a loopback address, or a scheme of the client\'s own, with no fragment.');
            }

            $uris[] = $uri;
        }

        sort($uris);

        return array_values(array_unique($uris));
    }

    private function nameFrom(mixed $value, string $fallbackUri): string
    {
        $name = is_string($value) ? trim(strip_tags($value)) : '';

        if ($name === '') {
            $name = RedirectUris::host($fallbackUri) ?? 'Assistant';
        }

        return Str::limit($name, 120, '');
    }

    private function httpsUrlFrom(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > RedirectUris::MAX_LENGTH || ! str_starts_with(strtolower($value), 'https://')) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
    }
}
