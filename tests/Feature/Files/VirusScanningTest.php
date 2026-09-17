<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Jobs\ScanFileJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\ScanVerdict;
use App\Modules\Files\Scanning\VirusScanner;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FakeVirusScanner;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // Settings survive RefreshDatabase's rollback in the cache, so every
    // value this file depends on is stated rather than assumed.
    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, 'tcp://scanner.test:3310');
    app(Settings::class)->set(Setting::VirusScanMaxSizeMb, 512);
    app(Settings::class)->set(Setting::VirusUnscannablePolicy, 'allow');
    app(Settings::class)->set(Setting::VirusScannerDownPolicy, 'allow');
    app(Settings::class)->set(Setting::VirusScannerWaitMinutes, 10);
});

function fakeScanner(?ScanVerdict $verdict = null): FakeVirusScanner
{
    $scanner = new FakeVirusScanner($verdict);
    app()->instance(VirusScanner::class, $scanner);

    return $scanner;
}

/** A stored file with real bytes, as an upload would leave it. */
function scannableFile(array $overrides = []): File
{
    $path = 'uploads/'.Str::uuid()->toString().'.pdf';
    Storage::disk('files')->put($path, 'some bytes');

    return File::factory()->create(array_merge([
        'path' => $path,
        'disk' => 'files',
        'size' => 10,
        'scan_status' => ScanStatus::Pending,
        'checksum' => hash('sha256', $path),
    ], $overrides));
}

/** Run the job the way the queue would, with this test's fake scanner. */
function runScan(File $file): void
{
    (new ScanFileJob($file->id))->handle(
        app(VirusScanner::class),
        app(App\Modules\Files\Scanning\ScanPolicy::class),
        app(App\Modules\Files\Scanning\ScanningConfig::class),
    );
}

/*
|--------------------------------------------------------------------------
| A verdict, and what this installation does with it
|--------------------------------------------------------------------------
*/

test('a clean file becomes available', function () {
    fakeScanner(ScanVerdict::clean('FakeAV 1.0'));
    $file = scannableFile();

    runScan($file);

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::Clean)
        ->and($file->scan_status->isAvailable())->toBeTrue()
        ->and($file->scanned_at)->not->toBeNull()
        ->and($file->scan_engine)->toBe('FakeAV 1.0');
});

test('an infected file is quarantined and logged', function () {
    fakeScanner(ScanVerdict::infected('Eicar-Test-Signature'));
    $file = scannableFile();

    runScan($file);

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::Infected)
        ->and($file->scan_status->isAvailable())->toBeFalse()
        ->and($file->scan_note)->toBe('Eicar-Test-Signature');

    $entry = ActivityLog::query()->where('action', Action::FileQuarantined)->sole();
    expect($entry->context['threat'])->toBe('Eicar-Test-Signature')
        ->and($entry->context['was_available'])->toBeFalse();
});

test('a quarantined file loses the thumbnails already rendered from it', function () {
    fakeScanner(ScanVerdict::infected('Some.Threat'));
    $file = scannableFile(['mime_type' => 'image/png']);

    $paths = App\Modules\Files\Thumbnails\ThumbnailGenerator::pathsFor($file->id, 'image/png');
    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        Storage::disk('files')->put($path, 'rendered');
    }

    runScan($file);

    foreach ($paths as $path) {
        expect(Storage::disk('files')->exists($path))->toBeFalse();
    }
});

test('a file the scanner cannot open is allowed through, marked, and logged', function () {
    fakeScanner(ScanVerdict::encrypted());
    $file = scannableFile();

    runScan($file);

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::NotScanned)
        ->and($file->scan_note)->toBe(NotScannedReason::Encrypted->value)
        ->and($file->scan_status->isAvailable())->toBeTrue();

    expect(ActivityLog::query()->where('action', Action::FileNotScanned)->count())->toBe(1);
});

test('the same file is blocked when this installation says to block', function () {
    app(Settings::class)->set(Setting::VirusUnscannablePolicy, 'block');
    fakeScanner(ScanVerdict::encrypted());
    $file = scannableFile();

    runScan($file);

    expect($file->refresh()->scan_status)->toBe(ScanStatus::UnscannableBlocked)
        ->and($file->scan_status->isAvailable())->toBeFalse();
});

test('a file larger than the maximum never reaches the scanner at all', function () {
    app(Settings::class)->set(Setting::VirusScanMaxSizeMb, 1);

    // The real client, deliberately: refusing an oversized file before a
    // socket is opened is its job, and a fake that answered anyway would
    // hide the day that check moves or disappears. There is no scanner at
    // the configured address, so anything but an early refusal here would
    // come back as "unavailable" instead.
    $stream = fopen('php://memory', 'r+');
    $verdict = app(App\Modules\Files\Scanning\ClamAvScanner::class)->scan($stream, 2 * 1024 * 1024);
    fclose($stream);

    expect($verdict->outcome)->toBe(App\Modules\Files\Scanning\ScanOutcome::TooLarge);
});

/*
|--------------------------------------------------------------------------
| A scanner that is not answering
|--------------------------------------------------------------------------
*/

test('a file waits while the scanner is down, then goes through', function () {
    fakeScanner(ScanVerdict::unavailable('connection refused'));
    $file = scannableFile();

    runScan($file);
    expect($file->refresh()->scan_status)->toBe(ScanStatus::Pending);

    // Past the ten minutes this installation is willing to wait.
    $this->travel(11)->minutes();

    runScan($file);

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::NotScanned)
        ->and($file->scan_note)->toBe(NotScannedReason::ScannerUnavailable->value);
});

test('an installation set to hold keeps waiting however long it takes', function () {
    app(Settings::class)->set(Setting::VirusScannerDownPolicy, 'hold');
    fakeScanner(ScanVerdict::unavailable('connection refused'));
    $file = scannableFile();

    runScan($file);
    $this->travel(3)->days();
    runScan($file);

    expect($file->refresh()->scan_status)->toBe(ScanStatus::Pending);
});

test('an upload identical to a quarantined file is quarantined without a scan', function () {
    $scanner = fakeScanner(ScanVerdict::clean());

    $known = scannableFile(['scan_status' => ScanStatus::Infected, 'scan_note' => 'Known.Threat']);
    $copy = scannableFile(['checksum' => $known->checksum]);

    runScan($copy);

    expect($copy->refresh()->scan_status)->toBe(ScanStatus::Infected)
        ->and($copy->scan_note)->toBe('Known.Threat')
        ->and($scanner->scans)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Uploads
|--------------------------------------------------------------------------
*/

test('a new upload is pending while scanning is on', function () {
    fakeScanner();

    $file = app(App\Modules\Files\Uploads\StoreUploadedFile::class)->create(
        uploader: $this->admin,
        originalName: 'report.pdf',
        path: 'uploads/report.pdf',
        mimeType: 'application/pdf',
        size: 10,
        checksum: str_repeat('b', 64),
    );

    expect($file->scan_status)->toBe(ScanStatus::Pending);
});

test('a new upload is marked never scanned while scanning is off', function () {
    app(Settings::class)->set(Setting::VirusScanningEnabled, false);
    app(Settings::class)->set(Setting::VirusScannerAddress, '');

    $file = app(App\Modules\Files\Uploads\StoreUploadedFile::class)->create(
        uploader: $this->admin,
        originalName: 'report.pdf',
        path: 'uploads/report.pdf',
        mimeType: 'application/pdf',
        size: 10,
        checksum: str_repeat('c', 64),
    );

    expect($file->scan_status)->toBe(ScanStatus::NotScanned)
        ->and($file->scan_note)->toBe(NotScannedReason::BeforeScanning->value);
});

/*
|--------------------------------------------------------------------------
| Nothing unchecked leaves the server
|--------------------------------------------------------------------------
*/

test('no route serves the bytes of a file that is still being checked', function () {
    $file = scannableFile(['uploaded_by' => $this->admin->id, 'mime_type' => 'image/png']);

    foreach ([
        "/files/{$file->id}/download",
        "/files/{$file->id}/thumbnail",
        "/files/{$file->id}/preview",
    ] as $path) {
        $this->actingAs($this->admin)->get($path)->assertStatus(423, "{$path} served a pending file");
    }
});

test('a quarantined file is refused for the same routes', function () {
    $file = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Infected, 'scan_note' => 'X']);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertStatus(423);
});

test('a clean file downloads normally', function () {
    $file = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Clean]);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertOk();
});

test('a share link says the file is still being checked', function () {
    $file = scannableFile();
    $link = App\Modules\Files\Models\ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
        'created_by' => $this->admin->id,
    ]);

    $this->get("/s/{$link->token}")->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page->component('share/show')->where('status', 'checking'),
    );

    $this->get("/s/{$link->token}/download")->assertRedirect(route('share.show', $link->token));
});

test('a pending file is not visible to the client it was shared with', function () {
    $client = User::factory()->client()->create();
    $file = scannableFile(['uploaded_by' => $this->admin->id]);
    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);

    expect(File::query()->visibleToClient($client)->count())->toBe(0);

    $file->forceFill(['scan_status' => ScanStatus::Clean])->save();

    expect(File::query()->visibleToClient($client)->count())->toBe(1);
});

test('a client still sees their own upload while it is being checked', function () {
    $client = User::factory()->client()->create();
    $file = scannableFile(['uploaded_by' => $client->id]);

    expect(File::query()->visibleToClient($client)->pluck('id')->all())->toBe([$file->id]);
});

test('a pending file is left out of a zip', function () {
    $pending = scannableFile(['uploaded_by' => $this->admin->id]);
    $clean = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Clean]);

    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => [$pending->id, $clean->id], 'folder_ids' => []])
        ->assertOk();

    $zip = App\Modules\Files\Models\ZipDownload::query()->latest('id')->sole();
    expect($zip->file_ids)->toBe([$clean->id]);
});

/*
|--------------------------------------------------------------------------
| Nobody is told about a file they cannot have yet
|--------------------------------------------------------------------------
*/

test('sharing a file still being checked tells nobody, and tells them when it clears', function () {
    fakeScanner(ScanVerdict::clean());
    $client = User::factory()->client()->create();
    $file = scannableFile(['uploaded_by' => $this->admin->id]);

    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);

    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->count())->toBe(0);

    runScan($file);

    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->where('type', 'file_shared')->count())->toBe(1);
});

test('a file found infected is never announced', function () {
    fakeScanner(ScanVerdict::infected('Some.Threat'));
    $client = User::factory()->client()->create();
    $file = scannableFile(['uploaded_by' => $this->admin->id]);

    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);
    runScan($file);

    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->count())->toBe(0);
});

test('a share taken back while the file was being checked produces no email afterwards', function () {
    fakeScanner(ScanVerdict::clean());
    $client = User::factory()->client()->create();
    $file = scannableFile(['uploaded_by' => $this->admin->id]);

    $sharing = app(App\Modules\Files\Sharing\FileSharing::class);
    $sharing->assign($file, $client, $client->name);
    $sharing->unassign($file, $client, $client->name);

    runScan($file);

    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->count())->toBe(0);
});

test('re-scanning a file that was let through keeps it available, and does not announce it twice', function () {
    // The hourly command asks again about files that went out unchecked
    // while the scanner was down. Their recipients already have them, so
    // they must not lose access while the answer comes back, and must not
    // be told a second time when it does.
    $client = User::factory()->client()->create();
    $file = scannableFile([
        'uploaded_by' => $this->admin->id,
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::ScannerUnavailable->value,
    ]);

    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);
    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->count())->toBe(1);

    fakeScanner(ScanVerdict::clean());
    $this->artisan('projectsend:scan-files')->assertSuccessful();

    // Still theirs throughout, and now actually checked.
    expect(File::query()->visibleToClient($client)->count())->toBe(1)
        ->and($file->refresh()->scan_status)->toBe(ScanStatus::Clean)
        ->and(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->count())->toBe(1);
});

test('a backfill never hides the library it is working through', function () {
    $client = User::factory()->client()->create();
    $file = scannableFile([
        'uploaded_by' => $this->admin->id,
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::BeforeScanning->value,
    ]);
    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);

    // Queued rather than run, which is the state a real backfill spends
    // almost all of its time in: dispatched, not yet scanned.
    Illuminate\Support\Facades\Queue::fake();
    fakeScanner(ScanVerdict::clean());

    $this->artisan('projectsend:scan-files', ['--existing' => true])->assertSuccessful();

    expect($file->refresh()->scan_status)->toBe(ScanStatus::NotScanned)
        ->and(File::query()->visibleToClient($client)->count())->toBe(1);
});

test('a released file is announced then, not before', function () {
    $client = User::factory()->client()->create();
    $file = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Infected, 'scan_note' => 'X']);

    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);
    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->count())->toBe(0);

    confirmPassword($this->admin);
    $this->actingAs($this->admin)->post("/files/{$file->id}/release", ['reason' => 'False positive']);

    expect(App\Modules\Notifications\InAppNotification::query()->where('user_id', $client->id)->where('type', 'file_shared')->count())->toBe(1);
});

test('the activity log names the file it quarantined', function () {
    // The scan job has no actor and attaches no subject, so a template
    // written with :subject renders 'The file "" was quarantined'. Caught
    // on a real dashboard, not by a test, which is why there is one now.
    fakeScanner(ScanVerdict::infected('Eicar-Test-Signature'));
    $file = scannableFile(['name' => 'Contrato firmado']);

    runScan($file);

    $entry = ActivityLog::query()->where('action', Action::FileQuarantined)->sole();

    $presented = app(App\Modules\Audit\ActivityPresenter::class)->present($entry);
    $line = strtr($presented['template'], collect($presented['replacements'])
        ->mapWithKeys(fn (string $value, string $key): array => [":{$key}" => $value])
        ->all());

    expect($line)->toContain('Contrato firmado')
        ->toContain('Eicar-Test-Signature');
});

test('a library from before the scanner is what "scan existing files" actually finds', function () {
    // The migration gives the column its default and writes no reason, so
    // every file on every upgraded installation has scan_note = null. A
    // backfill that looked for the reason found none of them — the whole
    // feature was inert on exactly the libraries it exists for.
    $old = File::factory()->create(['scan_status' => ScanStatus::NotScanned, 'scan_note' => null]);
    $stated = File::factory()->create([
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::BeforeScanning->value,
    ]);
    $letThrough = File::factory()->create([
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::ScannerUnavailable->value,
    ]);

    $found = File::query()->neverScanned()->pluck('id')->all();

    expect($found)->toContain($old->id)
        ->toContain($stated->id)
        // Not this one: it was offered to a scanner that could not answer,
        // and the hourly sweep already re-scans those.
        ->not->toContain($letThrough->id);
});

test('the backfill queues those files', function () {
    fakeScanner(ScanVerdict::clean());
    $old = scannableFile(['scan_status' => ScanStatus::NotScanned, 'scan_note' => null]);

    Illuminate\Support\Facades\Queue::fake();

    $this->artisan('projectsend:scan-files', ['--existing' => true])->assertSuccessful();

    Illuminate\Support\Facades\Queue::assertPushed(ScanFileJob::class, fn (ScanFileJob $job): bool => $job->fileId === $old->id);
});

test('a file whose bytes are gone says so, and is not retried forever', function () {
    // An orphaned row, or storage that moved. It used to be recorded as
    // "the scanner could not be reached" — wrong on screen, and wrong in
    // behaviour: that is the one reason the hourly sweep re-queues, so
    // every missing file would have been rescanned every hour for good.
    $scanner = fakeScanner(ScanVerdict::clean());
    $file = scannableFile();
    Storage::disk('files')->delete($file->path);

    runScan($file);

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::Missing)
        // Withheld: there is nothing to serve, and a client shown a file
        // whose download fails is worse off than one who never saw it.
        ->and($file->scan_status->isAvailable())->toBeFalse()
        // Never offered to the scanner: there was nothing to offer.
        ->and($scanner->scans)->toBe(0);

    Illuminate\Support\Facades\Queue::fake();
    $this->artisan('projectsend:scan-files')->assertSuccessful();
    Illuminate\Support\Facades\Queue::assertNothingPushed();
});

test('a missing file is missing whatever the unscannable policy says', function () {
    // "Allow files nobody could scan" is a decision about risk, and there
    // is no risk in a file that cannot be served — only a problem
    // somebody has to look at.
    app(App\Modules\Platform\Settings\Settings::class)->set(Setting::VirusUnscannablePolicy, 'allow');
    fakeScanner(ScanVerdict::clean());
    $file = scannableFile();
    Storage::disk('files')->delete($file->path);

    runScan($file);

    expect($file->refresh()->scan_status)->toBe(ScanStatus::Missing);
});

test('a new scan checks files that already have a verdict', function () {
    // "New scan" after an engine update means "check my library again".
    // The first version only queued files nothing had ever looked at, so
    // on a library already scanned once the button did nothing — and was
    // disabled for saying so.
    fakeScanner(ScanVerdict::clean());
    $clean = scannableFile(['scan_status' => ScanStatus::Clean]);
    $letThrough = scannableFile([
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::TooLarge->value,
    ]);
    $waiting = scannableFile(['scan_status' => ScanStatus::Pending]);
    $gone = scannableFile(['scan_status' => ScanStatus::Missing]);

    Illuminate\Support\Facades\Queue::fake();

    $this->artisan('projectsend:scan-files', ['--all' => true])->assertSuccessful();

    foreach ([$clean, $letThrough] as $file) {
        Illuminate\Support\Facades\Queue::assertPushed(ScanFileJob::class, fn (ScanFileJob $job): bool => $job->fileId === $file->id);
    }

    // Nothing to re-ask about a file with no bytes.
    Illuminate\Support\Facades\Queue::assertNotPushed(ScanFileJob::class, fn (ScanFileJob $job): bool => $job->fileId === $gone->id);

    // The one already waiting is picked up by the ordinary sweep at the
    // top of the command, as a first scan rather than as a rescan — the
    // distinction the job itself turns on.
    Illuminate\Support\Facades\Queue::assertPushed(
        ScanFileJob::class,
        fn (ScanFileJob $job): bool => $job->fileId === $waiting->id && $job->rescan === false,
    );
});

test('a rescan of a clean file re-checks it, and finds what is there now', function () {
    // What an engine update is for: the same bytes, a newer opinion.
    fakeScanner(ScanVerdict::infected('Newly.Known.Threat'));
    $file = scannableFile(['scan_status' => ScanStatus::Clean]);

    (new ScanFileJob($file->id, true))->handle(
        app(VirusScanner::class),
        app(App\Modules\Files\Scanning\ScanPolicy::class),
        app(App\Modules\Files\Scanning\ScanningConfig::class),
    );

    expect($file->refresh()->scan_status)->toBe(ScanStatus::Infected)
        ->and($file->scan_note)->toBe('Newly.Known.Threat');
});

test('a rescan leaves a file that is waiting for its first verdict alone', function () {
    $scanner = fakeScanner(ScanVerdict::clean());
    $file = scannableFile(['scan_status' => ScanStatus::Pending]);

    (new ScanFileJob($file->id, true))->handle(
        app(VirusScanner::class),
        app(App\Modules\Files\Scanning\ScanPolicy::class),
        app(App\Modules\Files\Scanning\ScanningConfig::class),
    );

    expect($scanner->scans)->toBe(0)
        ->and($file->refresh()->scan_status)->toBe(ScanStatus::Pending);
});

test('the library does not list a file nobody can use', function () {
    // Every button on such a row leads somewhere that refuses, and the
    // download leads to an error page. They live on the two screens that
    // exist to act on them.
    $clean = scannableFile(['uploaded_by' => $this->admin->id, 'name' => 'Usable', 'scan_status' => ScanStatus::Clean]);
    $quarantined = scannableFile(['uploaded_by' => $this->admin->id, 'name' => 'Infectado', 'scan_status' => ScanStatus::Infected, 'scan_note' => 'X']);
    $gone = scannableFile(['uploaded_by' => $this->admin->id, 'name' => 'Sin bytes', 'scan_status' => ScanStatus::Missing]);
    $waiting = scannableFile(['uploaded_by' => $this->admin->id, 'name' => 'Esperando', 'scan_status' => ScanStatus::Pending]);

    $names = collect($this->actingAs($this->admin)->get('/files')->viewData('page')['props']['files'])->pluck('name');

    expect($names)->toContain('Usable')
        // Still listed: it is about to be usable, and its uploader should
        // see where it went.
        ->toContain('Esperando')
        ->not->toContain('Infectado')
        ->not->toContain('Sin bytes');

    expect([$clean->id, $quarantined->id, $gone->id, $waiting->id])->toHaveCount(4);
});

test('the file editor says why a quarantined file refuses everything', function () {
    $file = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Infected, 'scan_note' => 'Eicar-Test-Signature']);

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('file.scan_status', 'infected')
            ->where('file.scan_note', 'Eicar-Test-Signature'),
    );
});

test('the editor offers no download for a file it cannot produce', function () {
    $quarantined = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Infected, 'scan_note' => 'X']);
    $clean = scannableFile(['uploaded_by' => $this->admin->id, 'scan_status' => ScanStatus::Clean]);

    $this->actingAs($this->admin)->get("/files/{$quarantined->id}")->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page->where('file.scan_available', false),
    );

    $this->actingAs($this->admin)->get("/files/{$clean->id}")->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page->where('file.scan_available', true),
    );
});
