<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Uploads\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Folder uses SoftDeletes, so `exists:folders,id` — which runs against
 * the table — passes for a folder in the trash, while every resolution
 * that follows goes through Folder::query() and finds nothing. The
 * upload paths wrote the id anyway.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $folder = app(FolderService::class)->create('Doomed', null);
    $this->trashedId = $folder->id;
    app(FolderService::class)->delete($folder);

    expect(Folder::query()->whereKey($this->trashedId)->exists())->toBeFalse()
        ->and(Folder::withTrashed()->whereKey($this->trashedId)->exists())->toBeTrue();
});

test('a web upload cannot be filed into a deleted folder', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('b.txt', 1, 'text/plain'),
        'folder_id' => $this->trashedId,
    ])->assertSessionHasErrors('folder_id');

    expect(File::query()->where('original_name', 'b.txt')->exists())->toBeFalse();
});

test('an API upload cannot be filed into a deleted folder', function () {
    $token = $this->admin->createToken('t', ['upload'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/files', [
        'file' => UploadedFile::fake()->create('c.txt', 1, 'text/plain'),
        'folder_id' => $this->trashedId,
    ])->assertJsonValidationErrors('folder_id');

    expect(File::query()->where('original_name', 'c.txt')->exists())->toBeFalse();
});

test('a file already in the library cannot be moved into a deleted folder', function () {
    $file = uploadNamedFile($this->admin, 'movable');

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => 'movable', 'folder_id' => $this->trashedId])
        ->assertSessionHasErrors('folder_id');

    expect($file->fresh()->folder_id)->toBeNull();
});

test('the API cannot move one there either', function () {
    $file = uploadNamedFile($this->admin, 'api-movable');
    $token = $this->admin->createToken('t', ['edit_files'])->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/files/{$file->id}", ['folder_id' => $this->trashedId])
        ->assertJsonValidationErrors('folder_id');

    expect($file->fresh()->folder_id)->toBeNull();
});

test('a chunked upload says so up front instead of quietly using the root', function () {
    $this->actingAs($this->admin)->postJson('/uploads', [
        'filename' => 'a.txt',
        'size' => 5,
        'folder_id' => $this->trashedId,
    ])->assertJsonValidationErrors('folder_id');

    expect(UploadSession::query()->count())->toBe(0);
});

test('the paths that resolve through the library scope keep refusing too', function () {
    $file = uploadNamedFile($this->admin, 'movable');

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}/move", ['folder_id' => $this->trashedId])
        ->assertSessionHasErrors('folder_id');

    $this->actingAs($this->admin)->patch('/files/bulk-edit', [
        'file_ids' => [$file->id],
        'folder_action' => 'move',
        'folder_id' => $this->trashedId,
        'description_action' => 'no_change',
        'expiration_action' => 'no_change',
    ])->assertSessionHasErrors('folder_id');

    expect($file->fresh()->folder_id)->toBeNull();
});

test('a folder cannot be created inside, or moved into, a deleted one', function () {
    $this->actingAs($this->admin)
        ->post('/folders', ['name' => 'Orphan', 'parent_id' => $this->trashedId])
        ->assertSessionHasErrors('parent_id');

    $folder = app(FolderService::class)->create('Live', null);

    $this->actingAs($this->admin)
        ->patch("/folders/{$folder->id}/move", ['parent_id' => $this->trashedId])
        ->assertSessionHasErrors('parent_id');

    expect(Folder::query()->where('name', 'Orphan')->exists())->toBeFalse()
        ->and($folder->fresh()->parent_id)->toBeNull();
});

test('a live folder is still a perfectly good upload target', function () {
    $live = app(FolderService::class)->create('Live', null);

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('ok.txt', 1, 'text/plain'),
        'folder_id' => $live->id,
    ])->assertSessionHasNoErrors();

    expect(File::query()->where('original_name', 'ok.txt')->value('folder_id'))->toBe($live->id);

    $this->actingAs($this->admin)->postJson('/uploads', [
        'filename' => 'chunk.txt',
        'size' => 5,
        'folder_id' => $live->id,
    ])->assertOk();

    expect(UploadSession::query()->value('folder_id'))->toBe($live->id);
});

test('the root is still the root', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('root.txt', 1, 'text/plain'),
        'folder_id' => null,
    ])->assertSessionHasNoErrors();

    expect(File::query()->where('original_name', 'root.txt')->value('folder_id'))->toBeNull();
});

// The refusal has to say why: "The selected folder id is invalid" tells
// somebody nothing when the answer is that the folder has been deleted.
test('the refusal explains itself', function () {
    $folder = app(FolderService::class)->create('Gone', null);
    app(FolderService::class)->delete($folder);

    $this->actingAs($this->admin)
        ->postJson('/uploads', [
            'filename' => 'report.pdf',
            'size' => 11,
            'type' => 'application/pdf',
            'folder_id' => $folder->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.folder_id.0', 'That folder no longer exists. Pick another one and try again.');
});
