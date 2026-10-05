<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Jobs\ImportOrphanFilesJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\OrphanImportProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
 * The background "import all" runs as the staff member who started it, in
 * chunks, for as long as it takes. Three edges of that, from the review of
 * #1809.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->staff = staffWithPermissions(['upload', 'import_orphans']);
    makeOrphanFile('2026/10/one.txt');
    makeOrphanFile('2026/10/two.txt');
    app(OrphanImportProgress::class)->tryStart(2);
});

test('a chunk does not go on for an account that has lost the permission', function () {
    $this->staff->role->permissions()->where('permission', 'import_orphans')->delete();
    forgetRequestState();

    (new ImportOrphanFilesJob($this->staff->id, null))->handle(
        app(App\Modules\Files\OrphanFileScanner::class),
        app(App\Modules\Files\OrphanFileImporter::class),
        app(OrphanImportProgress::class),
    );

    expect(File::query()->count())->toBe(0)
        ->and(app(OrphanImportProgress::class)->current()['status'])->toBe('failed');
});

test('a chunk does not go on for an account that was deactivated', function () {
    $this->staff->forceFill(['active' => false])->save();

    ImportOrphanFilesJob::dispatch($this->staff->id, null);

    expect(File::query()->count())->toBe(0)
        ->and(app(OrphanImportProgress::class)->current()['status'])->toBe('failed');
});

test('an account that keeps the permission still imports everything', function () {
    ImportOrphanFilesJob::dispatch($this->staff->id, null);

    expect(File::query()->count())->toBe(2)
        ->and(app(OrphanImportProgress::class)->current()['status'])->toBe('finished');
});

test('a path another chunk is adopting is left to it, and never adopted twice', function () {
    // Another chunk holds 2026/10/one.txt right now.
    $held = Cache::lock('orphan-files-import:'.sha1('files|2026/10/one.txt'), 600);
    expect($held->get())->toBeTrue();

    ImportOrphanFilesJob::dispatch($this->staff->id, null);

    expect(File::query()->pluck('path')->all())->toBe(['2026/10/two.txt']);

    $held->release();
});

test('a path that gained a row after the scan is skipped', function () {
    // Adopted by someone else between this chunk's scan and its import.
    $scanner = Mockery::mock(App\Modules\Files\OrphanFileScanner::class);
    $scanner->shouldReceive('importable')->andReturn([['disk' => 'files', 'path' => '2026/10/one.txt']]);
    File::factory()->create(['disk' => 'files', 'path' => '2026/10/one.txt', 'uploaded_by' => $this->staff->id]);

    (new ImportOrphanFilesJob($this->staff->id, null))->handle(
        $scanner,
        app(App\Modules\Files\OrphanFileImporter::class),
        app(OrphanImportProgress::class),
    );

    expect(File::query()->where('path', '2026/10/one.txt')->count())->toBe(1);
});

test('a failure shows a plain message and keeps the details for the log', function () {
    Log::spy();

    (new ImportOrphanFilesJob($this->staff->id, null))
        ->failed(new RuntimeException('Error executing "PutObject" on "https://bucket.s3.example/secret-path"'));

    $error = app(OrphanImportProgress::class)->current()['error'];

    expect($error)->not->toContain('bucket')->not->toContain('PutObject');
    Log::shouldHaveReceived('error')->once();
});
