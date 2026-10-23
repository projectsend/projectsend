<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Mcp\Events\ResolvingConnectorPlan;
use App\Modules\Mcp\OAuth\Clients\PublicAddress;
use App\Modules\Mcp\OAuth\Models\McpAuthorizationCode;
use App\Modules\Mcp\OAuth\Models\McpClient;
use App\Modules\Mcp\OAuth\Models\McpConnection;
use App\Modules\Mcp\OAuth\Pkce;
use App\Modules\Mcp\Support\McpContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\McpWorld;

/*
 * The authorization server (site docs/mcp-oauth-design.md §9 and §10):
 * discovery, how a client comes to be known, the consent and what it
 * refuses before drawing anything, the code exchange with PKCE, refresh
 * rotation and the family that dies on reuse, revocation, the
 * connections page, and the daily sweep.
 *
 * A token earned here is tried at `/mcp` through Sanctum itself.
 */

const OAUTH_CIMD = 'https://claude.ai/.well-known/client.json';
const OAUTH_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';
const OAUTH_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
const OAUTH_RESOURCE = 'https://acme.projectsend.site/mcp';
const OAUTH_PASSWORD = 'correct horse battery';

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'https://acme.projectsend.site');
    config()->set('projectsend.capabilities_disabled', null);

    // The web routes send everybody to /setup until a staff account exists.
    User::factory()->create();

    Http::preventStrayRequests();

    // No DNS in the tests: claude.ai is public, evil.example is this machine.
    app()->instance(PublicAddress::class, new PublicAddress(fn (string $host): array => match ($host) {
        'claude.ai' => ['104.18.32.1'],
        'evil.example' => ['127.0.0.1'],
        'metadata.example' => ['169.254.169.254'],
        default => [],
    }));
});

/**
 * Run $body with the connector present or subtracted, as a hosted plan
 * would subtract it.
 *
 * @param  list<string>  $capabilities
 */
function withHostCapabilities(array $capabilities, callable $body): mixed
{
    config()->set('projectsend.capabilities_disabled', in_array('ai.connector', $capabilities, true) ? null : 'ai.connector');

    try {
        return $body();
    } finally {
        config()->set('projectsend.capabilities_disabled', null);
    }
}

/**
 * An Inertia visit, carrying the asset version the app expects, so a
 * page answers with its props rather than a 409 asking for a reload.
 *
 * @return array<string, string>
 */
function oauthInertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];
}

function oauthDocument(array $overrides = [], string $url = OAUTH_CIMD): void
{
    Http::fake([
        $url => Http::response(array_merge([
            'client_id' => $url,
            'client_name' => 'Claude',
            'redirect_uris' => [OAUTH_CALLBACK, 'http://localhost:6274/oauth/callback'],
            'logo_uri' => 'https://claude.ai/logo.png',
        ], $overrides), 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'max-age=3600']),
    ]);
}

function oauthQuery(array $overrides = []): array
{
    return array_merge([
        'response_type' => 'code',
        'client_id' => OAUTH_CIMD,
        'redirect_uri' => OAUTH_CALLBACK,
        'code_challenge' => Pkce::challengeOf(OAUTH_VERIFIER),
        'code_challenge_method' => 'S256',
        'resource' => OAUTH_RESOURCE,
        'scope' => 'mcp:write',
        'state' => 'xyz-123',
    ], $overrides);
}

/** A staff member with a password to confirm, and no token yet: the grants mint those. */
function oauthStaff(string $email = 'ada@example.com'): User
{
    $staff = McpWorld::staff();
    $staff->tokens()->delete();
    $staff->forceFill(['email' => $email, 'password' => Hash::make(OAUTH_PASSWORD)])->save();

    return $staff;
}

/**
 * A client account with a password to confirm and no token. Where the
 * installation lets clients connect is the test's to say.
 */
function oauthClient(bool $clientsMayConnect): User
{
    $client = McpWorld::client();
    $client->tokens()->delete();
    $client->forceFill(['password' => Hash::make(OAUTH_PASSWORD)])->save();

    if (! $clientsMayConnect) {
        Event::forget(ResolvingConnectorPlan::class);
        app()->forgetScopedInstances();
    }

    return $client;
}

function oauthAuthorize(User $user, array $query = [], array $capabilities = ['ai.connector']): TestResponse
{
    // A new consent starts a new browser session. The app checks the
    // session against the password of whoever signed it in, so one left
    // over from another person would sign this one out.
    test()->flushSession();
    app('auth')->forgetGuards();

    return withHostCapabilities($capabilities, fn (): TestResponse => test()->actingAs($user)
        ->withHeaders(oauthInertiaHeaders())
        ->get('/oauth/mcp/authorize?'.http_build_query(oauthQuery($query))));
}

function oauthDecide(User $user, ?string $requestId, array $data = []): TestResponse
{
    return withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->actingAs($user)
        ->withHeaders(oauthInertiaHeaders())
        ->post('/oauth/mcp/authorize', array_merge([
            'request_id' => $requestId,
            'decision' => 'allow',
            'access' => 'operate',
            'password' => OAUTH_PASSWORD,
        ], $data)));
}

/** The whole consent, through to the code the allowed request is sent back with. */
function oauthCode(User $user, array $query = [], array $decision = []): string
{
    $page = oauthAuthorize($user, $query);
    $page->assertOk();

    $answer = oauthDecide($user, $page->json('props.request_id'), $decision);
    $answer->assertStatus(409);

    parse_str((string) parse_url((string) $answer->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $params);

    expect($params['state'] ?? null)->toBe($query['state'] ?? 'xyz-123');

    return (string) $params['code'];
}

function oauthToken(array $form, array $capabilities = ['ai.connector']): TestResponse
{
    return withHostCapabilities($capabilities, fn (): TestResponse => test()->post('/oauth/mcp/token', $form, ['Accept' => 'application/json']));
}

function oauthExchange(string $code, array $overrides = []): TestResponse
{
    return oauthToken(array_merge([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => OAUTH_CIMD,
        'redirect_uri' => OAUTH_CALLBACK,
        'code_verifier' => OAUTH_VERIFIER,
        'resource' => OAUTH_RESOURCE,
    ], $overrides));
}

function oauthRefresh(string $refreshToken, array $overrides = []): TestResponse
{
    return oauthToken(array_merge(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken, 'client_id' => OAUTH_CIMD], $overrides));
}

/** A JSON-RPC call at `/mcp` with a token the grants minted. */
function oauthMcp(string $token, array $body): TestResponse
{
    app('auth')->forgetGuards();

    return withHostCapabilities(['ai.connector'], fn (): TestResponse => test()
        ->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
        ->withToken($token)
        ->postJson('/mcp', $body));
}

function oauthToolsList(): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []];
}

// ── Discovery ────────────────────────────────────────────────────────

it('publishes the two discovery documents on the platform address, and the 401 names them', function (): void {
    $resource = test()->get('/.well-known/oauth-protected-resource/mcp');

    $resource->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJson([
            'resource' => OAUTH_RESOURCE,
            'authorization_servers' => ['https://acme.projectsend.site'],
            'scopes_supported' => ['mcp:read', 'mcp:write'],
            'bearer_methods_supported' => ['header'],
        ]);

    test()->get('/.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('resource', OAUTH_RESOURCE);

    $server = test()->get('/.well-known/oauth-authorization-server');

    $server->assertOk()->assertJson([
        'issuer' => 'https://acme.projectsend.site',
        'authorization_endpoint' => 'https://acme.projectsend.site/oauth/mcp/authorize',
        'token_endpoint' => 'https://acme.projectsend.site/oauth/mcp/token',
        'registration_endpoint' => 'https://acme.projectsend.site/oauth/mcp/register',
        'revocation_endpoint' => 'https://acme.projectsend.site/oauth/mcp/revoke',
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none'],
        'client_id_metadata_document_supported' => true,
    ]);

    expect($server->json('issuer'))->not->toEndWith('/');

    $door = oauthMcp('nothing', oauthToolsList());

    $door->assertStatus(401);
    expect($door->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata="https://acme.projectsend.site/.well-known/oauth-protected-resource/mcp"')
        ->toContain('scope="mcp:read mcp:write"');
});

it('announces mcp:own only where clients may connect', function (): void {
    McpWorld::freePlan();

    test()->get('/.well-known/oauth-authorization-server')->assertJsonPath('scopes_supported', ['mcp:own', 'mcp:read', 'mcp:write']);
});

// ── Registration ─────────────────────────────────────────────────────

it('registers a public client once, and answers a repeat with the same id', function (): void {
    $body = ['client_name' => 'ChatGPT', 'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect']];

    $first = withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->postJson('/oauth/mcp/register', $body));
    $second = withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->postJson('/oauth/mcp/register', $body));

    $first->assertStatus(201)->assertJson(['client_name' => 'ChatGPT', 'token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code', 'refresh_token']]);
    $second->assertOk();

    expect($first->json('client_id'))->toBe($second->json('client_id'))
        ->and($first->json())->not->toHaveKey('client_secret')
        ->and(McpClient::query()->count())->toBe(1)
        ->and(McpClient::query()->first()?->isVerified())->toBeFalse();
});

it('refuses a confidential client, a plain-http redirect and a redirect with a fragment', function (): void {
    $register = fn (array $body): TestResponse => withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->postJson('/oauth/mcp/register', $body));

    $register(['redirect_uris' => ['https://a.example/cb'], 'token_endpoint_auth_method' => 'client_secret_basic'])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_client_metadata');
    $register(['redirect_uris' => ['http://a.example/cb']])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    $register(['redirect_uris' => ['https://a.example/cb#x']])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    $register(['redirect_uris' => ['http://127.0.0.1:9999/cb', 'cursor://anysphere.cursor-mcp/oauth/callback']])
        ->assertStatus(201);

    expect(McpClient::query()->count())->toBe(1);
});

// ── Client metadata documents ────────────────────────────────────────

it('reads a client metadata document once and shows the client as verified by its host', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    $first = oauthAuthorize($staff);
    $second = oauthAuthorize($staff);

    $first->assertOk()->assertJsonPath('component', 'mcp/consent')
        ->assertJsonPath('props.client.name', 'Claude')
        ->assertJsonPath('props.client.identity', 'claude.ai')
        ->assertJsonPath('props.client.verified', true)
        ->assertJsonPath('props.client.redirect_host', 'claude.ai')
        ->assertJsonPath('props.client.loopback', false)
        ->assertJsonPath('props.actor.email', 'ada@example.com')
        ->assertJsonPath('props.grant.kind', 'staff')
        ->assertJsonPath('props.grant.default_access', 'operate');
    $second->assertOk();

    Http::assertSentCount(1);

    expect(McpClient::query()->count())->toBe(1)
        ->and(McpClient::query()->first()?->kind)->toBe('cimd');
});

it('refuses a document that does not name its own URL, and never fetches from a private address', function (): void {
    oauthDocument(['client_id' => 'https://claude.ai/other.json']);
    $staff = oauthStaff();

    oauthAuthorize($staff)->assertStatus(400)->assertJsonPath('component', 'mcp/authorize-error');

    expect(McpClient::query()->count())->toBe(0);

    Http::fake();

    oauthAuthorize($staff, ['client_id' => 'https://evil.example/client.json'])->assertStatus(400);
    oauthAuthorize($staff, ['client_id' => 'https://metadata.example/client.json'])->assertStatus(400);

    Http::assertNothingSent();
});

// ── Authorize: what is refused before drawing anything ───────────────

it('shows a redirect that was not registered on screen, and allows a loopback port to differ', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    oauthAuthorize($staff, ['redirect_uri' => 'https://claude.ai/api/mcp/auth_callback/x'])
        ->assertStatus(400)->assertJsonPath('component', 'mcp/authorize-error');
    oauthAuthorize($staff, ['redirect_uri' => 'http://localhost:51234/oauth/callback'])
        ->assertOk()->assertJsonPath('props.client.loopback', true);
});

it('reports what fails after the client validated at the client\'s own address, with the state', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    $refused = function (array $query, string $error) use ($staff): void {
        $response = oauthAuthorize($staff, $query);
        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        expect($location)->toStartWith(OAUTH_CALLBACK.'?')
            ->and($params['error'])->toBe($error)
            ->and($params['state'])->toBe('xyz-123');
    };

    $refused(['code_challenge_method' => 'plain'], 'invalid_request');
    $refused(['code_challenge' => ''], 'invalid_request');
    $refused(['response_type' => 'token'], 'unsupported_response_type');
    $refused(['resource' => 'https://other.projectsend.site/mcp'], 'invalid_target');
    $refused(['resource' => ''], 'invalid_target');
    $refused(['scope' => 'mcp:write files:all'], 'invalid_scope');

    expect(McpAuthorizationCode::query()->count())->toBe(0);
});

it('proposes read when only reading was asked for', function (): void {
    oauthDocument();

    oauthAuthorize(oauthStaff(), ['scope' => 'mcp:read'])->assertJsonPath('props.grant.default_access', 'read');
});

it('refuses a client account wherever clients may not connect, before issuing anything', function (): void {
    oauthDocument();
    $client = oauthClient(clientsMayConnect: false);

    oauthAuthorize($client)->assertStatus(403)->assertJsonPath('component', 'mcp/authorize-error');

    expect(McpAuthorizationCode::query()->count())->toBe(0);
});

it('does not exist on an installation without the capability', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    oauthAuthorize($staff, [], [])->assertStatus(404)->assertJsonPath('component', 'mcp/authorize-error');
    oauthToken(['grant_type' => 'authorization_code'], [])->assertStatus(404);
    withHostCapabilities([], fn (): TestResponse => test()->postJson('/oauth/mcp/register', ['redirect_uris' => ['https://a.example/cb']]))->assertStatus(404);
});

// ── The consent ──────────────────────────────────────────────────────

it('issues nothing without the password, and sends the person back with access_denied on Deny', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    $page = oauthAuthorize($staff);
    $requestId = $page->json('props.request_id');

    oauthDecide($staff, $requestId, ['password' => 'wrong'])->assertSessionHasErrors('password');

    expect(McpAuthorizationCode::query()->count())->toBe(0);

    // A mistyped password does not end the request: the same page asks again.
    $denied = oauthDecide($staff, $requestId, ['decision' => 'deny']);

    $denied->assertStatus(409);
    expect($denied->headers->get('X-Inertia-Location'))->toContain('error=access_denied')->toContain('state=xyz-123');

    // A decision is one per page: the same id again is gone.
    oauthDecide($staff, $requestId)->assertStatus(410);
});

it('issues a code for the access the staff member chose, and logs the connection', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    $code = oauthCode($staff, [], ['access' => 'read']);
    $record = McpAuthorizationCode::query()->first();

    expect($code)->not->toBe('')
        ->and($record?->scopes)->toBe(['mcp:read'])
        ->and($record?->code_hash)->toBe(hash('sha256', $code))
        ->and($record?->user_id)->toBe($staff->getKey())
        ->and(ActivityLog::query()->where('action', 'api_token.created')->exists())->toBeTrue();
});

it('grants a client its own files where clients may connect, with no choice offered', function (): void {
    oauthDocument();
    $client = oauthClient(clientsMayConnect: true);
    McpWorld::freePlan();

    oauthAuthorize($client)->assertOk()->assertJsonPath('props.grant.kind', 'client');

    oauthCode($client, [], ['access' => 'operate']);

    expect(McpAuthorizationCode::query()->first()?->scopes)->toBe(['mcp:own']);
});

// ── The token endpoint ───────────────────────────────────────────────

it('exchanges a code for a Sanctum token that works at /mcp, and refuses the code a second time by revoking what it earned', function (): void {
    oauthDocument();
    $staff = oauthStaff();
    $code = oauthCode($staff);

    $exchanged = oauthExchange($code);

    $exchanged->assertOk();
    expect($exchanged->headers->get('Cache-Control'))->toContain('no-store');
    $exchanged
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 3600)
        ->assertJsonPath('scope', 'mcp:write');

    $access = (string) $exchanged->json('access_token');
    $row = PersonalAccessToken::query()->first();
    $connection = McpConnection::query()->first();

    expect($row?->name)->toBe('AI assistant · Claude')
        ->and($row?->abilities)->toBe(['mcp:write'])
        ->and($row?->expires_at?->diffInMinutes(now()))->toBeLessThanOrEqual(60)
        ->and($connection?->access_token_id)->toBe($row?->getKey())
        ->and($connection?->isLive())->toBeTrue()
        ->and(strlen((string) $exchanged->json('refresh_token')))->toBeGreaterThanOrEqual(43);

    $tools = oauthMcp($access, oauthToolsList());
    $tools->assertOk();
    expect(count($tools->json('result.tools')))->toBe(19);

    // The code again: intercepted, so what the first exchange earned goes.
    oauthExchange($code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(McpConnection::query()->first()?->revoked_at)->not->toBeNull();

    oauthMcp($access, oauthToolsList())->assertStatus(401);
});

it('refuses an exchange whose verifier, redirect, client or resource does not match, and a code past its minute', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    oauthExchange(oauthCode($staff), ['code_verifier' => 'wrong-'.OAUTH_VERIFIER])->assertJsonPath('error', 'invalid_grant');
    oauthExchange(oauthCode($staff), ['redirect_uri' => 'http://localhost:6274/oauth/callback'])->assertJsonPath('error', 'invalid_grant');
    oauthExchange(oauthCode($staff), ['client_id' => 'https://claude.ai/other.json'])->assertJsonPath('error', 'invalid_grant');
    oauthExchange(oauthCode($staff), ['resource' => 'https://other.projectsend.site/mcp'])->assertJsonPath('error', 'invalid_grant');
    oauthToken(['grant_type' => 'password'])->assertJsonPath('error', 'unsupported_grant_type');

    $late = oauthCode($staff);
    test()->travel(61)->seconds();
    oauthExchange($late)->assertJsonPath('error', 'invalid_grant');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('rotates on refresh, and ends the whole connection when an old refresh token is reused', function (): void {
    oauthDocument();
    $staff = oauthStaff();
    $first = oauthExchange(oauthCode($staff))->json();

    $second = oauthRefresh($first['refresh_token']);

    $second->assertOk();
    expect($second->json('access_token'))->not->toBe($first['access_token'])
        ->and($second->json('refresh_token'))->not->toBe($first['refresh_token'])
        ->and(PersonalAccessToken::query()->count())->toBe(1)
        ->and(McpConnection::query()->count())->toBe(1);

    oauthMcp($first['access_token'], oauthToolsList())->assertStatus(401);
    oauthMcp($second->json('access_token'), oauthToolsList())->assertOk();

    // The old refresh again: somebody has it who should not.
    oauthRefresh($first['refresh_token'])->assertJsonPath('error', 'invalid_grant');

    expect(McpConnection::query()->first()?->revoked_reason)->toBe('refresh token reuse')
        ->and(PersonalAccessToken::query()->count())->toBe(0);

    oauthMcp($second->json('access_token'), oauthToolsList())->assertStatus(401);
    oauthRefresh($second->json('refresh_token'))->assertJsonPath('error', 'invalid_grant');
});

it('never widens a grant on refresh, holds a refresh to its client, and lets it run out', function (): void {
    oauthDocument();
    $staff = oauthStaff();
    $granted = oauthExchange(oauthCode($staff, [], ['access' => 'read']))->json();

    oauthRefresh($granted['refresh_token'], ['scope' => 'mcp:write'])->assertJsonPath('error', 'invalid_scope');
    oauthRefresh($granted['refresh_token'], ['client_id' => 'https://claude.ai/other.json'])->assertJsonPath('error', 'invalid_grant');
    oauthRefresh($granted['refresh_token'], ['scope' => 'mcp:read'])->assertOk()->assertJsonPath('scope', 'mcp:read');

    test()->travel(31)->days();

    $latest = McpConnection::query()->first();
    oauthRefresh($granted['refresh_token'])->assertJsonPath('error', 'invalid_grant');

    expect($latest?->refresh_expires_at->isPast())->toBeTrue();
});

// ── Revocation ───────────────────────────────────────────────────────

it('revokes the whole connection by either token, and answers 200 whatever it was given', function (): void {
    oauthDocument();
    $staff = oauthStaff();

    $one = oauthExchange(oauthCode($staff))->json();
    withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->post('/oauth/mcp/revoke', ['token' => $one['refresh_token'], 'token_type_hint' => 'refresh_token']))->assertOk();

    oauthMcp($one['access_token'], oauthToolsList())->assertStatus(401);
    oauthRefresh($one['refresh_token'])->assertJsonPath('error', 'invalid_grant');

    $two = oauthExchange(oauthCode($staff))->json();
    withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->post('/oauth/mcp/revoke', ['token' => $two['access_token']]))->assertOk();

    oauthMcp($two['access_token'], oauthToolsList())->assertStatus(401);

    withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->post('/oauth/mcp/revoke', ['token' => 'nothing-here']))->assertOk();

    expect(McpConnection::query()->whereNotNull('revoked_at')->count())->toBe(2)
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', 'api_token.revoked')->count())->toBe(2);
});

// ── The connections page ─────────────────────────────────────────────

it('lists the person\'s own live connections and disconnects one, and nobody else\'s', function (): void {
    oauthDocument();
    $staff = oauthStaff();
    $other = oauthStaff('other@example.com');

    $mine = oauthExchange(oauthCode($staff))->json();
    oauthExchange(oauthCode($other));

    // Back to the first person's own browser.
    test()->flushSession();
    app('auth')->forgetGuards();

    $page = withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->actingAs($staff)->withHeaders(oauthInertiaHeaders())->get('/settings/ai-connections'));

    $page->assertOk()->assertJsonPath('component', 'settings/ai-connections');
    expect($page->json('props.connections'))->toHaveCount(1)
        ->and($page->json('props.connections.0.client_name'))->toBe('Claude')
        ->and($page->json('props.connections.0.identity'))->toBe('claude.ai')
        ->and($page->json('props.connections.0.verified'))->toBeTrue()
        ->and($page->json('props.connections.0.access'))->toBe('Operate');

    $theirs = McpConnection::query()->where('user_id', $other->getKey())->first();
    $ours = McpConnection::query()->where('user_id', $staff->getKey())->first();

    withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->actingAs($staff)->delete('/settings/ai-connections/'.$theirs?->getKey()))->assertNotFound();
    withHostCapabilities(['ai.connector'], fn (): TestResponse => test()->actingAs($staff)->delete('/settings/ai-connections/'.$ours?->getKey()))->assertRedirect('/settings/ai-connections');

    expect($ours?->fresh()?->revoked_reason)->toBe('disconnected from settings')
        ->and($theirs?->fresh()?->isLive())->toBeTrue();

    oauthMcp($mine['access_token'], oauthToolsList())->assertStatus(401);
    withHostCapabilities([], fn (): TestResponse => test()->actingAs($staff)->get('/settings/ai-connections'))->assertNotFound();
});

// ── The sweep ────────────────────────────────────────────────────────

it('prunes what the tables no longer need, and expires what ran out', function (): void {
    oauthDocument();
    $staff = oauthStaff();
    $live = oauthExchange(oauthCode($staff))->json();

    McpAuthorizationCode::query()->update(['expires_at' => now()->subDays(2)]);

    $client = McpClient::query()->first();
    $stale = McpConnection::query()->create([
        'mcp_client_id' => $client?->getKey(), 'user_id' => $staff->getKey(), 'scopes' => ['mcp:read'],
        'family' => 'f1', 'refresh_expires_at' => now()->addDay(), 'revoked_at' => now()->subDays(31), 'created_at' => now()->subDays(40),
    ]);
    $ranOut = McpConnection::query()->create([
        'mcp_client_id' => $client?->getKey(), 'user_id' => $staff->getKey(), 'scopes' => ['mcp:read'],
        'family' => 'f2', 'refresh_expires_at' => now()->subDay(), 'access_token_id' => PersonalAccessToken::query()->first()?->getKey(),
    ]);
    $orphan = McpClient::query()->create([
        'client_id' => 'orphan', 'client_id_hash' => McpClient::hashOf('orphan'), 'kind' => 'dcr', 'name' => 'Old', 'redirect_uris' => ['https://a.example/cb'],
        'created_at' => now()->subDays(100),
    ]);

    test()->artisan('mcp:prune')->assertSuccessful();

    expect(McpAuthorizationCode::query()->count())->toBe(0)
        ->and($stale->fresh())->toBeNull()
        ->and($ranOut->fresh()?->revoked_reason)->toBe('refresh token expired')
        ->and($orphan->fresh())->toBeNull()
        ->and(McpClient::query()->count())->toBe(1)
        ->and(PersonalAccessToken::query()->count())->toBe(0);

    // The live connection is untouched, though its access token went with the one that ran out.
    expect(McpConnection::query()->find(McpConnection::query()->where('family', '!=', 'f2')->whereNull('revoked_at')->value('id')))->not->toBeNull()
        ->and(oauthRefresh($live['refresh_token'])->status())->toBe(200);
});
