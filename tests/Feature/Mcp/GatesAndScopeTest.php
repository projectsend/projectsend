<?php

declare(strict_types=1);

use App\Modules\Mcp\Events\ResolvingConnectorPlan;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\CreateClient;
use App\Modules\Mcp\Tools\CreateFileFromContent;
use App\Modules\Mcp\Tools\CreateUpload;
use App\Modules\Mcp\Tools\DeleteFile;
use App\Modules\Mcp\Tools\GetClient;
use App\Modules\Mcp\Tools\GetDownloadStatus;
use App\Modules\Mcp\Tools\GetFile;
use App\Modules\Mcp\Tools\ListClients;
use App\Modules\Mcp\Tools\RevokeLink;
use App\Modules\Mcp\Tools\SendFileToClient;
use App\Modules\Mcp\Tools\ShareByLink;
use Illuminate\Support\Facades\Event;
use Tests\Support\McpWorld;

/*
 * Who may do what (spec §7 and §8): the gate a client account meets on
 * the client tools — an offer where the installation has a plan to
 * offer, a plain refusal where it has not — the scope a read-only grant meets on the write
 * tools, the ceiling an API token's abilities set, and the reach of a
 * client account — its own files and nothing else.
 */

beforeEach(function (): void {
    McpContext::set(null);
});

it('answers a free account with the gate on every client tool, the free alternative first', function (string $tool, array $arguments): void {
    $client = McpWorld::client();

    $answer = McpWorld::call($tool, $arguments, $client);
    $error = McpWorld::error($answer);

    expect($error)->not->toBeNull()
        ->and($error['code'])->toBe('feature_requires_plan')
        ->and($error['alternative_tool'])->toBe('share_by_link')
        ->and($error['plan']['name'])->toBe('Pro')
        ->and($error['action'])->toContain('share_by_link');

    // The plan's own link, told which tool asked and which assistant
    // was asking; how it tags them is the hosted edition's business.
    $name = (new $tool)->name();
    parse_str((string) parse_url($error['upgrade_url'], PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'utm_content' => $name,
        'mcp_client' => 'claude',
    ])->and($error['web_url'])->toStartWith('https://example.com/pricing');
})->with([
    'list_clients' => [ListClients::class, []],
    'get_client' => [GetClient::class, ['client_id' => 1]],
    'create_client' => [CreateClient::class, ['name' => 'Acme', 'email' => 'ops@acme.example']],
    'send_file_to_client' => [SendFileToClient::class, ['client_id' => 1, 'file_id' => 1]],
]);

it('refuses a client account plainly where there is no plan to offer', function (): void {
    $client = McpWorld::client();
    // A client token, on an installation that says nothing about plans.
    Event::forget(ResolvingConnectorPlan::class);
    app()->forgetScopedInstances();

    $error = McpWorld::error(McpWorld::call(ListClients::class, [], $client));

    expect($error['code'])->toBe('permission_missing')
        ->and($error['permission'])->toBe('staff')
        ->and($error)->not->toHaveKey('upgrade_url')
        ->and($error['action'])->not->toContain('plan');
});

it('fills the gate\'s next_call with the same file, shared by link instead', function (): void {
    $client = McpWorld::client();

    $error = McpWorld::error(McpWorld::call(SendFileToClient::class, ['client_id' => 1, 'file_id' => 9], $client));

    expect($error['next_call'])->toBe(['tool' => 'share_by_link', 'arguments' => ['file_id' => 9]]);
});

it('gates a free account out of download status by client, but not by file', function (): void {
    $client = McpWorld::client();
    $file = McpWorld::file($client);

    $byClient = McpWorld::error(McpWorld::call(GetDownloadStatus::class, ['client_id' => 1], $client));
    $byFile = McpWorld::call(GetDownloadStatus::class, ['file_id' => $file->getKey()], $client);

    expect($byClient['code'])->toBe('feature_requires_plan')
        ->and(McpWorld::error($byFile))->toBeNull()
        ->and($byFile['summary']['total'])->toBe(0);
});

it('tells a read-only connection how to reconnect rather than doing the write', function (): void {
    $staff = McpWorld::staff(tokenAbilities: ['mcp:read']);

    $error = McpWorld::error(McpWorld::call(CreateFileFromContent::class, ['filename' => 'a.txt', 'content' => 'x'], $staff));

    expect($error['code'])->toBe('scope_insufficient')
        ->and($error['required_scope'])->toBe('mcp:write')
        ->and($error['web_url'])->toContain('/settings/ai-connections')
        ->and($error['action'])->toContain('Operate');
});

it('accepts mcp:use as whatever was granted, which is read', function (): void {
    $staff = McpWorld::staff(tokenAbilities: ['mcp:use']);

    expect(McpWorld::error(McpWorld::call(GetFile::class, ['file_id' => 1], $staff))['code'])->toBe('not_found')
        ->and(McpWorld::error(McpWorld::call(DeleteFile::class, ['file_id' => 1], $staff))['code'])->toBe('scope_insufficient');
});

it('holds an API token to its abilities, and prunes what it cannot reach from the list', function (): void {
    $staff = McpWorld::staff(tokenAbilities: ['upload'], tokenName: 'n8n');

    // Not listed, so not callable: the server itself refuses the call.
    $error = McpWorld::error(McpWorld::call(CreateClient::class, ['name' => 'Acme', 'email' => 'ops@acme.example'], $staff));

    expect($error['code'])->toBe('jsonrpc')
        ->and($error['message'])->toContain('not found');

    // Listed only when the token can reach the tool: an ability is not
    // something a conversation can change (spec §8).
    McpContext::set(null);
    $this->actingAs($staff);

    expect((new CreateClient)->shouldRegister())->toBeFalse()
        ->and((new CreateUpload)->shouldRegister())->toBeTrue()
        ->and((new GetFile)->shouldRegister())->toBeTrue();
});

it('lists every tool to an OAuth grant, read-only included', function (): void {
    $staff = McpWorld::staff(tokenAbilities: ['mcp:read']);

    McpContext::set(null);
    $this->actingAs($staff);

    expect((new CreateClient)->shouldRegister())->toBeTrue()
        ->and((new DeleteFile)->shouldRegister())->toBeTrue();
});

it('caps a write grant at the person\'s live permissions', function (): void {
    $staff = McpWorld::staff(permissions: ['upload', 'edit_files'], tokenAbilities: ['mcp:write']);

    $error = McpWorld::error(McpWorld::call(CreateClient::class, ['name' => 'Acme', 'email' => 'ops@acme.example'], $staff));

    expect($error['code'])->toBe('permission_missing')
        ->and($error['permission'])->toBe('create_clients');
});

it('lets a client account see, share, revoke and delete its own files and nobody else\'s', function (): void {
    $other = McpWorld::staff();
    $client = McpWorld::client();
    $theirs = McpWorld::file($other, ['name' => 'not-yours']);
    $theirLink = McpWorld::link($theirs);
    $mine = McpWorld::file($client, ['name' => 'mine']);

    foreach ([
        [GetFile::class, ['file_id' => $theirs->getKey()]],
        [ShareByLink::class, ['file_id' => $theirs->getKey()]],
        [DeleteFile::class, ['file_id' => $theirs->getKey()]],
        [RevokeLink::class, ['link_id' => $theirLink->getKey()]],
    ] as [$tool, $arguments]) {
        $error = McpWorld::error(McpWorld::call($tool, $arguments, $client));

        expect($error)->not->toBeNull("{$tool} answered somebody else's file")
            ->and($error['code'])->toBe('not_found');
    }

    $own = McpWorld::call(GetFile::class, ['file_id' => $mine->getKey()], $client);

    expect(McpWorld::error($own))->toBeNull()
        ->and($own['file']['name'])->toBe('mine');
});
