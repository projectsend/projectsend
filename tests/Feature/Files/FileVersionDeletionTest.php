<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
});

/**
 * Everything here uses ->delete(), never ->forceDelete(): files are
 * soft-deleted in this app, so the hard path is not the one that runs. A
 * suite that tested forceDelete would pass while every real deletion left
 * the chain broken.
 */
test('deleting the original leaves the revision with no version link', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);
    $original->delete();

    expect($revision->fresh()->previous_file_id)->toBeNull()
        ->and($revision->fresh()->version_root_id)->toBeNull();
});

test('deleting a middle version heals the chain instead of breaking it', function () {
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    $v2->fresh()->delete();

    // v3 does revise v1, so saying so is more accurate than amputating.
    expect($v3->fresh()->previous_file_id)->toBe($v1->id)
        ->and($v3->fresh()->version_root_id)->toBe($v1->id);
});

test('deleting a revision frees the original so it can be revised again', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $first = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $second = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($first, $original, $this->admin);
    $first->fresh()->delete();

    // A trashed row still occupies the unique previous_file_id slot unless
    // the delete hook clears it — without that, the original could never be
    // revised again and the error would name a file nobody can see.
    $this->versions->link($second, $original->fresh(), $this->admin);

    expect($second->fresh()->previous_file_id)->toBe($original->id);
});

test('deleting a root hands its recipients down to the promoted revision', function () {
    $client = User::factory()->client()->create();

    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($v1, $client);
    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    $v1->fresh()->delete();

    // v2 and v3 held no assignments of their own; without the hand-down
    // the whole chain would silently lose its audience. That the audience
    // then actually resolves through the new root is asserted in
    // FileVersionSharingTest, which owns the visibility rules.
    expect(FileAssignment::query()->where('file_id', $v2->id)->count())->toBe(1)
        ->and(FileAssignment::query()->where('file_id', $v2->id)->first()->assignable_id)->toBe($client->id)
        ->and($v2->fresh()->version_root_id)->toBeNull()
        ->and($v3->fresh()->version_root_id)->toBe($v2->id);
});

test('deleting the only revision of a root leaves the root untouched', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $revision->fresh()->delete();

    expect($original->fresh()->previous_file_id)->toBeNull()
        ->and($original->fresh()->version_root_id)->toBeNull()
        ->and(FileAssignment::query()->where('file_id', $original->id)->count())->toBe(1);
});

test('a trashed file is never offered as a version candidate', function () {
    $subject = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $gone = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $gone->delete();

    expect($this->versions->candidates($subject, $this->admin)->pluck('id')->all())
        ->not->toContain($gone->id);
});

test('deleting a versioned file still removes its bytes from disk', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    Storage::disk('files')->put($revision->path, 'contents');
    $this->versions->link($revision, $original, $this->admin);

    $revision->fresh()->delete();

    // The chain repair runs in the same deleted() hook as the disk cleanup;
    // adding it must not have displaced the cleanup.
    Storage::disk('files')->assertMissing($revision->path);
});

test('versioning check reports a consistent table and fails on a drifted one', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    $this->artisan('versioning:check')->assertSuccessful();

    // Drift the denormalised column the way a bad write path would.
    File::query()->whereKey($revision->id)->update(['version_root_id' => null]);

    $this->artisan('versioning:check')->assertFailed();
    $this->artisan('versioning:check', ['--repair' => true]);

    expect($revision->fresh()->version_root_id)->toBe($original->id);
});
