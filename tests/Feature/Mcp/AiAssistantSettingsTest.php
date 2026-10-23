<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Testing\TestResponse;
use Tests\Support\McpWorld;

/*
 * The installation's own switch for the AI connector: an administrator
 * can close /mcp, its authorization server and its discovery documents
 * without touching the environment, and open them again.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('projectsend.capabilities_disabled', null);

    // Set rather than assumed: the settings cache outlives a rolled-back
    // test, so a default is whatever the last test left.
    app(Settings::class)->set(Setting::AiConnectorEnabled, true);
});

/** @param array<string, mixed> $body */
function aiSwitchMcp(array $body, User $as): TestResponse
{
    app('auth')->forgetGuards();
    McpContext::set(null);

    return test()->withHeaders([
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2025-06-18',
    ])->withToken(McpWorld::$plainTokens[$as->id])->postJson('/mcp', $body);
}

/** @return array<string, mixed> */
function aiSwitchToolsList(): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []];
}

function aiSwitchSettingsPage(User $as): TestResponse
{
    return test()->actingAs($as)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])
        ->get('/system/settings/ai-assistants');
}

it('is on by default', function (): void {
    expect(Setting::AiConnectorEnabled->default())->toBeTrue();
});

it('closes the server, the authorization server and discovery when it is off, and opens them again', function (): void {
    $staff = McpWorld::staff();

    aiSwitchMcp(aiSwitchToolsList(), $staff)->assertOk();
    test()->get('/.well-known/oauth-authorization-server')->assertOk();

    app(Settings::class)->set(Setting::AiConnectorEnabled, false);

    aiSwitchMcp(aiSwitchToolsList(), $staff)->assertNotFound();
    test()->get('/.well-known/oauth-authorization-server')->assertNotFound();
    test()->get('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
    test()->postJson('/oauth/mcp/register', ['client_name' => 'x', 'redirect_uris' => ['https://a.example/cb']])->assertNotFound();
    // Back to a browser: the bearer requests above left Sanctum's guard
    // as the default, which a real request would not inherit.
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');
    test()->actingAs($staff)->get('/settings/ai-connections')->assertNotFound();

    app(Settings::class)->set(Setting::AiConnectorEnabled, true);

    // Nothing was deleted: the same token works again.
    aiSwitchMcp(aiSwitchToolsList(), $staff)->assertOk();
});

it('tells the frontend, so the account settings never link to a closed page', function (): void {
    $staff = User::factory()->create();

    $page = fn (): TestResponse => test()->actingAs($staff)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])
        ->get('/settings/profile');

    expect($page()->json('props.ai_connector'))->toBeTrue();

    app(Settings::class)->set(Setting::AiConnectorEnabled, false);

    expect($page()->json('props.ai_connector'))->toBeFalse();
});

it('lets an administrator turn it off and on, and logs the change', function (): void {
    $admin = User::factory()->create();

    aiSwitchSettingsPage($admin)->assertOk()
        ->assertJsonPath('component', 'system/settings/ai-assistants')
        ->assertJsonPath('props.ai_connector_enabled', true);

    test()->actingAs($admin)->patch('/system/settings/ai-assistants', ['ai_connector_enabled' => false])->assertRedirect();

    expect(app(Settings::class)->get(Setting::AiConnectorEnabled))->toBeFalse();

    $entry = ActivityLog::query()->where('action', 'settings.updated')->latest('id')->first();

    expect($entry->context)->toMatchArray(['section' => 'ai_assistants', 'ai_connector_enabled' => false]);
});

it('keeps the switch from anyone who may not edit settings', function (): void {
    User::factory()->create();
    $editor = McpWorld::staff(permissions: ['upload', 'edit_files']);

    aiSwitchSettingsPage($editor)->assertForbidden();
    test()->actingAs($editor)->patch('/system/settings/ai-assistants', ['ai_connector_enabled' => false])->assertForbidden();

    expect(app(Settings::class)->get(Setting::AiConnectorEnabled))->toBeTrue();
});

it('has no switch where a hosted plan took the connector away', function (): void {
    $admin = User::factory()->create();
    config()->set('projectsend.capabilities_disabled', 'ai.connector');

    aiSwitchSettingsPage($admin)->assertNotFound();
});
