<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Support\Rules;
use App\Support\VacatedSlug;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('files');
    User::factory()->create();
});

// File, Folder and Group each carried their own byte-identical copy of this
// logic before it moved to the shared trait; the cases below are the ones
// that copy actually handled, asserted against all three so a future model
// picking up the trait inherits the same guarantees.

test('a slug is derived from the name when none is given', function () {
    expect(File::factory()->create(['name' => 'Quarterly Report'])->slug)->toBe('quarterly-report')
        ->and(Folder::query()->create(['name' => 'Client Docs', 'path' => '/'])->slug)->toBe('client-docs')
        ->and(Group::query()->create(['name' => 'Board Members'])->slug)->toBe('board-members');
});

test('an explicitly given slug is left alone', function () {
    expect(File::factory()->create(['name' => 'Report', 'slug' => 'custom-one'])->slug)->toBe('custom-one');
});

test('colliding names get a numeric suffix that keeps counting up', function () {
    expect(File::factory()->create(['name' => 'Report'])->slug)->toBe('report')
        ->and(File::factory()->create(['name' => 'Report'])->slug)->toBe('report-2')
        ->and(File::factory()->create(['name' => 'Report'])->slug)->toBe('report-3');
});

// Deleting used to hold the slug forever, which burned the name: nothing can
// restore a trashed row, so it was reserved for a page that could never come
// back. See issue #1645.
test('deleting a row hands its slug back', function () {
    $first = File::factory()->create(['name' => 'Report']);
    $first->delete();

    expect($first->trashed())->toBeTrue()
        ->and(File::factory()->create(['name' => 'Report'])->slug)->toBe('report');
});

test('the vacated slug is parked where nothing can collide with it', function () {
    $file = File::factory()->create(['name' => 'Report']);
    $file->delete();

    $parked = File::withTrashed()->whereKey($file->id)->value('slug');

    expect($parked)->toBe('report'.VacatedSlug::MARKER.$file->id)
        // Neither route into a slug can produce that string, which is what
        // makes parking there safe rather than merely unlikely.
        ->and(Str::slug($parked))->not->toContain('_')
        ->and(Validator::make(
            ['slug' => $parked, 'public' => true],
            ['slug' => Rules::slug('files')],
        )->fails())->toBeTrue();
});

test('the name is reusable however many times it is deleted', function () {
    foreach (range(1, 3) as $ignored) {
        $file = File::factory()->create(['name' => 'Report']);
        expect($file->slug)->toBe('report');
        $file->delete();
    }

    // Three deleted rows, each parked under its own id rather than piling up
    // as report-2, report-3, report-4 the way the suffix used to.
    expect(File::withTrashed()->where('slug', 'like', 'report'.VacatedSlug::MARKER.'%')->count())->toBe(3);
});

test('all three models hand the slug back', function () {
    $file = File::factory()->create(['name' => 'Shared']);
    $folder = Folder::query()->create(['name' => 'Shared', 'path' => '/']);
    $group = Group::query()->create(['name' => 'Shared']);

    $file->delete();
    $folder->delete();
    $group->delete();

    expect(File::factory()->create(['name' => 'Shared'])->slug)->toBe('shared')
        ->and(Folder::query()->create(['name' => 'Shared', 'path' => '/'])->slug)->toBe('shared')
        ->and(Group::query()->create(['name' => 'Shared'])->slug)->toBe('shared');
});

// The reported bug: a public folder's slug is user-supplied and validated
// against the table, so a trashed row holding it made the name unusable with
// an error naming a row the interface will not show.
test('a deleted public row does not block its slug at the validator', function () {
    $folder = Folder::query()->create(['name' => 'Quarterly', 'path' => '/', 'public' => true, 'slug' => 'quarterly']);
    $folder->delete();

    expect(Validator::make(
        ['slug' => 'quarterly', 'public' => true],
        ['slug' => Rules::slug('folders')],
    )->fails())->toBeFalse();
});

test('force-deleting leaves nothing behind to vacate', function () {
    $file = File::factory()->create(['name' => 'Report']);
    $file->forceDelete();

    expect(File::withTrashed()->whereKey($file->id)->exists())->toBeFalse()
        ->and(File::factory()->create(['name' => 'Report'])->slug)->toBe('report');
});

test('vacating does not look like somebody edited the row', function () {
    $file = File::factory()->create(['name' => 'Report']);

    $this->travel(5)->minutes();
    $file->delete();

    // The soft delete moves updated_at itself; what matters is that vacating
    // the slug afterwards is not a second write on top of it, so the two
    // stamps the delete wrote still agree.
    $row = File::withTrashed()->whereKey($file->id)->sole();

    expect($row->slug)->toBe('report'.VacatedSlug::MARKER.$file->id)
        ->and($row->updated_at)->toEqual($row->deleted_at);
});

test('a name that slugs to nothing falls back to a per-model default', function () {
    expect(File::factory()->create(['name' => '!!!'])->slug)->toBe('file')
        ->and(Folder::query()->create(['name' => '!!!', 'path' => '/'])->slug)->toBe('folder')
        ->and(Group::query()->create(['name' => '!!!'])->slug)->toBe('group');
});

test('ignoreId lets a row keep the slug it already holds', function () {
    $file = File::factory()->create(['name' => 'Report']);

    expect(File::uniqueSlugFrom('Report', $file->id))->toBe('report')
        // Without the exemption the same name has to move out of its own way.
        ->and(File::uniqueSlugFrom('Report'))->toBe('report-2');
});

// The trait boots via bootHasUniqueSlug() precisely so it does not replace
// File::booted(), which is where the disk-cleanup hook lives.
test('the file disk-cleanup hook still fires now that the trait owns slug creation', function () {
    $file = File::factory()->create(['name' => 'Report', 'path' => 'docs/report.pdf']);
    Storage::disk('files')->put('docs/report.pdf', 'bytes');

    $file->delete();

    expect(Storage::disk('files')->exists('docs/report.pdf'))->toBeFalse();
});
