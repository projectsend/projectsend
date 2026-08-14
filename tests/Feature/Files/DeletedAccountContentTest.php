<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function makeFileIn(User $uploader, ?Folder $folder): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'folder_id' => $folder?->id,
        'name' => 'doc',
        'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 123,
    ]);
}

function makeOwnedFolder(string $name, User $creator, ?Folder $parent = null): Folder
{
    return Folder::query()->create([
        'name' => $name,
        'parent_id' => $parent?->id,
        'created_by' => $creator->id,
        'path' => $parent === null ? '/' : $parent->subtreePathPrefix(),
    ]);
}

test('summarize counts only the files and folders the account owns', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();

    makeFileIn($client, null);
    makeOwnedFolder('Mine', $client);
    makeFileIn($other, null);

    expect(app(DeletedAccountContent::class)->summarize($client))->toBe(['files' => 1, 'folders' => 1]);
});

test('cascade delete removes a fully-owned subtree bottom-up', function () {
    $client = User::factory()->client()->create();

    $root = makeOwnedFolder('Root', $client);
    $child = makeOwnedFolder('Child', $client, $root);
    $fileInChild = makeFileIn($client, $child);
    $standalone = makeFileIn($client, null);

    $result = app(DeletedAccountContent::class)->cascadeDelete($client);

    expect($result)->toBe(['files' => 2, 'folders' => 2])
        ->and(File::withTrashed()->findOrFail($fileInChild->id)->trashed())->toBeTrue()
        ->and(File::withTrashed()->findOrFail($standalone->id)->trashed())->toBeTrue()
        ->and(Folder::withTrashed()->findOrFail($root->id)->trashed())->toBeTrue()
        ->and(Folder::withTrashed()->findOrFail($child->id)->trashed())->toBeTrue();
});

test('cascade delete keeps a folder that still contains another account\'s content, but orphans it', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();

    $shared = makeOwnedFolder('Shared', $client);
    $othersFile = makeFileIn($other, $shared);

    $result = app(DeletedAccountContent::class)->cascadeDelete($client);

    expect($result)->toBe(['files' => 0, 'folders' => 0]);

    $shared->refresh();
    expect($shared->trashed())->toBeFalse()
        ->and($shared->created_by)->toBeNull()
        ->and(File::query()->find($othersFile->id))->not->toBeNull();
});

test('cascade delete never touches another account\'s own files or folders', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();

    $othersFile = makeFileIn($other, null);
    $othersFolder = makeOwnedFolder('Not mine', $other);

    app(DeletedAccountContent::class)->cascadeDelete($client);

    expect(File::query()->find($othersFile->id))->not->toBeNull()
        ->and(Folder::query()->find($othersFolder->id))->not->toBeNull()
        ->and(Folder::query()->find($othersFolder->id)->created_by)->toBe($other->id);
});

test('reassignTo transfers ownership of files and folders without deleting anything', function () {
    $client = User::factory()->client()->create();
    $target = User::factory()->client()->create();

    $file = makeFileIn($client, null);
    $folder = makeOwnedFolder('Mine', $client);

    $result = app(DeletedAccountContent::class)->reassignTo($client, $target);

    expect($result)->toBe(['files' => 1, 'folders' => 1])
        ->and($file->refresh()->uploaded_by)->toBe($target->id)
        ->and($folder->refresh()->created_by)->toBe($target->id)
        ->and($folder->trashed())->toBeFalse();
});
