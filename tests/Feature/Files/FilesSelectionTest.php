<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\ScanStatus;

/*
 * "Select all :count items" on the library screen. files.selection hands
 * back the ids of every row the listing would show for the same filters,
 * across all of its pages, so the bulk actions can be sent for them.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function selectionFile(User $uploader, string $name, ?int $folderId = null): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => $name,
        'original_name' => "{$name}.pdf",
        'folder_id' => $folderId,
    ]);
}

test('every folder and file in the current folder comes back, past the first page', function () {
    $folders = app(FolderService::class);
    $folderIds = [];
    for ($i = 1; $i <= 30; $i++) {
        $folderIds[] = $folders->create(sprintf('Folder %02d', $i), null)->id;
    }
    $fileIds = [];
    for ($i = 1; $i <= 10; $i++) {
        $fileIds[] = selectionFile($this->admin, "file-{$i}")->id;
    }
    // Inside a subfolder, so not a row of the top of the library.
    $nested = selectionFile($this->admin, 'nested', $folderIds[0]);

    $response = $this->actingAs($this->admin)->getJson(route('files.selection'))->assertOk();

    expect($response->json('folder_ids'))->toEqualCanonicalizing($folderIds)
        ->and($response->json('file_ids'))->toEqualCanonicalizing($fileIds)
        ->and($response->json('file_ids'))->not->toContain($nested->id);

    $inside = $this->actingAs($this->admin)->getJson(route('files.selection', ['folder' => $folderIds[0]]))->assertOk();

    expect($inside->json('folder_ids'))->toBe([])
        ->and($inside->json('file_ids'))->toBe([$nested->id]);
});

test('the filters narrow it exactly as they narrow the list', function () {
    $report = selectionFile($this->admin, 'quarterly report');
    selectionFile($this->admin, 'holiday photo');
    $folder = app(FolderService::class)->create('Reports', null);

    $response = $this->actingAs($this->admin)->getJson(route('files.selection', ['search' => 'report']))->assertOk();

    expect($response->json('file_ids'))->toBe([$report->id])
        ->and($response->json('folder_ids'))->toBe([$folder->id]);
});

test('a quarantined file is left out, as it is from the list', function () {
    $clean = selectionFile($this->admin, 'clean');
    File::factory()->create(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Infected]);

    $this->actingAs($this->admin)->getJson(route('files.selection'))
        ->assertOk()
        ->assertJsonPath('file_ids', [$clean->id]);
});

test('only the files this person may delete are offered for deletion', function () {
    $uploader = staffWithPermissions(['upload', 'delete_files']);
    $own = selectionFile($uploader, 'mine');
    $somebodyElses = selectionFile($this->admin, 'theirs');

    $response = $this->actingAs($uploader)->getJson(route('files.selection'))->assertOk();

    expect($response->json('file_ids'))->toEqualCanonicalizing([$own->id, $somebodyElses->id])
        ->and($response->json('deletable_file_ids'))->toBe([$own->id]);
});

test('clients cannot reach it', function () {
    $client = User::factory()->client()->create();
    selectionFile($client, 'theirs');

    $this->actingAs($client)->getJson(route('files.selection'))->assertForbidden();
});
