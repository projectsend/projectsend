<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
});

test('a file can be marked as a revision of another', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    expect($revision->fresh()->previous_file_id)->toBe($original->id)
        ->and($revision->fresh()->version_root_id)->toBe($original->id)
        ->and($original->fresh()->version_root_id)->toBeNull()
        ->and($original->fresh()->nextVersion->id)->toBe($revision->id);
});

test('linking the same pair twice is a no-op rather than an error', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);
    $this->versions->link($revision, $original, $this->admin);

    expect(ActivityLog::query()->where('action', Action::FileVersionLinked)->count())->toBe(1);
});

test('a file cannot be a revision of itself', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($file, $file, $this->admin);
})->throws(ValidationException::class, 'A file cannot be a revision of itself.');

test('linking cannot create a loop in the version history', function () {
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    // v1 <- v2 <- v3 already; making v1 a revision of v3 would close the ring.
    expect(fn () => $this->versions->link($v1, $v3, $this->admin))
        ->toThrow(ValidationException::class, 'already part of this file\'s version history');
});

test('a version history cannot grow past the maximum chain length', function () {
    $files = collect(range(1, FileVersions::MAX_CHAIN + 1))
        ->map(fn (): File => File::factory()->create(['uploaded_by' => $this->admin->id]));

    // Build the longest chain that is allowed, then try one more.
    $failedAt = null;

    foreach ($files as $index => $file) {
        if ($index === 0) {
            continue;
        }

        try {
            $this->versions->link($file, $files[$index - 1], $this->admin);
        } catch (ValidationException $e) {
            $failedAt = $index;
            break;
        }
    }

    expect($failedAt)->toBe(FileVersions::MAX_CHAIN);
});

test('an original that already has a revision cannot be picked again', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev A']);
    $first = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev B']);
    $second = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);

    $this->versions->link($first, $original, $this->admin);

    expect(fn () => $this->versions->link($second, $original, $this->admin))
        ->toThrow(ValidationException::class, 'already been revised by "Rev B"');
});

test('linking records an activity entry naming the original', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Floorplan Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Floorplan Rev D']);

    $this->versions->link($revision, $original, $this->admin);

    $entry = ActivityLog::query()->where('action', Action::FileVersionLinked)->firstOrFail();

    expect($entry->subject_id)->toBe($revision->id)
        // A name snapshot, so the entry survives the original's deletion.
        ->and($entry->context['previous'])->toBe('Floorplan Rev C');
});

test('unlinking makes a revision a root again and logs it', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);
    $removed = $this->versions->unlink($revision->fresh(), $this->admin);

    expect($removed)->toBeTrue()
        ->and($revision->fresh()->previous_file_id)->toBeNull()
        ->and($revision->fresh()->version_root_id)->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::FileVersionUnlinked)->count())->toBe(1);
});

test('unlinking a file that was never linked reports nothing was removed', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    expect($this->versions->unlink($file, $this->admin))->toBeFalse();
});

test('every file in a chain is stamped with the same root', function () {
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    expect($v2->fresh()->version_root_id)->toBe($v1->id)
        ->and($v3->fresh()->version_root_id)->toBe($v1->id)
        ->and($v3->fresh()->sharingOwnerId())->toBe($v1->id);
});

test('linking a file that already has revisions re-roots the whole subchain', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);

    // Build v1 <- v2 first, then hang the pair off an earlier original.
    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v1->fresh(), $original, $this->admin);

    expect($v1->fresh()->version_root_id)->toBe($original->id)
        ->and($v2->fresh()->version_root_id)->toBe($original->id);
});

test('linking moves the revision\'s own recipients onto the original', function () {
    $client = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Contractors']);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($revision, $client);
    shareFileWithGroup($revision, $group);

    $this->versions->link($revision, $original, $this->admin);

    // Nobody loses access: the rows move up rather than being dropped.
    expect(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(0)
        ->and(FileAssignment::query()->where('file_id', $original->id)->count())->toBe(2);
});

test('the link preview names the recipients that would move', function () {
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);
    $group = Group::query()->create(['name' => 'Contractors']);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($revision, $client);
    shareFileWithGroup($revision, $group);

    $preview = $this->versions->previewLink($revision, $original);

    expect($preview->isEmpty())->toBeFalse()
        ->and($preview->clientNames)->toBe(['Acme Ltd'])
        ->and($preview->groupNames)->toBe(['Contractors']);
});

test('the link preview omits recipients the original already has', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    shareFileWith($revision, $client);

    expect($this->versions->previewLink($revision, $original)->isEmpty())->toBeTrue();
});

test('unlinking copies the original\'s recipients onto the freed file', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $this->versions->unlink($revision->fresh(), $this->admin);

    // It stops inheriting, so it needs its own rows — unlinking must never
    // silently revoke access someone already had.
    expect(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(1)
        ->and(FileAssignment::query()->where('file_id', $original->id)->count())->toBe(1);
});

test('candidates exclude the file itself, its own revisions, and already-revised files', function () {
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $free = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($v3, $v2, $this->admin);

    // Picking an original for v2: not itself, not v3 (downstream, would
    // cycle), not v1... v1 is free, so it stays.
    $ids = $this->versions->candidates($v2->fresh(), $this->admin)->pluck('id')->all();

    expect($ids)->toContain($v1->id)
        ->and($ids)->toContain($free->id)
        ->and($ids)->not->toContain($v2->id)
        ->and($ids)->not->toContain($v3->id);
});

test('candidates exclude a file that already has a revision', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $subject = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    $ids = $this->versions->candidates($subject, $this->admin)->pluck('id')->all();

    expect($ids)->not->toContain($original->id)
        ->and($ids)->toContain($revision->id);
});

test('candidates can be narrowed by a search term', function () {
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Floorplan Rev C']);
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Invoice 2026-014']);
    $subject = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Floorplan Rev D']);

    $names = $this->versions->candidates($subject, $this->admin, 'Floorplan')->pluck('name')->all();

    expect($names)->toBe(['Floorplan Rev C']);
});

test('the chain lists the whole lineage oldest first', function () {
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'A']);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'B']);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'C']);

    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    expect($this->versions->chain($v2->fresh(), $this->admin)->pluck('name')->all())
        ->toBe(['A', 'B', 'C']);
});
