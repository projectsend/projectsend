<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Storage;

/*
 * Deleting several files at once from the staff selection bar (#1800).
 * Each file is asked the same question a single delete asks, and one the
 * person may not delete is left alone rather than failing the batch.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function bulkDeleteFile(User $owner): File
{
    $file = File::factory()->create(['uploaded_by' => $owner->id]);
    Storage::disk('files')->put($file->path, 'bytes');

    return $file;
}

test('several files are deleted at once, each logged as a delete', function () {
    $a = bulkDeleteFile($this->admin);
    $b = bulkDeleteFile($this->admin);
    $kept = bulkDeleteFile($this->admin);

    $this->actingAs($this->admin)->delete(route('files.bulk-destroy'), ['file_ids' => [$a->id, $b->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(File::query()->find($a->id))->toBeNull()
        ->and(File::query()->find($b->id))->toBeNull()
        ->and(File::query()->find($kept->id))->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::FileDeleted)->count())->toBe(2);
});

test('a file this person may not delete is left alone, and the rest still go', function () {
    // May delete their own files, not other people's.
    $uploader = staffWithPermissions(['upload', 'delete_files']);
    $own = bulkDeleteFile($uploader);
    $somebodyElses = bulkDeleteFile($this->admin);

    $this->actingAs($uploader)->delete(route('files.bulk-destroy'), ['file_ids' => [$own->id, $somebodyElses->id]])
        ->assertRedirect();

    expect(File::query()->find($own->id))->toBeNull()
        ->and(File::query()->find($somebodyElses->id))->not->toBeNull();
});

test('a batch with nothing this person may delete is refused', function () {
    $uploader = staffWithPermissions(['upload', 'delete_files']);
    $somebodyElses = bulkDeleteFile($this->admin);

    $this->actingAs($uploader)->delete(route('files.bulk-destroy'), ['file_ids' => [$somebodyElses->id]])
        ->assertStatus(422);

    expect(File::query()->find($somebodyElses->id))->not->toBeNull();
});

test('clients cannot reach it', function () {
    $client = User::factory()->client()->create();
    $file = bulkDeleteFile($client);

    $this->actingAs($client)->delete(route('files.bulk-destroy'), ['file_ids' => [$file->id]]);

    expect(File::query()->find($file->id))->not->toBeNull();
});
