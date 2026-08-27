<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// QUEUE_CONNECTION=sync in phpunit.xml — BuildZipDownloadJob runs
// synchronously within the same request, so the zip is already built by
// the time a `postJson('/zip-downloads', ...)` call returns.

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback), so the cap every test below builds on is
    // set rather than assumed — and reset here for the tests that lower it.
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 2048);
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

    expect($zipDownload->fresh()->contained_file_ids)->toBe([$file->id]);
});

test('a file added to the folder after the build is not counted as downloaded', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $bundled = zipUploadFile($this->admin, 'a.pdf', $folder->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertOk();
    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    // The archive is written by now. Anything that lands in the folder
    // from here on is not in it.
    $late = zipUploadFile($this->admin, 'b.pdf');
    $late->update(['folder_id' => $folder->id]);

    $this->actingAs($this->admin)->get("/zip-downloads/{$zipDownload->id}/download")->assertOk();

    expect(zipEntryNames($zipDownload))->toBe(['Reports/a.pdf'])
        ->and($bundled->downloads()->count())->toBe(1)
        ->and($late->downloads()->count())->toBe(0);
});

test('a file moved out of the folder after the build is still counted', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $elsewhere = Folder::query()->create(['name' => 'Elsewhere']);
    $file = zipUploadFile($this->admin, 'a.pdf', $folder->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])->assertOk();
    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    // Moving it does not take its bytes back out of the archive, so the
    // download still hands it over and still has to say so.
    $file->update(['folder_id' => $elsewhere->id]);

    $this->actingAs($this->admin)->get("/zip-downloads/{$zipDownload->id}/download")->assertOk();

    expect($file->downloads()->count())->toBe(1);
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

// The cap is on bytes, not on file count: bytes are what the build
// actually costs — worker time, temp copies from a remote disk, and the
// archive itself. The message names both numbers so the person who asked
// knows how much to deselect.
test('a selection larger than the zip download limit is rejected, and says how large it was', function () {
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 1);

    $file = zipUploadFile($this->admin, 'huge.pdf');
    $file->update(['size' => 5 * 1024 * 1024]);

    $response = $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertStatus(422);

    expect($response->json('message'))->toContain('5.0 MB')->toContain('1 MB')
        ->and(ZipDownload::query()->count())->toBe(0);
});

test('a zip download limit of zero means no limit', function () {
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 0);

    $file = zipUploadFile($this->admin, 'huge.pdf');
    $file->update(['size' => 50 * 1024 * 1024]);

    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertOk();
});

// The controller checks what was asked for; the job checks again, because
// the selection is re-derived at build time and a folder can grow while
// the job waits in the queue.
test('a selection that grew past the limit after it was queued fails the build', function () {
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 1);

    $file = zipUploadFile($this->admin, 'grown.pdf');

    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$file->id],
        'folder_ids' => [],
    ]);

    $file->update(['size' => 5 * 1024 * 1024]);

    (new BuildZipDownloadJob($zipDownload->id))->handle();

    $zipDownload->refresh();

    expect($zipDownload->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($zipDownload->path)->toBeNull()
        ->and(Storage::disk('files')->exists("zips/{$zipDownload->id}.zip"))->toBeFalse();
});

// A zip holds the single queue worker for as long as it takes to write,
// and every notification email waits behind it.
test('a build already in progress blocks a second request from the same person', function () {
    ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
    ]);

    $file = zipUploadFile($this->admin, 'later.pdf');

    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertStatus(429);
});

test('one person\'s build in progress does not block anybody else', function () {
    $other = User::factory()->create();

    ZipDownload::query()->create([
        'requested_by' => $other->id,
        'status' => ZipDownload::STATUS_PENDING,
    ]);

    $file = zipUploadFile($this->admin, 'mine.pdf');

    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertOk();
});

// failed() resolves a row the queue gave up on, but a worker killed hard
// enough never runs it. Nobody should be locked out forever by a row that
// nothing is ever going to finish.
test('an abandoned pending build stops blocking after an hour', function () {
    $stale = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
    ]);
    $stale->forceFill(['created_at' => now()->subHours(2)])->save();

    $file = zipUploadFile($this->admin, 'again.pdf');

    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertOk();
});

// "Nothing left to send" and "you have already had these as often as you
// were meant to" are different answers, and the list of what was left out
// belongs on the row either way.
test('a build where every file had already hit its download limit says so, and lists them', function () {
    // Uploaded by somebody else: the uploader is exempt from their own
    // file's limit, so the requester has to be a different person for the
    // allowance to mean anything.
    $file = zipUploadFile(User::factory()->create(), 'spent.pdf');
    $file->update(['download_limit' => 1]);

    ActivityLog::query()->create([
        'actor_id' => $this->admin->id,
        'actor_name' => $this->admin->name,
        'actor_type' => $this->admin->type->value,
        'action' => Action::FileDownloaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'created_at' => now(),
    ]);

    $zipDownload = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$file->id],
        'folder_ids' => [],
    ]);

    (new BuildZipDownloadJob($zipDownload->id))->handle();

    $zipDownload->refresh();

    expect($zipDownload->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($zipDownload->error)->toContain('download limit')
        ->and($zipDownload->skipped_files)->toHaveCount(1)
        ->and($zipDownload->skipped_files[0]['id'])->toBe($file->id);
});

// zip_downloads.requested_by cascades on delete, so removing a user takes
// their rows with it and leaves every archive they built behind — nothing
// keyed on rows can ever see those again.
test('the purge command removes an archive whose row no longer exists', function () {
    $this->admin; // setup complete

    Storage::disk('files')->put('zips/4242.zip', 'stranded');
    touch(Storage::disk('files')->path('zips/4242.zip'), now()->subDays(2)->getTimestamp());

    // Younger than the grace period: this one could still belong to a
    // build that is running right now.
    Storage::disk('files')->put('zips/4243.zip', 'just built');

    $this->artisan('projectsend:purge-zip-downloads')->assertSuccessful();

    expect(Storage::disk('files')->exists('zips/4242.zip'))->toBeFalse()
        ->and(Storage::disk('files')->exists('zips/4243.zip'))->toBeTrue();
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

// Its own queue, so one hour-long build cannot hold up every notification
// email behind it. Both shipped topologies run a worker for it; a manual
// install is told to watch `default,zips` (INSTALL.md, and the upgrade
// note in CHANGELOG.md), because a worker that is not listening finishes
// no zips and says nothing about why.
test('a zip build is queued away from ordinary work', function () {
    Queue::fake();

    $file = zipUploadFile($this->admin, 'a.pdf');

    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertOk();

    Queue::assertPushed(BuildZipDownloadJob::class, fn (BuildZipDownloadJob $job): bool => $job->queue === 'zips');
});

/*
|--------------------------------------------------------------------------
| A selection that reaches the same file more than once
|--------------------------------------------------------------------------
*/

test('a file reached by both a loose pick and a folder is added once', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $file = zipUploadFile($this->admin, 'report.pdf', $folder->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', [
        'file_ids' => [$file->id],
        'folder_ids' => [$folder->id],
    ])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    // The loose pick reaches it first, so that is where the one copy sits.
    expect(zipEntryNames($zipDownload))->toBe(['report.pdf'])
        ->and($zipDownload->file_count)->toBe(1)
        ->and($zipDownload->total_size)->toBe($file->size)
        ->and($zipDownload->contained_file_ids)->toBe([$file->id]);
});

test('a folder inside another selected folder does not duplicate its contents', function () {
    $parent = Folder::query()->create(['name' => 'Reports']);
    $child = Folder::query()->create(['name' => 'Q1', 'parent_id' => $parent->id, 'path' => "/{$parent->id}/"]);
    $file = zipUploadFile($this->admin, 'report.pdf', $child->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', [
        'folder_ids' => [$parent->id, $child->id],
    ])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    // The outer folder wins, so the entry keeps the fuller path.
    expect(zipEntryNames($zipDownload))->toBe(['Reports/Q1/report.pdf'])
        ->and($zipDownload->file_count)->toBe(1)
        ->and($zipDownload->total_size)->toBe($file->size);
});

test('the same file selected three ways is handed over once and charged once', function () {
    $parent = Folder::query()->create(['name' => 'Reports']);
    $child = Folder::query()->create(['name' => 'Q1', 'parent_id' => $parent->id, 'path' => "/{$parent->id}/"]);
    $file = zipUploadFile($this->admin, 'report.pdf', $child->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', [
        'file_ids' => [$file->id],
        'folder_ids' => [$parent->id, $child->id],
    ])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));
    $this->actingAs($this->admin)->get("/zip-downloads/{$zipDownload->id}/download")->assertOk();

    // Delivery logs one FileDownloaded per contained file, so as many
    // copies as the archive holds must be as many as the log records —
    // otherwise a file limited to one download leaves in several.
    $logged = ActivityLog::query()
        ->where('action', Action::FileDownloaded)
        ->where('subject_id', $file->id)
        ->count();

    expect(count(zipEntryNames($zipDownload)))->toBe(1)
        ->and($logged)->toBe(1);
});

test('two selected folders that merely share a name are both zipped', function () {
    // The pruning above is about containment, not about names: neither of
    // these is inside the other, so both belong in the archive, and the
    // usual collision suffix keeps them apart.
    $first = Folder::query()->create(['name' => 'Reports']);
    $second = Folder::query()->create(['name' => 'Reports']);
    zipUploadFile($this->admin, 'a.pdf', $first->id);
    zipUploadFile($this->admin, 'b.pdf', $second->id);

    $response = $this->actingAs($this->admin)->postJson('/zip-downloads', [
        'folder_ids' => [$first->id, $second->id],
    ])->assertOk();

    $zipDownload = ZipDownload::query()->findOrFail($response->json('id'));

    expect($zipDownload->file_count)->toBe(2)
        ->and(zipEntryNames($zipDownload))->toContain('Reports/a.pdf');
});
