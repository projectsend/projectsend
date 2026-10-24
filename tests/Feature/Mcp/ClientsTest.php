<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Identity\UserType;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\CreateClient;
use App\Modules\Mcp\Tools\GetClient;
use App\Modules\Mcp\Tools\ListClients;
use App\Modules\Mcp\Tools\SendFileToClient;
use App\Modules\Notifications\InAppNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Support\McpWorld;

/*
 * Client accounts, from a staff account: created once however often the
 * call is repeated, found by address, sent files that are shared once.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'https://acme.projectsend.site');
    config()->set('projectsend.platform.max_clients', null);
    Notification::fake();
});

/** Client accounts that exist, as the seat allowance counts them. */
function mcpClientCount(): int
{
    return User::query()->where('type', UserType::Client)->count();
}

it('creates a client with a generated password shown once, and logs it as the assistant', function (): void {
    $staff = McpWorld::staff();

    $answer = McpWorld::call(CreateClient::class, ['name' => 'Acme Ltd', 'email' => 'Ops@Acme.example', 'notify' => false], $staff);

    expect(McpWorld::error($answer))->toBeNull(json_encode($answer))
        ->and($answer['client']['email'])->toBe('ops@acme.example')
        ->and(strlen((string) $answer['password_once']))->toBeGreaterThanOrEqual(20)
        ->and($answer['notified'])->toBeFalse()
        ->and($answer['next_steps'])->toContain('will not be shown again');

    Notification::assertNothingSent();

    $entry = ActivityLog::query()->where('action', 'user.created')->first();

    expect($entry->origin->value)->toBe('mcp')
        ->and($entry->api_token_name)->toBe('Claude');
});

it('creates one account for one key, however often the call is repeated', function (): void {
    $staff = McpWorld::staff();
    $arguments = ['name' => 'Acme Ltd', 'email' => 'ops@acme.example', 'idempotency_key' => 'ops@acme.example'];

    $first = McpWorld::call(CreateClient::class, $arguments, $staff);
    $second = McpWorld::call(CreateClient::class, $arguments, $staff);

    expect($second['client']['id'])->toBe($first['client']['id'])
        ->and($second['password_once'])->toBe($first['password_once'])
        ->and(mcpClientCount())->toBe(1);

    $conflict = McpWorld::error(McpWorld::call(CreateClient::class, ['name' => 'Acme Inc', 'email' => 'ops@acme.example', 'idempotency_key' => 'ops@acme.example'], $staff));

    expect($conflict['code'])->toBe('idempotency_conflict');
});

it('points at the existing account rather than creating a second one for the same address', function (): void {
    $staff = McpWorld::staff();
    $existing = McpWorld::clientAccount();

    $error = McpWorld::error(McpWorld::call(CreateClient::class, ['name' => 'Acme again', 'email' => 'ops@acme.example'], $staff));

    expect($error['code'])->toBe('client_email_taken')
        ->and($error['client_id'])->toBe($existing->getKey())
        ->and($error['next_call'])->toBe(['tool' => 'get_client', 'arguments' => ['client_id' => $existing->getKey()]])
        ->and(mcpClientCount())->toBe(1);
});

it('stops at the seat cap and says so, without retrying', function (): void {
    $staff = McpWorld::staff();
    McpWorld::clientAccount('one@acme.example', 'One');
    config()->set('projectsend.platform.max_clients', 1);

    $error = McpWorld::error(McpWorld::call(CreateClient::class, ['name' => 'Two', 'email' => 'two@acme.example'], $staff));

    expect($error['code'])->toBe('seats_full')
        ->and($error['clients_used'])->toBe(1)
        ->and($error['clients_limit'])->toBe(1)
        ->and($error['action'])->toContain('Do not retry')
        ->and(mcpClientCount())->toBe(1);
});

it('lists clients with how many files they have and when they last signed in', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    McpWorld::clientAccount('zed@example.com', 'Zed');
    $file = McpWorld::file($staff);
    FileAssignment::query()->create(['file_id' => $file->getKey(), 'assignable_type' => $acme->getMorphClass(), 'assignable_id' => $acme->getKey()]);
    ActivityLog::query()->create(['actor_id' => $acme->getKey(), 'action' => 'auth.login', 'origin' => 'ui', 'created_at' => now()->subDay()]);

    $answer = McpWorld::call(ListClients::class, ['query' => 'acme'], $staff);

    expect($answer['total'])->toBe(1)
        ->and($answer['clients'][0]['files_shared_count'])->toBe(1)
        ->and($answer['clients'][0]['last_sign_in_at'])->not->toBeNull()
        ->and($answer['clients'][0]['web_url'])->toBe('https://acme.projectsend.site/clients/'.$acme->getKey());
});

it('answers an unknown address with the call that creates the account, and asks for the name', function (): void {
    $staff = McpWorld::staff();

    $error = McpWorld::error(McpWorld::call(GetClient::class, ['email' => 'new@example.com'], $staff));

    expect($error['code'])->toBe('not_found')
        ->and($error['resource'])->toBe('client')
        ->and($error['next_call'])->toBe(['tool' => 'create_client', 'arguments' => ['email' => 'new@example.com']])
        ->and($error['action'])->toContain('name');
});

it('sends a file to a client once, and says so the second time', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $file = McpWorld::file($staff);

    $first = McpWorld::call(SendFileToClient::class, ['client_email' => 'ops@acme.example', 'file_id' => $file->getKey()], $staff);
    $second = McpWorld::call(SendFileToClient::class, ['client_id' => $acme->getKey(), 'file_id' => $file->getKey()], $staff);

    expect(McpWorld::error($first))->toBeNull(json_encode($first))
        ->and($first['already_shared'])->toBeFalse()
        ->and($first['notified'])->toBeTrue()
        ->and($first['next_steps'])->toContain('Acme Ltd')
        ->and($second['already_shared'])->toBeTrue()
        ->and(FileAssignment::query()->count())->toBe(1)
        ->and(InAppNotification::query()->where('user_id', $acme->id)->count())->toBe(1);
});

it('saves content and sends it in the same call', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();

    $answer = McpWorld::call(SendFileToClient::class, ['client_id' => $acme->getKey(), 'filename' => 'brief.txt', 'content' => 'Brief.'], $staff);

    expect(McpWorld::error($answer))->toBeNull(json_encode($answer))
        ->and($answer['file']['name'])->toBe('brief')
        ->and(FileAssignment::query()->count())->toBe(1);
});
