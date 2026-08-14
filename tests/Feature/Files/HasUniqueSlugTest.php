<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use Illuminate\Support\Facades\Storage;

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

test('a soft-deleted row still collides, so its URL is not silently reused', function () {
    $first = File::factory()->create(['name' => 'Report']);
    $first->delete();

    expect($first->trashed())->toBeTrue()
        ->and(File::factory()->create(['name' => 'Report'])->slug)->toBe('report-2');
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
