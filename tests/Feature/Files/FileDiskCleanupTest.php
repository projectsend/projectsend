<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('files');
    Storage::fake('files_external');
    $this->admin = User::factory()->create();
});

function makeStoredFile(array $overrides = []): File
{
    $path = $overrides['path'] ?? '2026/07/'.Str::uuid()->toString().'.pdf';
    $disk = $overrides['disk'] ?? 'files';

    if ($disk !== 'nonexistent-disk') {
        Storage::disk($disk)->put($path, 'hello-world');
    }

    return File::factory()->create([
        'uploaded_by' => test()->admin->id,
        'name' => 'doc',
        'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 11,
        ...$overrides,
        'path' => $path,
        'disk' => $disk,
    ]);
}

test('deleting a file removes its bytes from disk while the row survives as trashed', function () {
    $file = makeStoredFile();

    $this->actingAs($this->admin)->delete("/files/{$file->id}")->assertRedirect();

    Storage::disk('files')->assertMissing($file->path);
    expect(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue();
});

test('deleting a folder cascades disk cleanup to every file inside it', function () {
    $folder = makeFolder('Reports');
    $file = makeStoredFile(['folder_id' => $folder->id]);

    $this->actingAs($this->admin)->delete("/folders/{$folder->id}")->assertRedirect();

    Storage::disk('files')->assertMissing($file->path);
    expect(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue();
});

// Every rendition of every audience, not just the staff thumbnail — a
// file can easily have been viewed only by the client it was shared with,
// in which case the external copies are the only cached ones there are.
test('deleting a file also removes every cached rendition of it', function () {
    $file = makeStoredFile(['mime_type' => 'image/jpeg']);

    $paths = ThumbnailGenerator::pathsFor($file->id, 'image/jpeg');
    expect($paths)->toHaveCount(count(ImageAudience::cases()) * count(ImageRendition::cases()));

    foreach ($paths as $path) {
        Storage::disk('files')->put($path, 'fake-thumbnail-bytes');
    }

    $this->actingAs($this->admin)->delete("/files/{$file->id}")->assertRedirect();

    foreach ($paths as $path) {
        Storage::disk('files')->assertMissing($path);
    }
});

test('a file stored on the external disk is deleted from that disk, not the local one', function () {
    $file = makeStoredFile(['disk' => 'files_external', 'path' => 'ext/doc.pdf']);

    $this->actingAs($this->admin)->delete("/files/{$file->id}")->assertRedirect();

    Storage::disk('files_external')->assertMissing($file->path);
});

// The row comes back on a rollback; the bytes have to still be under it.
// Nothing restores them, so this is the one failure in the whole cleanup
// path that cannot be repaired afterwards.
test('a transaction that rolls back leaves the bytes where they were', function () {
    $file = makeStoredFile();

    try {
        DB::transaction(function () use ($file): void {
            $file->delete();

            throw new RuntimeException('something later in the transaction failed');
        });
    } catch (RuntimeException) {
        // The point of the test is what survives it.
    }

    expect(File::query()->find($file->id))->not->toBeNull();
    Storage::disk('files')->assertExists($file->path);
});

// The shape an account deletion has: content disposal runs in its own
// transaction, nested as a savepoint inside the caller's, and the write
// that fails afterwards belongs to the caller.
test('an outer rollback leaves the bytes even after the inner transaction committed', function () {
    $file = makeStoredFile();

    try {
        DB::transaction(function () use ($file): void {
            DB::transaction(function () use ($file): void {
                $file->delete();
            });

            throw new RuntimeException('the account write after it failed');
        });
    } catch (RuntimeException) {
        // Same.
    }

    expect(File::query()->find($file->id))->not->toBeNull();
    Storage::disk('files')->assertExists($file->path);
});

test('a storage failure while cleaning up disk bytes never blocks the file from being deleted', function () {
    // A disk with no configured driver at all throws immediately on
    // resolution — proves a storage-layer failure (e.g. external storage
    // misconfigured or its credentials rotated after upload) can't turn a
    // routine delete into a 500.
    $file = makeStoredFile(['disk' => 'nonexistent-disk', 'path' => 'whatever.pdf']);

    $this->actingAs($this->admin)->delete("/files/{$file->id}")->assertRedirect();

    expect(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue();
});
