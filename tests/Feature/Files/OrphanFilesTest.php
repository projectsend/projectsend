<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    Storage::fake('files_external');
    $this->admin = User::factory()->create();
});

function makeAdoptedFile(User $uploader, string $path, string $disk = 'files'): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => basename($path),
        'original_name' => basename($path),
        'path' => $path,
        'disk' => $disk,
        'mime_type' => 'text/plain',
        'size' => 11,
    ]);
}

function orphanImport(array $items): TestResponse
{
    return test()->postJson('/files/orphans/import', ['items' => $items]);
}

function orphanDelete(array $items): TestResponse
{
    return test()->postJson('/files/orphans/delete', ['items' => $items]);
}

test('scanning excludes derived-artifact prefixes and anything already claimed by a file row, including soft-deleted ones', function () {
    makeOrphanFile('2026/07/orphan.pdf');
    makeOrphanFile('thumbnails/2026/07/some.jpg');
    makeOrphanFile('zips/some.zip');

    $adopted = makeAdoptedFile($this->admin, '2026/07/adopted.pdf');
    Storage::disk('files')->put('2026/07/adopted.pdf', 'x');

    $trashed = makeAdoptedFile($this->admin, '2026/07/trashed.pdf');
    Storage::disk('files')->put('2026/07/trashed.pdf', 'x');
    $trashed->delete();

    $this->actingAs($this->admin)->get('/files/orphans')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('files/orphans')
            ->has('orphans', 1)
            ->where('orphans.0.path', '2026/07/orphan.pdf')
            ->where('orphans.0.disk', 'files'),
    );
});

test('by default only the local disk is scanned and shown as the scan location', function () {
    $this->actingAs($this->admin)->get('/files/orphans')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('scanned_disks.files')
            ->missing('scanned_disks.files_external')
            ->where('scanned_disks.files.location', rtrim(Storage::disk('files')->path(''), '/')),
    );
});

test('once external storage is active, both disks are scanned and orphans on either are found', function () {
    activateExternalStorage();

    makeOrphanFile('2026/07/local-orphan.pdf', disk: 'files');
    makeOrphanFile('2026/07/bucket-orphan.pdf', disk: 'files_external');

    $this->actingAs($this->admin)->get('/files/orphans')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('scanned_disks.files')
            ->has('scanned_disks.files_external')
            ->where('scanned_disks.files_external.location', 's3://my-bucket')
            ->has('orphans', 2)
            ->where('orphans.0.disk', 'files')
            ->where('orphans.0.path', '2026/07/local-orphan.pdf')
            ->where('orphans.1.disk', 'files_external')
            ->where('orphans.1.path', '2026/07/bucket-orphan.pdf'),
    );
});

test('a file already adopted on the external disk is excluded from that disk\'s scan the same way local ones are', function () {
    activateExternalStorage();

    makeAdoptedFile($this->admin, '2026/07/adopted.pdf', disk: 'files_external');
    Storage::disk('files_external')->put('2026/07/adopted.pdf', 'x');
    makeOrphanFile('2026/07/orphan.pdf', disk: 'files_external');

    $this->actingAs($this->admin)->get('/files/orphans')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('orphans', 1)
            ->where('orphans.0.path', '2026/07/orphan.pdf'),
    );
});

test('the search filter narrows the scan to matching paths before pagination', function () {
    makeOrphanFile('2026/07/invoice.pdf');
    makeOrphanFile('2026/07/photo.jpg');

    $this->actingAs($this->admin)->get('/files/orphans?search=invoice')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('search', 'invoice')
            ->has('orphans', 1)
            ->where('orphans.0.path', '2026/07/invoice.pdf'),
    );

    $this->actingAs($this->admin)->get('/files/orphans?search=nomatch')->assertInertia(
        fn (AssertableInertia $page) => $page->has('orphans', 0),
    );
});

test('the orphan list is paginated so an installation with thousands of stray files never renders them all at once', function () {
    foreach (range(1, 30) as $i) {
        makeOrphanFile(sprintf('2026/07/orphan-%02d.txt', $i));
    }

    $this->actingAs($this->admin)->get('/files/orphans')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('orphans', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2)
            ->where('orphans.0.path', '2026/07/orphan-01.txt'),
    );

    $this->actingAs($this->admin)->get('/files/orphans?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('orphans', 5)
            ->where('pagination.page', 2),
    );
});

test('requesting a page beyond the last one redirects to the real last page instead of rendering empty', function () {
    foreach (range(1, 30) as $i) {
        makeOrphanFile(sprintf('2026/07/orphan-%02d.txt', $i));
    }

    $this->actingAs($this->admin)->get('/files/orphans?page=99')
        ->assertRedirect('/files/orphans?page=2');
});

test('requesting page=2 when everything fits on page 1 redirects there with no page param', function () {
    makeOrphanFile('2026/07/only-one.txt');

    $this->actingAs($this->admin)->get('/files/orphans?page=2')
        ->assertRedirect('/files/orphans');
});

test('an out-of-range page redirect preserves the current search term', function () {
    makeOrphanFile('2026/07/invoice.pdf');

    $this->actingAs($this->admin)->get('/files/orphans?search=invoice&page=99')
        ->assertRedirect('/files/orphans?search=invoice');
});

test('importing a single orphan creates a real file row, logs the action, and redirects straight to its editor', function () {
    makeOrphanFile('2026/07/orphan.txt', 'hello-world');

    $this->actingAs($this->admin);
    $response = orphanImport([['disk' => 'files', 'path' => '2026/07/orphan.txt']])->assertRedirect();

    $file = File::query()->sole();
    expect($file->path)->toBe('2026/07/orphan.txt')
        ->and($file->disk)->toBe('files')
        ->and($file->original_name)->toBe('orphan.txt')
        ->and($file->uploaded_by)->toBe($this->admin->id)
        ->and($file->folder_id)->toBeNull()
        ->and($file->size)->toBe(11)
        ->and($file->checksum)->toBe(hash('sha256', 'hello-world'))
        ->and(ActivityLog::query()->where('action', Action::FileImported)->where('subject_id', $file->id)->exists())->toBeTrue();

    $response->assertRedirect(route('files.edit', $file));
});

test('importing an orphan from the external disk stores it with that disk and a stream-computed checksum', function () {
    activateExternalStorage();
    makeOrphanFile('2026/07/bucket-orphan.pdf', 'bucket-content', disk: 'files_external');

    $this->actingAs($this->admin);
    orphanImport([['disk' => 'files_external', 'path' => '2026/07/bucket-orphan.pdf']])->assertRedirect();

    $file = File::query()->sole();
    expect($file->disk)->toBe('files_external')
        ->and($file->path)->toBe('2026/07/bucket-orphan.pdf')
        ->and($file->checksum)->toBe(hash('sha256', 'bucket-content'));
});

test('importing multiple orphans at once stays on the list rather than picking one file to redirect to', function () {
    makeOrphanFile('2026/07/one.txt');
    makeOrphanFile('2026/07/two.txt');

    $this->actingAs($this->admin);
    $response = orphanImport([
        ['disk' => 'files', 'path' => '2026/07/one.txt'],
        ['disk' => 'files', 'path' => '2026/07/two.txt'],
    ])->assertRedirect();

    expect(File::query()->count())->toBe(2);

    $importedIds = File::query()->pluck('id');
    foreach ($importedIds as $id) {
        expect($response->headers->get('Location'))->not->toBe(route('files.edit', $id));
    }
});

test('a 0-byte orphan cannot be imported but can still be deleted', function () {
    makeOrphanFile('2026/07/empty.txt', '');

    $this->actingAs($this->admin);
    orphanImport([['disk' => 'files', 'path' => '2026/07/empty.txt']])->assertRedirect();

    expect(File::query()->count())->toBe(0);

    orphanDelete([['disk' => 'files', 'path' => '2026/07/empty.txt']])->assertRedirect();

    Storage::disk('files')->assertMissing('2026/07/empty.txt');
});

test('a viewer without the extension allowance cannot import a restricted file but can still delete it', function () {
    app(Settings::class)->set(Setting::UploadTypeRestriction, 'all');
    app(Settings::class)->set(Setting::AllowedUploadExtensions, ['pdf']);

    makeOrphanFile('2026/07/shell.php', 'not-allowed');

    $this->actingAs($this->admin);
    orphanImport([['disk' => 'files', 'path' => '2026/07/shell.php']])->assertRedirect();

    expect(File::query()->count())->toBe(0);

    orphanDelete([['disk' => 'files', 'path' => '2026/07/shell.php']])->assertRedirect();

    Storage::disk('files')->assertMissing('2026/07/shell.php');
});

test('deleting an orphan removes it from disk and logs the action', function () {
    makeOrphanFile('2026/07/orphan.txt');

    $this->actingAs($this->admin);
    orphanDelete([['disk' => 'files', 'path' => '2026/07/orphan.txt']])->assertRedirect();

    Storage::disk('files')->assertMissing('2026/07/orphan.txt');
    expect(ActivityLog::query()->where('action', Action::OrphanFileDeleted)->where('context->name', 'orphan.txt')->exists())->toBeTrue();
});

test('a path that is not actually an orphan is rejected rather than silently adopted or deleted', function () {
    $adopted = makeAdoptedFile($this->admin, '2026/07/adopted.pdf');
    Storage::disk('files')->put('2026/07/adopted.pdf', 'x');

    $this->actingAs($this->admin);
    orphanImport([['disk' => 'files', 'path' => '2026/07/adopted.pdf']])->assertRedirect();

    expect(File::query()->count())->toBe(1);

    orphanDelete([['disk' => 'files', 'path' => '2026/07/adopted.pdf']])->assertRedirect();

    Storage::disk('files')->assertExists('2026/07/adopted.pdf');

    orphanImport([['disk' => 'files', 'path' => '2026/07/never-existed.pdf']])->assertRedirect();

    expect(File::query()->count())->toBe(1);
});

test('a disk outside the currently scanned set is rejected, even if it is a real configured Laravel disk', function () {
    $this->actingAs($this->admin);

    orphanImport([['disk' => 'public', 'path' => 'whatever.pdf']])->assertJsonValidationErrors('items.0.disk');
    orphanDelete([['disk' => 'public', 'path' => 'whatever.pdf']])->assertJsonValidationErrors('items.0.disk');
});

test('the external disk is rejected until external storage is actually active', function () {
    makeOrphanFile('2026/07/bucket-orphan.pdf', disk: 'files_external');

    $this->actingAs($this->admin);
    orphanImport([['disk' => 'files_external', 'path' => '2026/07/bucket-orphan.pdf']])->assertJsonValidationErrors('items.0.disk');
});

test('the whole surface is unreachable for staff without import_orphans', function () {
    $limited = staffWithPermissions(['upload']);

    $this->actingAs($limited)->get('/files/orphans')->assertForbidden();
    $this->actingAs($limited)->post('/files/orphans/import', ['items' => [['disk' => 'files', 'path' => 'x']]])->assertForbidden();
    $this->actingAs($limited)->post('/files/orphans/delete', ['items' => [['disk' => 'files', 'path' => 'x']]])->assertForbidden();
});
