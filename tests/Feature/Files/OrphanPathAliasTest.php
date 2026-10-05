<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Storage;

/*
 * The storage layer reads several spellings as one file: "./a/b.txt",
 * "a/./b.txt", "a//b.txt", "/a/b.txt", "a\b.txt" and "a/x/../b.txt" all
 * resolve to "a/b.txt". The orphan check compared the spelling it was
 * given against the paths file rows hold, so any of them made a tracked
 * file look like an orphan: deleting it removed another person's bytes
 * without delete_others_files, and importing it put a second row on them
 * (GHSA-pv88-7863-5hwq). The same spellings walked past the exclusion of
 * derived-artifact folders.
 *
 * A spelling the storage layer would rewrite is refused. The scan only
 * ever offers paths as storage lists them, so nothing honest sends one.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->owner = User::factory()->client()->create();
    $this->tracked = File::factory()->create([
        'uploaded_by' => $this->owner->id,
        'path' => 'audit/client-b.txt',
        'disk' => 'files',
        'mime_type' => 'text/plain',
        'size' => 11,
    ]);
    Storage::disk('files')->put('audit/client-b.txt', 'hello-world');

    $this->staff = staffWithPermissions(['upload', 'import_orphans']);
});

dataset('aliases of a tracked file', [
    'leading dot' => './audit/client-b.txt',
    'inner dot' => 'audit/./client-b.txt',
    'double slash' => 'audit//client-b.txt',
    'leading slash' => '/audit/client-b.txt',
    'backslash' => 'audit\\client-b.txt',
    'climb back' => 'audit/x/../client-b.txt',
]);

test('an alias of a tracked file cannot delete its bytes', function (string $alias) {
    $this->actingAs($this->staff)->postJson('/files/orphans/delete', ['items' => [['disk' => 'files', 'path' => $alias]]]);

    Storage::disk('files')->assertExists('audit/client-b.txt');
})->with('aliases of a tracked file');

test('an alias of a tracked file cannot be adopted as a second file', function (string $alias) {
    $this->actingAs($this->staff)->postJson('/files/orphans/import', ['items' => [['disk' => 'files', 'path' => $alias]]]);

    expect(File::query()->count())->toBe(1);
})->with('aliases of a tracked file');

test('an alias cannot reach into a derived-artifact folder either', function () {
    Storage::disk('files')->put('thumbnails/2026/some.jpg', 'x');

    $this->actingAs($this->staff)->postJson('/files/orphans/delete', ['items' => [['disk' => 'files', 'path' => './thumbnails/2026/some.jpg']]]);

    Storage::disk('files')->assertExists('thumbnails/2026/some.jpg');
});

test('a real orphan is still deleted and imported', function () {
    makeOrphanFile('audit/stray-a.txt');
    makeOrphanFile('audit/stray-b.txt');

    $this->actingAs($this->staff)->postJson('/files/orphans/delete', ['items' => [['disk' => 'files', 'path' => 'audit/stray-a.txt']]]);
    $this->actingAs($this->staff)->postJson('/files/orphans/import', ['items' => [['disk' => 'files', 'path' => 'audit/stray-b.txt']]]);

    Storage::disk('files')->assertMissing('audit/stray-a.txt');
    expect(File::query()->where('path', 'audit/stray-b.txt')->exists())->toBeTrue();
});
