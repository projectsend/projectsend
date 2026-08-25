<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// QUEUE_CONNECTION=sync in phpunit.xml — BuildZipDownloadJob runs
// synchronously within the same request, so the zip is already built by
// the time a `postJson('/zip-downloads', ...)` call returns.

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function zipUploadFile(User $as, string $name, ?int $folderId = null): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name, 4, 'application/pdf'),
        'name' => '',
        'description' => '',
        'folder_id' => $folderId,
    ]);

    return File::query()->latest('id')->firstOrFail();
}

/**
 * @return list<string>
 */
function zipEntryNames(ZipDownload $zipDownload): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('files')->path($zipDownload->refresh()->path));

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

test('zipping a folder builds an archive with the hierarchy preserved', function () {
    $parent = Folder::query()->create(['name' => 'Reports']);
    $child = Folder::query()->create(['name' => 'Q1', 'parent_id' => $parent->id, 'path' => "/{$parent->id}/"]);

    zipUploadFile($this->admin, 'summary.pdf', $parent->id);
    zipUploadFile($this->admin, 'detail.pdf', $child->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$parent->id]])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));
    expect($zipDownload->status)->toBe(ZipDownload::STATUS_READY)
        ->and($zipDownload->file_count)->toBe(2);

    expect(zipEntryNames($zipDownload))->toContain('Reports/summary.pdf', 'Reports/Q1/detail.pdf');
});

test('filename collisions get a numbered suffix', function () {
    $fileA = zipUploadFile($this->admin, 'notes.pdf');
    $fileB = zipUploadFile($this->admin, 'notes.pdf');

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', [
        'file_ids' => [$fileA->id, $fileB->id],
    ])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    expect(zipEntryNames($zipDownload))->toContain('notes.pdf', 'notes (2).pdf');
});

test('a mixed loose-file and folder selection places files at the root and folders as subfolders', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $loose = zipUploadFile($this->admin, 'loose.pdf');
    zipUploadFile($this->admin, 'inside.pdf', $folder->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', [
        'file_ids' => [$loose->id],
        'folder_ids' => [$folder->id],
    ])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    expect(zipEntryNames($zipDownload))->toContain('loose.pdf', 'Reports/inside.pdf');
});

test('inaccessible files are silently dropped, rejecting the request if nothing remains', function () {
    $client = User::factory()->client()->create();
    $secretFile = zipUploadFile($this->admin, 'secret.pdf');

    $this->actingAs($client)->postJson('/zip-downloads', ['file_ids' => [$secretFile->id]])->assertStatus(422);
});

test('a client can zip a file assigned to them', function () {
    $client = User::factory()->client()->create();
    $file = zipUploadFile($this->admin, 'shared.pdf');
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $response = $this->actingAs($client)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));
    expect($zipDownload->file_ids)->toBe([$file->id]);
});

test('a client cannot zip a folder that is not shared with them', function () {
    $client = User::factory()->client()->create();
    $folder = Folder::query()->create(['name' => 'Private']);
    zipUploadFile($this->admin, 'a.pdf', $folder->id);

    $this->actingAs($client)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertStatus(422);
});

// Holding a folder never implied being able to read everything inside it —
// expiry hides a file from a client while leaving it in place — so the job
// re-derives visibility per file instead of inheriting it from the folder.
test('zipping a shared folder excludes files the client may no longer see', function () {
    $client = User::factory()->client()->create();
    $folder = Folder::query()->create(['name' => 'Shared']);
    $this->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $live = zipUploadFile($this->admin, 'live.pdf', $folder->id);
    $expired = zipUploadFile($this->admin, 'expired.pdf', $folder->id);
    $expired->update(['expires_at' => now()->subDay()]);

    $response = $this->actingAs($client)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertOk();
    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    expect(zipEntryNames($zipDownload))->toBe(['Shared/live.pdf'])
        ->and($zipDownload->file_count)->toBe(1);

    // Staff keep access to an expired file, so the same folder still zips
    // both for them — the rule is per-viewer, not a property of the file.
    $staffResponse = $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertOk();
    expect(zipEntryNames(ZipDownload::query()->findOrFail($staffResponse->json('id'))))
        ->toEqualCanonicalizing(['Shared/live.pdf', 'Shared/expired.pdf']);

    expect($live->fresh())->not->toBeNull();
});

test('a client cannot zip a loose file that expired after it was shared', function () {
    $client = User::factory()->client()->create();
    $file = zipUploadFile($this->admin, 'a.pdf');
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $file->update(['expires_at' => now()->subDay()]);

    $this->actingAs($client)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertStatus(422);
});

test('the download endpoint logs a FileDownloaded entry for every bundled file', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $file = zipUploadFile($this->admin, 'a.pdf', $folder->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertOk();
    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    $this->actingAs($this->admin)->get("/zip-downloads/{$zipDownload->id}/download")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$zipDownload->path)
        ->assertHeader('Content-Disposition', 'attachment; filename="Reports.zip"');

    expect(ActivityLog::query()->where('action', Action::FileDownloaded)->where('subject_id', $file->id)->exists())->toBeTrue();
});

test('a pending zip download 404s until ready', function () {
    $zipDownload = ZipDownload::query()->create(['requested_by' => $this->admin->id, 'status' => ZipDownload::STATUS_PENDING]);

    $this->actingAs($this->admin)->get("/zip-downloads/{$zipDownload->id}/download")->assertNotFound();
});

test('only the requester can poll or download their own zip', function () {
    $other = User::factory()->create();
    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_READY,
        'path' => 'zips/whatever.zip',
    ]);

    $this->actingAs($other)->getJson("/zip-downloads/{$zipDownload->id}")->assertNotFound();
    $this->actingAs($other)->get("/zip-downloads/{$zipDownload->id}/download")->assertNotFound();
});

test('an oversized selection is rejected', function () {
    $folder = Folder::query()->create(['name' => 'Huge']);

    $now = now();
    $rows = [];
    for ($i = 0; $i < 10001; $i++) {
        $rows[] = [
            'folder_id' => $folder->id,
            'name' => "file{$i}",
            // Bulk insert() bypasses Eloquent's creating() event (the
            // auto-slug fallback), so a unique one is supplied directly.
            'slug' => "huge-file-{$i}",
            'original_name' => "file{$i}.txt",
            'path' => "huge/file{$i}.txt",
            'mime_type' => 'text/plain',
            'size' => 1,
            'checksum' => str_repeat('a', 64),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    File::query()->insert($rows);

    $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertStatus(422);
});

test('the purge command removes zip downloads and files older than 24 hours', function () {
    Storage::disk('files')->put('zips/old.zip', 'x');
    Storage::disk('files')->put('zips/new.zip', 'x');

    $old = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_READY,
        'path' => 'zips/old.zip',
    ]);
    $old->forceFill(['created_at' => now()->subDays(2)])->save();

    $new = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_READY,
        'path' => 'zips/new.zip',
    ]);

    $this->artisan('projectsend:purge-zip-downloads')->assertSuccessful();

    expect(ZipDownload::query()->find($old->id))->toBeNull()
        ->and(ZipDownload::query()->find($new->id))->not->toBeNull()
        ->and(Storage::disk('files')->exists('zips/old.zip'))->toBeFalse()
        ->and(Storage::disk('files')->exists('zips/new.zip'))->toBeTrue();
});

// The store guard rejects an empty selection, but a file selected and then
// removed before the queued job runs leaves nothing to add. libzip writes
// no file at all for a zero-entry archive, so a "ready" row would point the
// download controller at a path that does not exist.
test('a build with no available files is marked failed rather than ready over an empty archive', function () {
    $file = zipUploadFile($this->admin, 'gone.pdf');

    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$file->id],
        'folder_ids' => [],
    ]);

    // Gone from the requester's view by the time the job builds the archive.
    $file->delete();

    (new BuildZipDownloadJob($zipDownload->id))->handle();

    $zipDownload->refresh();

    expect($zipDownload->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($zipDownload->path)->toBeNull()
        ->and(Storage::disk('files')->exists("zips/{$zipDownload->id}.zip"))->toBeFalse();
});

// ZipArchive reads each source only at close(); if the bytes vanish in
// between (a staff delete triggers FileDiskCleanup at once) close() returns
// false. The row must fail rather than go ready over an archive that was
// never actually written to disk.
test('a source file deleted after it was queued fails the build instead of serving a broken archive', function () {
    $file = zipUploadFile($this->admin, 'vanishing.pdf');

    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$file->id],
        'folder_ids' => [],
    ]);

    // The row stays visible, but its bytes are gone before the build closes.
    Storage::disk('files')->delete($file->path);

    (new BuildZipDownloadJob($zipDownload->id))->handle();

    $zipDownload->refresh();

    expect($zipDownload->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($zipDownload->path)->toBeNull()
        ->and(Storage::disk('files')->exists("zips/{$zipDownload->id}.zip"))->toBeFalse();
});

// A build that blows the worker timeout is killed mid-run: handle()'s own
// catch never executes, so without this hook the row would poll as pending
// forever and the frontend would never stop.
test('the failed hook fails a still-pending row when the worker gives up on the job', function () {
    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
    ]);

    (new BuildZipDownloadJob($zipDownload->id))->failed(new RuntimeException('timed out'));

    $zipDownload->refresh();

    expect($zipDownload->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($zipDownload->error)->not->toBeNull();
});

// A late failure signal (a retry racing a build that already finished) must
// not overwrite a row that already delivered a ready archive.
test('the failed hook leaves a row that already finished alone', function () {
    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_READY,
        'path' => 'zips/whatever.zip',
    ]);

    (new BuildZipDownloadJob($zipDownload->id))->failed(new RuntimeException('too late'));

    expect($zipDownload->refresh()->status)->toBe(ZipDownload::STATUS_READY);
});

test('the build job runs once and allows enough time for a large archive', function () {
    $job = new BuildZipDownloadJob(1);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBeGreaterThan(60);
});

// A build killed before it finished (worker timeout, full disk) leaves a
// partial archive — and libzip's temp file beside it — on disk but never
// writes a path back to the row. Purge keys off the row id so it clears
// them anyway.
test('the purge command removes leftover archives even when the row never recorded a path', function () {
    $row = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_FAILED,
    ]);
    $row->forceFill(['created_at' => now()->subDays(2)])->save();

    Storage::disk('files')->put("zips/{$row->id}.zip", 'partial');
    Storage::disk('files')->put("zips/{$row->id}.zip.tmp0a1b2c", 'libzip temp');

    $this->artisan('projectsend:purge-zip-downloads')->assertSuccessful();

    expect(ZipDownload::query()->find($row->id))->toBeNull()
        ->and(Storage::disk('files')->exists("zips/{$row->id}.zip"))->toBeFalse()
        ->and(Storage::disk('files')->exists("zips/{$row->id}.zip.tmp0a1b2c"))->toBeFalse();
});

// original_name is uploader-chosen and validated only for length, so it
// must not be able to steer where an entry lands inside the archive.
test('a traversing filename cannot escape the archive as a zip entry', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $file = zipUploadFile($this->admin, 'ok.pdf', $folder->id);
    // Set it directly: the sanitizer on the intake path would already have
    // stripped this, and the point here is that the zip builder does not
    // depend on that having happened.
    $file->forceFill(['original_name' => '../../../.bashrc'])->save();

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertOk();

    $names = zipEntryNames(ZipDownload::query()->findOrFail($response->json('id')));

    expect($names)->toHaveCount(1);

    // Dots in a filename are fine — what must not exist is a path *segment*
    // that walks up, which is what an extractor acts on.
    $segments = explode('/', $names[0]);

    expect($segments)->toHaveCount(2)
        ->and($segments)->not->toContain('..')
        ->and($names[0])->toStartWith('Reports/');
});
