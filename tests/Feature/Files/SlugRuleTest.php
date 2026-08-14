<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Support\Rules;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

/** Run a candidate slug through the shared rule exactly as a controller would. */
function slugFails(string $slug, string $table = 'files', ?int $ignoreId = null, bool $public = true): bool
{
    return Validator::make(
        ['slug' => $slug, 'public' => $public],
        ['slug' => Rules::slug($table, $ignoreId)],
    )->fails();
}

// These land straight in a public URL path segment, so the shape is the
// point: lowercase alphanumerics in hyphen-separated runs, nothing else.
test('a well-formed slug passes', function () {
    expect(slugFails('report'))->toBeFalse()
        ->and(slugFails('quarterly-report'))->toBeFalse()
        ->and(slugFails('report-2026-q1'))->toBeFalse()
        ->and(slugFails('a1'))->toBeFalse();
});

test('a malformed slug is rejected', function (string $slug) {
    expect(slugFails($slug))->toBeTrue();
})->with([
    'uppercase' => ['Report'],
    'underscore' => ['my_report'],
    'leading hyphen' => ['-report'],
    'trailing hyphen' => ['report-'],
    'doubled hyphen' => ['a--b'],
    'space' => ['my report'],
    'slash' => ['a/b'],
    'dot' => ['a.b'],
    'accented' => ['informe-anual-españa'],
    'empty' => [''],
]);

test('a slug already taken by another row is rejected', function () {
    File::factory()->create(['name' => 'Taken', 'slug' => 'taken']);

    expect(slugFails('taken'))->toBeTrue();
});

test('ignoreId lets a row keep the slug it already holds', function () {
    $file = File::factory()->create(['name' => 'Mine', 'slug' => 'mine']);

    expect(slugFails('mine', ignoreId: $file->id))->toBeFalse()
        ->and(slugFails('mine'))->toBeTrue();
});

test('the slug is required only while public is true', function () {
    // Not public: the name-derived slug stands in, so absence is fine.
    expect(Validator::make(['public' => false], ['slug' => Rules::slug('files')])->fails())->toBeFalse()
        ->and(Validator::make(['public' => true], ['slug' => Rules::slug('files')])->fails())->toBeTrue();
});

test('uniqueness is scoped to the table it is asked about', function () {
    File::factory()->create(['name' => 'Shared Name', 'slug' => 'shared-name']);

    // The same slug is free in the folders and groups tables.
    expect(slugFails('shared-name', 'files'))->toBeTrue()
        ->and(slugFails('shared-name', 'folders'))->toBeFalse()
        ->and(slugFails('shared-name', 'groups'))->toBeFalse();
});
