<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\ListFiles;
use App\Modules\Mcp\Tools\ListFolders;
use App\Modules\Mcp\Tools\ShareFolderWithClient;
use Illuminate\Support\Facades\Notification;
use Tests\Support\McpWorld;

/*
 * The assistant's folder and file lists give the same counts as the
 * library page. For a staff member limited to some clients they used to
 * count everything: how many files, subfolders and shares they were not
 * allowed to know about.
 */

beforeEach(function (): void {
    McpContext::set(null);
    config()->set('app.url', 'https://acme.projectsend.site');
    Notification::fake();

    $this->staff = McpWorld::staff();
    $this->other = McpWorld::staff();
    $this->acme = McpWorld::clientAccount();
    $this->globex = McpWorld::clientAccount('it@globex.example', 'Globex');
});

function shareWithForCounts(object $item, User $client): void
{
    $item instanceof Folder
        ? FolderAssignment::query()->create(['folder_id' => $item->getKey(), 'assignable_type' => $client->getMorphClass(), 'assignable_id' => $client->id])
        : FileAssignment::query()->create(['file_id' => $item->getKey(), 'assignable_type' => $client->getMorphClass(), 'assignable_id' => $client->id]);
}

it('counts only what a scoped staff member may see on a folder', function (): void {
    $mine = McpWorld::folder('Mine', creator: $this->staff);
    // Put there by somebody else, for a client off this roster.
    McpWorld::file($this->other, ['folder_id' => $mine->getKey()]);
    McpWorld::folder('Hidden', $mine, $this->other);
    shareWithForCounts($mine, $this->globex);
    // Theirs.
    McpWorld::file($this->staff, ['folder_id' => $mine->getKey()]);

    McpWorld::scopeTo($this->staff, $this->acme);

    $answer = McpWorld::call(ListFolders::class, [], $this->staff);

    expect(McpWorld::error($answer))->toBeNull()
        ->and($answer['folders'][0])->toMatchArray([
            'name' => 'Mine',
            'folders_count' => 0,
            'files_count' => 1,
            'shared_with_count' => 0,
        ]);
});

it('counts only the shares a scoped staff member may know about on a file', function (): void {
    $file = McpWorld::file($this->staff);
    shareWithForCounts($file, $this->acme);
    shareWithForCounts($file, $this->globex);

    McpWorld::scopeTo($this->staff, $this->acme);

    $answer = McpWorld::call(ListFiles::class, [], $this->staff);

    expect(McpWorld::error($answer))->toBeNull()
        ->and($answer['files'][0]['shared_with_count'])->toBe(1);
});

it('still counts everything for staff who see the whole library', function (): void {
    $folder = McpWorld::folder('Everything');
    McpWorld::file($this->other, ['folder_id' => $folder->getKey()]);
    McpWorld::folder('Sub', $folder);
    shareWithForCounts($folder, $this->globex);
    $file = McpWorld::file($this->staff);
    shareWithForCounts($file, $this->acme);
    shareWithForCounts($file, $this->globex);

    expect(McpWorld::call(ListFolders::class, [], $this->staff)['folders'][0])->toMatchArray([
        'folders_count' => 1,
        'files_count' => 1,
        'shared_with_count' => 1,
    ]);

    $files = collect(McpWorld::call(ListFiles::class, [], $this->staff)['files']);
    expect($files->firstWhere('id', $file->getKey())['shared_with_count'])->toBe(2);
});

it('refuses to share a folder holding hidden files without saying how many', function (): void {
    $mine = McpWorld::folder('Mine', creator: $this->staff);
    $sub = McpWorld::folder('Sub', $mine, $this->staff);
    McpWorld::file($this->staff, ['folder_id' => $mine->getKey()]);
    McpWorld::file($this->other, ['folder_id' => $sub->getKey()]);
    McpWorld::file($this->other, ['folder_id' => $sub->getKey()]);

    McpWorld::scopeTo($this->staff, $this->acme);

    $error = McpWorld::error(McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $mine->getKey(), 'client_id' => $this->acme->getKey()], $this->staff));

    expect($error['errors']['folder_id'] ?? null)->toBe(['This folder cannot be shared: it holds files you may not share.'])
        ->and(FolderAssignment::query()->count())->toBe(0);
});

it('counts the files a folder share covers', function (): void {
    $mine = McpWorld::folder('Mine', creator: $this->staff);
    $sub = McpWorld::folder('Sub', $mine, $this->staff);
    McpWorld::file($this->staff, ['folder_id' => $mine->getKey()]);
    McpWorld::file($this->staff, ['folder_id' => $sub->getKey()]);

    McpWorld::scopeTo($this->staff, $this->acme);

    $answer = McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $mine->getKey(), 'client_id' => $this->acme->getKey()], $this->staff);

    expect(McpWorld::error($answer))->toBeNull()
        ->and($answer['files_count'])->toBe(2);
});
