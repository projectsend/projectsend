<?php

declare(strict_types=1);

use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\CreateFolder;
use App\Modules\Mcp\Tools\GetFile;
use App\Modules\Mcp\Tools\ListFiles;
use App\Modules\Mcp\Tools\ListFolders;
use App\Modules\Mcp\Tools\MoveFile;
use App\Modules\Mcp\Tools\ShareFolderWithClient;
use App\Modules\Notifications\InAppNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Support\McpWorld;

/*
 * The staff library's folders: finding one, making one, filing a file
 * in one and giving a client a whole one — each under the rule the web
 * asks, each written to the activity log as the assistant, and none of
 * it offered to a client account.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'https://acme.projectsend.site');
    Notification::fake();
});

it('lists the top level, opens a folder by parent_id, and searches the whole tree by name', function (): void {
    $staff = McpWorld::staff();
    $clients = McpWorld::folder('Clients');
    $acme = McpWorld::folder('Acme', $clients);
    McpWorld::folder('2026', $acme);
    McpWorld::folder('Archive');
    McpWorld::file($staff, ['folder_id' => $acme->getKey()]);

    $top = McpWorld::call(ListFolders::class, [], $staff);

    expect(McpWorld::error($top))->toBeNull()
        ->and(array_column($top['folders'], 'name'))->toBe(['Archive', 'Clients'])
        ->and($top['parent'])->toBeNull()
        ->and($top['web_url'])->toBe('https://acme.projectsend.site/files');

    $inside = McpWorld::call(ListFolders::class, ['parent_id' => $clients->getKey()], $staff);

    expect($inside['parent']['name'])->toBe('Clients')
        ->and($inside['folders'][0])->toMatchArray([
            'name' => 'Acme',
            'path' => 'Clients / Acme',
            'parent_id' => $clients->getKey(),
            'folders_count' => 1,
            'files_count' => 1,
            'is_client_home' => false,
        ])
        ->and($inside['web_url'])->toBe('https://acme.projectsend.site/files?folder='.$clients->getKey());

    $found = McpWorld::call(ListFolders::class, ['query' => '2026'], $staff);

    expect($found['total'])->toBe(1)
        ->and($found['folders'][0]['path'])->toBe('Clients / Acme / 2026');
});

it('answers an unknown parent with not_found and the call that finds the right one', function (): void {
    $error = McpWorld::error(McpWorld::call(ListFolders::class, ['parent_id' => 99], McpWorld::staff()));

    expect($error['code'])->toBe('not_found')
        ->and($error['resource'])->toBe('folder')
        ->and($error['next_call'])->toBe(['tool' => 'list_folders', 'arguments' => []]);
});

it('creates a folder inside another as the assistant, and returns the same one when asked again', function (): void {
    $staff = McpWorld::staff();
    $clients = McpWorld::folder('Clients');

    $answer = McpWorld::call(CreateFolder::class, ['name' => 'Acme', 'parent_id' => $clients->getKey()], $staff);

    expect(McpWorld::error($answer))->toBeNull()
        ->and($answer['created'])->toBeTrue()
        ->and($answer['folder']['path'])->toBe('Clients / Acme')
        ->and($answer['folder']['parent_id'])->toBe($clients->getKey())
        ->and($answer['next_steps'])->toContain('folder_id '.$answer['folder']['id']);

    $folder = Folder::query()->findOrFail($answer['folder']['id']);

    expect($folder->path)->toBe('/'.$clients->getKey().'/')
        ->and((int) $folder->created_by)->toBe($staff->getKey());

    $entry = ActivityLog::query()->where('action', 'folder.created')->first();

    expect($entry->origin->value)->toBe('mcp')
        ->and($entry->api_token_name)->toBe('Claude')
        ->and((int) $entry->subject_id)->toBe($folder->getKey());

    // A retry is not how a library gets two "Acme" folders.
    $again = McpWorld::call(CreateFolder::class, ['name' => 'Acme', 'parent_id' => $clients->getKey()], $staff);

    expect($again['created'])->toBeFalse()
        ->and($again['folder']['id'])->toBe($folder->getKey())
        ->and(Folder::query()->where('name', 'Acme')->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', 'folder.created')->count())->toBe(1);
});

it('creates at the top level when no parent is given', function (): void {
    $answer = McpWorld::call(CreateFolder::class, ['name' => 'Invoices'], McpWorld::staff());

    expect($answer['folder'])->toMatchArray(['name' => 'Invoices', 'path' => 'Invoices', 'parent_id' => null]);
});

it('asks what the web asks before creating: the folder permission, a parent that exists, the depth cap', function (): void {
    $without = McpWorld::staff(permissions: ['upload', 'edit_files']);

    expect(McpWorld::error(McpWorld::call(CreateFolder::class, ['name' => 'Acme'], $without)))
        ->toMatchArray(['code' => 'permission_missing', 'permission' => 'create_own_folders']);

    $staff = McpWorld::staff();

    expect(McpWorld::error(McpWorld::call(CreateFolder::class, ['name' => 'Acme', 'parent_id' => 42], $staff))['code'])
        ->toBe('not_found');

    // Nine levels above it already: a tenth is the host's limit.
    $deep = McpWorld::folder('deep', attributes: ['path' => '/1/2/3/4/5/6/7/8/9/']);
    $error = McpWorld::error(McpWorld::call(CreateFolder::class, ['name' => 'too-deep', 'parent_id' => $deep->getKey()], $staff));

    expect($error['code'])->toBe('validation_failed')
        ->and($error['errors'])->toHaveKey('parent_id');
});

it('moves a file into a folder and back out, and says so in every file it presents', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::folder('Acme');
    $file = McpWorld::file($staff);

    $moved = McpWorld::call(MoveFile::class, ['file_id' => $file->getKey(), 'folder_id' => $acme->getKey()], $staff);

    expect(McpWorld::error($moved))->toBeNull()
        ->and($moved['moved'])->toBeTrue()
        ->and($moved['from'])->toBeNull()
        ->and($moved['to']['name'])->toBe('Acme')
        ->and($moved['file']['folder'])->toBe(['id' => $acme->getKey(), 'name' => 'Acme'])
        ->and((int) $file->fresh()->folder_id)->toBe($acme->getKey());

    $entry = ActivityLog::query()->where('action', 'file.updated')->first();

    expect($entry->origin->value)->toBe('mcp')
        ->and((int) $entry->subject_id)->toBe($file->getKey());

    // The folder travels with the file wherever a file is shown.
    expect(McpWorld::call(ListFiles::class, [], $staff)['files'][0]['folder']['name'])->toBe('Acme')
        ->and(McpWorld::call(GetFile::class, ['file_id' => $file->getKey()], $staff)['file']['folder']['name'])->toBe('Acme');

    $again = McpWorld::call(MoveFile::class, ['file_id' => $file->getKey(), 'folder_id' => $acme->getKey()], $staff);

    expect($again['moved'])->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'file.updated')->count())->toBe(1);

    $out = McpWorld::call(MoveFile::class, ['file_id' => $file->getKey(), 'to_top_level' => true], $staff);

    expect($out['moved'])->toBeTrue()
        ->and($out['from']['name'])->toBe('Acme')
        ->and($out['to'])->toBeNull()
        ->and($out['file']['folder'])->toBeNull()
        ->and($file->fresh()->folder_id)->toBeNull();
});

it('needs exactly one of folder_id and to_top_level, so a forgotten argument never empties a folder', function (): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff, ['folder_id' => McpWorld::folder('Acme')->getKey()]);

    foreach ([['file_id' => $file->getKey()], ['file_id' => $file->getKey(), 'folder_id' => 1, 'to_top_level' => true]] as $arguments) {
        expect(McpWorld::error(McpWorld::call(MoveFile::class, $arguments, $staff)))
            ->toMatchArray(['code' => 'validation_failed']);
    }

    expect($file->fresh()->folder_id)->not->toBeNull();
});

it('will not publish a file by moving it into a public folder without the right to publish', function (): void {
    $editor = McpWorld::staff();
    $public = McpWorld::folder('Press kit', attributes: ['public' => true]);
    $file = McpWorld::file($editor);

    $error = McpWorld::error(McpWorld::call(MoveFile::class, ['file_id' => $file->getKey(), 'folder_id' => $public->getKey()], $editor));

    expect($error['code'])->toBe('permission_missing')
        ->and($error['permission'])->toBe('upload_public')
        ->and($file->fresh()->folder_id)->toBeNull();

    $publisher = McpWorld::staff(permissions: ['upload', 'edit_files', 'edit_others_files', 'upload_public']);

    expect(McpWorld::call(MoveFile::class, ['file_id' => $file->getKey(), 'folder_id' => $public->getKey()], $publisher)['moved'])->toBeTrue();
});

it('shares a folder with a client once: the assignment, the activity entry, the in-app notice and the digest', function (): void {
    $staff = McpWorld::staff();
    $acme = McpWorld::clientAccount();
    $folder = McpWorld::folder('Acme', creator: $staff);
    $inside = McpWorld::folder('2026', $folder);
    McpWorld::file($staff, ['folder_id' => $folder->getKey()]);
    McpWorld::file($staff, ['folder_id' => $inside->getKey()]);
    McpWorld::file($staff);

    $answer = McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $folder->getKey(), 'client_email' => 'ops@acme.example'], $staff);

    expect(McpWorld::error($answer))->toBeNull()
        ->and($answer['already_shared'])->toBeFalse()
        ->and($answer['notified'])->toBeTrue()
        ->and($answer['client']['id'])->toBe($acme->getKey())
        // Subfolders included: the share is live over the whole subtree.
        ->and($answer['files_count'])->toBe(2)
        ->and(FolderAssignment::query()->where('folder_id', $folder->getKey())->where('assignable_id', $acme->getKey())->count())->toBe(1)
        ->and(InAppNotification::query()->where('user_id', $acme->id)->where('type', 'file_shared')->count())->toBe(1);

    $entry = ActivityLog::query()->where('action', 'folder.shared')->first();

    expect($entry->origin->value)->toBe('mcp')
        ->and($entry->context)->toBe(['target' => 'Acme Ltd']);

    $again = McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $folder->getKey(), 'client_id' => $acme->getKey()], $staff);

    expect($again['already_shared'])->toBeTrue()
        ->and($again['notified'])->toBeFalse()
        ->and(InAppNotification::query()->where('user_id', $acme->id)->count())->toBe(1)
        ->and(FolderAssignment::query()->count())->toBe(1);
});

it('points at create_client when no client has the address', function (): void {
    $staff = McpWorld::staff();
    $folder = McpWorld::folder('Acme');

    $error = McpWorld::error(McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $folder->getKey(), 'client_email' => 'New@Acme.example'], $staff));

    expect($error['code'])->toBe('not_found')
        ->and($error['next_call'])->toBe(['tool' => 'create_client', 'arguments' => ['email' => 'new@acme.example']]);
});

it('holds sharing to the folder policy: somebody else\'s folder takes edit_others_files', function (): void {
    $owner = McpWorld::staff();
    $editor = McpWorld::staff(permissions: ['upload', 'edit_files']);
    McpWorld::clientAccount();
    $folder = McpWorld::folder('Acme', creator: $owner);

    $error = McpWorld::error(McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $folder->getKey(), 'client_email' => 'ops@acme.example'], $editor));

    expect($error['code'])->toBe('permission_missing')
        ->and($error['permission'])->toBe('edit_others_files')
        ->and(FolderAssignment::query()->count())->toBe(0);
});

it('keeps a client-scoped staff member inside their library and their clients', function (): void {
    $staff = McpWorld::staff();
    $mine = McpWorld::folder('Mine', creator: $staff);
    $theirs = McpWorld::folder('Theirs');
    $acme = McpWorld::clientAccount();
    $globex = McpWorld::clientAccount('it@globex.example', 'Globex');
    $file = McpWorld::file($staff);

    McpWorld::scopeTo($staff, $acme);

    expect(array_column(McpWorld::call(ListFolders::class, [], $staff)['folders'], 'name'))->toBe(['Mine'])
        ->and(McpWorld::error(McpWorld::call(MoveFile::class, ['file_id' => $file->getKey(), 'folder_id' => $theirs->getKey()], $staff))['code'])->toBe('not_found')
        ->and(McpWorld::error(McpWorld::call(CreateFolder::class, ['name' => 'x', 'parent_id' => $theirs->getKey()], $staff))['code'])->toBe('not_found');

    // Somebody else's client is not on this roster at all: not found,
    // as the clients screen would not list it.
    $error = McpWorld::error(McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $mine->getKey(), 'client_id' => $globex->getKey()], $staff));

    expect($error['code'])->toBe('not_found')
        ->and($error['resource'])->toBe('client')
        ->and(FolderAssignment::query()->count())->toBe(0);

    expect(McpWorld::error(McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $mine->getKey(), 'client_id' => $acme->getKey()], $staff)))->toBeNull();
});

it('lists every folder tool to a free account and answers with what a portal of their own adds, the free way first', function (string $tool, array $arguments, string $alternative): void {
    $client = McpWorld::client();

    // Listed, like the client tools (decisions 16 and 17): what the free
    // plan lacks is the moment to say what Pro adds, so it is never hidden.
    McpContext::set(null);
    $this->actingAs($client);

    expect((new $tool)->shouldRegister())->toBeTrue();

    $error = McpWorld::error(McpWorld::call($tool, $arguments, $client));

    expect($error['code'])->toBe('feature_requires_plan')
        ->and($error['alternative_tool'])->toBe($alternative)
        ->and($error['action'])->toStartWith('Folders come with a portal of their own (Pro, from $19/month).')
        ->and($error['action'])->toContain($alternative)
        ->and($error['plan']['name'])->toBe('Pro')
        // Nothing to call instead with the same arguments: the alternative
        // is a different way of working, which the action says.
        ->and($error)->not->toHaveKey('next_call')
        ->and(Folder::query()->count())->toBe(0);

    parse_str((string) parse_url($error['upgrade_url'], PHP_URL_QUERY), $query);

    expect($query['utm_content'])->toBe((new $tool)->name());
})->with([
    'list_folders' => [ListFolders::class, [], 'list_files'],
    'create_folder' => [CreateFolder::class, ['name' => 'Contracts'], 'list_files'],
    'move_file' => [MoveFile::class, ['file_id' => 1, 'folder_id' => 1], 'list_files'],
    'share_folder_with_client' => [ShareFolderWithClient::class, ['folder_id' => 1, 'client_id' => 1], 'share_by_link'],
]);

it('never names a staff folder to a client account', function (): void {
    $client = McpWorld::client();
    $folder = McpWorld::folder('Internal');
    $file = McpWorld::file($client, ['folder_id' => $folder->getKey()]);

    expect(McpWorld::call(GetFile::class, ['file_id' => $file->getKey()], $client)['file']['folder'])->toBeNull()
        ->and(McpWorld::call(ListFiles::class, [], $client)['files'][0]['folder'])->toBeNull();
});

it('lists the folder tools to an API token only with the abilities they need', function (): void {
    $uploader = McpWorld::staff(tokenAbilities: ['upload'], tokenName: 'n8n');

    McpContext::set(null);
    $this->actingAs($uploader);

    expect((new ListFolders)->shouldRegister())->toBeTrue()
        ->and((new CreateFolder)->shouldRegister())->toBeFalse()
        ->and((new MoveFile)->shouldRegister())->toBeFalse();

    $organiser = McpWorld::staff(tokenAbilities: ['create_own_folders', 'edit_files'], tokenName: 'n8n');

    McpContext::set(null);
    $this->actingAs($organiser);

    expect((new CreateFolder)->shouldRegister())->toBeTrue()
        ->and((new MoveFile)->shouldRegister())->toBeTrue()
        ->and((new ShareFolderWithClient)->shouldRegister())->toBeTrue();
});

it('tells a read-only connection how to reconnect before any folder is touched', function (): void {
    $staff = McpWorld::staff(tokenAbilities: ['mcp:read']);

    foreach ([
        [CreateFolder::class, ['name' => 'Acme']],
        [MoveFile::class, ['file_id' => 1, 'to_top_level' => true]],
        [ShareFolderWithClient::class, ['folder_id' => 1, 'client_id' => 1]],
    ] as [$tool, $arguments]) {
        expect(McpWorld::error(McpWorld::call($tool, $arguments, $staff))['code'])->toBe('scope_insufficient');
    }

    expect(Folder::query()->count())->toBe(0);
});
