<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use App\Modules\Mcp\OAuth\Clients\Clients;
use App\Modules\Mcp\OAuth\Models\McpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What a client asked at `/oauth/mcp/authorize`, validated in the order
 * design §4.1 fixes: the client and its redirect first, because every
 * failure after them is reported at that redirect and every failure
 * before them on screen. Kept in the session between the page and the
 * decision, so the decision acts on what was validated and not on what
 * a form posted back.
 */
final class AuthorizationRequest
{
    private const SESSION_PREFIX = 'mcp.authorize.';

    private const SESSION_LIFETIME_MINUTES = 10;

    /**
     * @param  list<string>  $requestedScopes
     */
    public function __construct(
        public readonly McpClient $client,
        public readonly string $redirectUri,
        public readonly string $codeChallenge,
        public readonly string $resource,
        public readonly array $requestedScopes,
        public readonly ?string $state,
    ) {}

    /**
     * @throws AuthorizeRefused
     */
    public static function fromQuery(Request $request, Clients $clients): self
    {
        $clientId = (string) $request->query('client_id', '');
        $client = $clientId !== '' ? $clients->resolve($clientId) : null;

        if ($client === null) {
            throw new AuthorizeRefused('invalid_client', 'This assistant is not known here. It has to identify itself by a metadata document or register first.');
        }

        $redirectUri = (string) $request->query('redirect_uri', '');

        if ($redirectUri === '' || ! $clients->redirectMatches($client, $redirectUri)) {
            throw new AuthorizeRefused('invalid_request', 'The address this assistant asked to be sent back to is not one it registered.');
        }

        $state = $request->query('state');
        $state = is_string($state) && $state !== '' ? Str::limit($state, 1024, '') : null;

        $refuse = static fn (string $error, string $description): AuthorizeRefused => new AuthorizeRefused(
            $error,
            $description,
            self::withQuery($redirectUri, array_filter(['error' => $error, 'error_description' => $description, 'state' => $state])),
        );

        if ($request->query('response_type') !== 'code') {
            throw $refuse('unsupported_response_type', 'Only response_type=code is supported.');
        }

        $challenge = (string) $request->query('code_challenge', '');

        if ($challenge === '' || ! Pkce::isWellFormed($challenge)) {
            throw $refuse('invalid_request', 'A code_challenge is required (PKCE, S256).');
        }

        if ($request->query('code_challenge_method') !== 'S256') {
            throw $refuse('invalid_request', 'code_challenge_method must be S256.');
        }

        $resource = (string) $request->query('resource', '');

        if ($resource !== Issuer::resource()) {
            throw $refuse('invalid_target', 'The resource must be '.Issuer::resource().'.');
        }

        try {
            $scopes = Scopes::parse($request->query('scope'));
        } catch (OAuthError $e) {
            throw $refuse($e->error, $e->description);
        }

        return new self($client, $redirectUri, $challenge, $resource, $scopes, $state);
    }

    public function redirectWithCode(string $code): string
    {
        return self::withQuery($this->redirectUri, array_filter(['code' => $code, 'state' => $this->state]));
    }

    public function redirectWithError(string $error, string $description): string
    {
        return self::withQuery($this->redirectUri, array_filter(['error' => $error, 'error_description' => $description, 'state' => $this->state]));
    }

    /** Puts the validated request in the session; the id is what the page posts back. */
    public function stash(Request $request): string
    {
        $id = bin2hex(random_bytes(16));

        $request->session()->put(self::SESSION_PREFIX.$id, [
            'client' => $this->client->getKey(),
            'redirect_uri' => $this->redirectUri,
            'code_challenge' => $this->codeChallenge,
            'resource' => $this->resource,
            'scopes' => $this->requestedScopes,
            'state' => $this->state,
            'expires' => now()->addMinutes(self::SESSION_LIFETIME_MINUTES)->getTimestamp(),
        ]);

        return $id;
    }

    /**
     * The stashed request a page posts back. It stays in the session
     * until a decision is made (`forget()`), so a mistyped password does
     * not end the request: the page keeps its id and asks again.
     */
    public static function recall(Request $request, string $id): ?self
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            return null;
        }

        $data = $request->session()->get(self::SESSION_PREFIX.$id);

        if (! is_array($data) || ($data['expires'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $client = McpClient::query()->find((int) $data['client']);

        if ($client === null) {
            return null;
        }

        /** @var list<string> $scopes */
        $scopes = $data['scopes'];

        return new self($client, (string) $data['redirect_uri'], (string) $data['code_challenge'], (string) $data['resource'], $scopes, $data['state']);
    }

    /** One decision per page: the id is gone once Allow or Deny was answered. */
    public static function forget(Request $request, string $id): void
    {
        $request->session()->forget(self::SESSION_PREFIX.$id);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private static function withQuery(string $uri, array $parameters): string
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri.$separator.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
