<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Testing\TestResponse;
use Tests\Support\McpWorld;

/*
 * The door itself, over HTTP: the 401 a client can discover the server
 * from, the capability gate in both editions, and a request that goes
 * all the way through Sanctum to a tool and leaves the entry it should.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'http://localhost');
    config()->set('projectsend.capabilities_disabled', null);
});

/** @param array<string, mixed> $body */
function mcpPost(array $body, ?User $as = null): TestResponse
{
    // One PHP process serves every request here; a guard remembers the
    // user it resolved. A real server resolves afresh per request.
    app('auth')->forgetGuards();
    McpContext::set(null);

    $request = test()->withHeaders([
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2025-06-18',
    ]);

    if ($as !== null) {
        $request = $request->withToken(McpWorld::$plainTokens[$as->id]);
    }

    return $request->postJson('/mcp', $body);
}

/** @return array<string, mixed> */
function mcpInitialize(): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
        'protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1'],
    ]];
}

it('answers a request without a token with the 401 a client discovers the server from', function (): void {
    $response = mcpPost(mcpInitialize());

    $response->assertStatus(401);

    $header = (string) $response->headers->get('WWW-Authenticate');

    expect($header)->toContain('resource_metadata="http://localhost/.well-known/oauth-protected-resource/mcp"')
        ->and($header)->toContain('scope="mcp:read mcp:write"')
        ->and($response->json('error.code'))->toBe(-32001);
});

it('offers mcp:own only where clients may connect', function (): void {
    McpWorld::freePlan();

    expect((string) mcpPost(mcpInitialize())->headers->get('WWW-Authenticate'))->toContain('scope="mcp:own mcp:read mcp:write"');
});

it('is there in both editions', function (Edition $edition): void {
    config()->set('projectsend.edition', $edition);
    $staff = McpWorld::staff();

    mcpPost(mcpInitialize(), $staff)->assertOk();
})->with([Edition::Community, Edition::Cloud]);

it('does not exist where the capability was subtracted', function (): void {
    config()->set('projectsend.capabilities_disabled', 'ai.connector');
    $staff = McpWorld::staff();

    mcpPost(mcpInitialize(), $staff)->assertStatus(404);
});

it('refuses a deactivated account\'s token', function (): void {
    $staff = McpWorld::staff();
    $staff->forceFill(['active' => false])->save();

    expect(mcpPost(mcpInitialize(), $staff)->status())->toBeIn([401, 403]);
});

it('initializes with the account named in the instructions, and lists the tools', function (): void {
    $staff = McpWorld::staff();

    $init = mcpPost(mcpInitialize(), $staff);

    $init->assertOk();

    expect($init->json('result.serverInfo.name'))->toBe('ProjectSend')
        ->and($init->json('result.serverInfo.version'))->toBe(config('projectsend.version'))
        ->and($init->json('result.instructions'))->toContain('This account: Ada Lovelace on localhost')
        // No plan to mention on an installation that has none.
        ->and($init->json('result.instructions'))->not->toContain('feature_requires_plan')
        ->and($init->json('result.instructions'))->not->toContain('paid plan');

    $list = mcpPost(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []], $staff);

    expect(count($list->json('result.tools')))->toBe(19);
});

it('tells the assistant how to talk about the plan where there is one', function (): void {
    McpWorld::freePlan();
    $client = McpWorld::client();

    $init = mcpPost(mcpInitialize(), $client);

    expect($init->json('result.instructions'))->toContain('When code is feature_requires_plan')
        ->and($init->json('result.instructions'))->toContain('This account: Grace Hopper on localhost, free plan');
});

it('runs a tool over HTTP and records it as the assistant, by the connector\'s name', function (): void {
    $staff = McpWorld::staff(tokenName: 'AI assistant · Claude Code');

    $response = mcpPost(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
        'name' => 'create_file_from_content',
        'arguments' => ['filename' => 'hello.txt', 'content' => 'hello'],
    ]], $staff);

    $response->assertOk();

    expect($response->json('result.isError'))->toBeFalsy()
        ->and($response->json('result.structuredContent.file.name'))->toBe('hello');

    $entry = ActivityLog::query()->where('action', 'file.uploaded')->first();

    expect($entry->origin->value)->toBe('mcp')
        ->and($entry->api_token_name)->toBe('Claude Code');
});

it('holds a personal API token to its own abilities here, as the API does', function (): void {
    // A token minted for "edit own files" shared, and deleted, other
    // people's files whenever its owner's role could (2026-10-28 scan).
    $staff = McpWorld::staff(tokenAbilities: ['edit_files', 'delete_files'], tokenName: 'integration');
    $colleague = McpWorld::staff();
    $theirs = McpWorld::file($colleague);

    $share = mcpPost(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
        'name' => 'share_by_link', 'arguments' => ['file_id' => $theirs->id],
    ]], $staff);
    $delete = mcpPost(['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => [
        'name' => 'delete_file', 'arguments' => ['file_id' => $theirs->id],
    ]], $staff);

    expect($share->json('result.isError'))->toBeTrue()
        ->and($delete->json('result.isError'))->toBeTrue()
        ->and(ShareLink::query()->count())->toBe(0)
        ->and($theirs->fresh()?->trashed())->toBeFalse();
});

it('lets a connection the person granted act with their role', function (): void {
    $staff = McpWorld::staff();
    $theirs = McpWorld::file(McpWorld::staff());

    $share = mcpPost(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
        'name' => 'share_by_link', 'arguments' => ['file_id' => $theirs->id],
    ]], $staff);

    expect($share->json('result.isError'))->toBeFalsy()
        ->and(ShareLink::query()->count())->toBe(1);
});

it('does not accept an assistant connection\'s token on the REST API', function (): void {
    $staff = McpWorld::staff();
    app('auth')->forgetGuards();

    test()->withToken(McpWorld::$plainTokens[$staff->id])->getJson('/api/v1/me')->assertForbidden();
    test()->withToken(McpWorld::$plainTokens[$staff->id])->deleteJson('/api/v1/tokens/current')->assertForbidden();

    expect($staff->tokens()->count())->toBe(1);
});

it('does not hand a narrow API token the client list as a resource', function (): void {
    $staff = McpWorld::staff(tokenAbilities: ['upload'], tokenName: 'integration');
    McpWorld::clientAccount('secret@acme.example', 'Secret Client');

    $read = mcpPost(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'resources/read', 'params' => ['uri' => 'projectsend://clients']], $staff);

    expect((string) $read->getContent())->not->toContain('secret@acme.example');
});
