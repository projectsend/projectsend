<?php

declare(strict_types=1);

use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Mcp\ProjectSendServer;
use App\Modules\Mcp\Resources\ActivityRecent;
use App\Modules\Mcp\Resources\Clients;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\CreateClient;
use App\Modules\Mcp\Tools\GetClient;
use App\Modules\Mcp\Tools\GetDownloadStatus;
use App\Modules\Mcp\Tools\GetFile;
use App\Modules\Mcp\Tools\ListClients;
use App\Modules\Mcp\Tools\ListFiles;
use App\Modules\Mcp\Tools\SendFileToClient;
use Illuminate\Support\Facades\Notification;
use Tests\Support\McpWorld;

/*
 * A client-scoped staff member reaches their own uploads and their
 * assigned clients, on the web and so through the assistant: the same
 * StaffLibraryScope, ActivityLogScope and roster.
 */

beforeEach(function (): void {
    McpContext::set(null);
    Notification::fake();
});

it('lists and opens only the files a scoped staff member reaches on the web', function (): void {
    $other = McpWorld::staff();
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $mine = McpWorld::file($staff, ['name' => 'mine']);
    $acmes = McpWorld::file($other, ['name' => 'for-acme']);
    shareFileWith($acmes, $acme);
    $elsewhere = McpWorld::file($other, ['name' => 'not-theirs']);

    McpWorld::scopeTo($staff, $acme);

    $names = array_column(McpWorld::call(ListFiles::class, [], $staff)['files'], 'name');
    sort($names);

    expect($names)->toBe(['for-acme', 'mine'])
        ->and(McpWorld::error(McpWorld::call(GetFile::class, ['file_id' => $elsewhere->id], $staff))['code'])->toBe('not_found')
        ->and(McpWorld::error(McpWorld::call(GetDownloadStatus::class, ['file_id' => $elsewhere->id], $staff))['code'])->toBe('not_found')
        ->and(McpWorld::error(McpWorld::call(GetFile::class, ['file_id' => $mine->id], $staff)))->toBeNull();
});

it('lists, opens and sends to only the clients on a scoped staff member\'s roster', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $globex = McpWorld::clientAccount('it@globex.example', 'Globex');
    $file = McpWorld::file($staff);

    McpWorld::scopeTo($staff, $acme);

    $listed = McpWorld::call(ListClients::class, [], $staff);

    expect($listed['total'])->toBe(1)
        ->and($listed['clients'][0]['id'])->toBe($acme->id)
        ->and(McpWorld::error(McpWorld::call(GetClient::class, ['client_id' => $globex->id], $staff))['code'])->toBe('not_found')
        ->and(McpWorld::error(McpWorld::call(GetClient::class, ['email' => 'it@globex.example'], $staff))['code'])->toBe('not_found')
        ->and(McpWorld::error(McpWorld::call(GetDownloadStatus::class, ['client_id' => $globex->id], $staff))['code'])->toBe('not_found');

    $sent = McpWorld::error(McpWorld::call(SendFileToClient::class, ['client_id' => $globex->id, 'file_id' => $file->id], $staff));

    expect($sent['code'])->toBe('not_found')
        ->and(FileAssignment::query()->count())->toBe(0);

    $resource = ProjectSendServer::actingAs($staff)->resource(Clients::class);
    $resource->assertSee('Acme Ltd')->assertDontSee('Globex');
});

it('reads a scoped staff member only the activity the log page shows them', function (): void {
    $staff = McpWorld::staff();
    $other = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    McpWorld::scopeTo($staff, $acme);

    $elsewhere = McpWorld::file($other, ['name' => 'not-theirs']);
    ActivityLog::query()->create(['actor_id' => $other->id, 'actor_name' => 'Someone else', 'action' => 'file.updated', 'origin' => 'ui', 'subject_type' => $elsewhere->getMorphClass(), 'subject_id' => $elsewhere->id, 'subject_name' => 'not-theirs', 'created_at' => now()]);
    ActivityLog::query()->create(['actor_id' => $staff->id, 'actor_name' => 'Ada Lovelace', 'action' => 'auth.login', 'origin' => 'ui', 'created_at' => now()]);

    ProjectSendServer::actingAs($staff)->resource(ActivityRecent::class)
        ->assertSee('auth.login')
        ->assertDontSee('not-theirs');
});

it('keeps the client a scoped staff member creates on their own roster', function (): void {
    $staff = McpWorld::staff();
    McpWorld::scopeTo($staff);

    $answer = McpWorld::call(CreateClient::class, ['name' => 'Initech', 'email' => 'bill@initech.example', 'notify' => false], $staff);

    expect(McpWorld::error($answer))->toBeNull(json_encode($answer))
        ->and($staff->assignedClients()->pluck('users.id')->all())->toBe([$answer['client']['id']]);
});

it('refuses an address another account holds without naming that account', function (): void {
    $staff = McpWorld::staff();
    $globex = McpWorld::clientAccount('it@globex.example', 'Globex');
    $colleague = McpWorld::staff();
    McpWorld::scopeTo($staff);

    foreach (['it@globex.example', $colleague->email] as $taken) {
        $answer = McpWorld::call(CreateClient::class, ['name' => 'Whoever', 'email' => $taken, 'notify' => false], $staff);
        $error = McpWorld::error($answer);

        expect($error['code'])->toBe('validation_failed', json_encode($answer))
            ->and($error['errors'])->toHaveKey('email')
            ->and(json_encode($answer))->not->toContain((string) $globex->id.',')
            ->and(json_encode($answer))->not->toContain('Globex');
    }
});

/*
 * GHSA-9rch-3cmp-whh4. Being able to read a file and being told who a
 * client is are two questions. get_file answered the first and skipped
 * the second, so a file shared with a manager's own client named the
 * stranger who uploaded it and every off-roster co-recipient.
 */
it('does not name an off-roster client who uploaded a file a scoped staff member can open', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $globex = McpWorld::clientAccount('it@globex.example', 'Globex');
    $file = McpWorld::file($globex, ['name' => 'from-globex']);
    shareFileWith($file, $acme);

    McpWorld::scopeTo($staff, $acme);

    $got = McpWorld::call(GetFile::class, ['file_id' => $file->id], $staff);

    expect(McpWorld::error($got))->toBeNull()
        ->and($got['file']['uploaded_by'])->toBeNull()
        ->and(json_encode($got))->not->toContain('Globex');
});

it('names an on-roster client who uploaded a file', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $file = McpWorld::file($acme, ['name' => 'from-acme']);
    shareFileWith($file, $acme);

    McpWorld::scopeTo($staff, $acme);

    $got = McpWorld::call(GetFile::class, ['file_id' => $file->id], $staff);

    expect($got['file']['uploaded_by'])->toBe(['name' => 'Acme Ltd']);
});

it('leaves off-roster clients and groups out of who a file is shared with', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $globex = McpWorld::clientAccount('it@globex.example', 'Globex');
    $ours = Group::query()->create(['name' => 'Acme team']);
    $ours->members()->attach($acme->id);
    $theirs = Group::query()->create(['name' => 'Globex team']);
    $theirs->members()->attach($globex->id);

    $file = McpWorld::file($staff, ['name' => 'shared-with-both']);
    shareFileWith($file, $acme);
    shareFileWith($file, $globex);
    shareFileWithGroup($file, $ours);
    shareFileWithGroup($file, $theirs);

    McpWorld::scopeTo($staff, $acme);

    $got = McpWorld::call(GetFile::class, ['file_id' => $file->id], $staff);

    expect($got['shared_with']['clients'])->toBe([['id' => $acme->id, 'name' => 'Acme Ltd']])
        ->and($got['shared_with']['groups'])->toBe([['id' => $ours->id, 'name' => 'Acme team']])
        ->and(json_encode($got))->not->toContain('Globex');
});

it('still names every recipient for unscoped staff', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $globex = McpWorld::clientAccount('it@globex.example', 'Globex');
    $file = McpWorld::file($globex);
    shareFileWith($file, $acme);
    shareFileWith($file, $globex);

    $got = McpWorld::call(GetFile::class, ['file_id' => $file->id], $staff);

    expect($got['file']['uploaded_by'])->toBe(['name' => 'Globex'])
        ->and(array_column($got['shared_with']['clients'], 'name'))->toEqualCanonicalizing(['Acme Ltd', 'Globex']);
});
