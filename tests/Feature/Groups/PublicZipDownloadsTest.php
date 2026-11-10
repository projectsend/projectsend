<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Issue #1840: visitors on a public page tick files and download them as
 * one zip. Off by default. These tests are mostly about what a visitor
 * must NOT be able to do with it, because anybody at all can call it.
 *
 * QUEUE_CONNECTION=sync, so the archive is built by the time store returns.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->staff = User::factory()->create();

    $settings = app(Settings::class);
    $settings->set(Setting::PublicListingEnabled, true);
    $settings->set(Setting::PublicListingSlug, 'public');
    $settings->set(Setting::PublicZipDownloadsEnabled, true);
    $settings->set(Setting::MaxZipDownloadSizeMb, 2048);

    $this->actingAs($this->staff);
    $this->folder = app(FolderService::class)->create('Drawings', null);
    $this->folder->update(['public' => true]);
    $this->private = app(FolderService::class)->create('Internal', null);

    $this->a = publicZipUpload($this->staff, 'a.pdf', $this->folder->id);
    $this->b = publicZipUpload($this->staff, 'b.pdf', $this->folder->id);
    $this->c = publicZipUpload($this->staff, 'c.pdf', $this->folder->id);
    $this->secret = publicZipUpload($this->staff, 'secret.pdf', $this->private->id);

    auth()->logout();
});

function publicZipUpload(User $as, string $name, ?int $folderId): File
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
 * @param  list<string>  $slugs
 */
function askForPublicZip(string $scope, ?string $slug, array $slugs): Illuminate\Testing\TestResponse
{
    return test()->postJson('/public/zip-downloads', ['scope' => $scope, 'slug' => $slug, 'files' => $slugs]);
}

/**
 * @return list<string>
 */
function publicZipEntries(ZipDownload $zipDownload): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('files')->path($zipDownload->refresh()->path));
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();
    sort($names);

    return $names;
}

test('it is off unless an administrator turns it on', function () {
    app(Settings::class)->set(Setting::PublicZipDownloadsEnabled, false);

    askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertNotFound();

    expect(ZipDownload::query()->count())->toBe(0);
});

test('the setting is off on a fresh installation', function () {
    expect(Setting::PublicZipDownloadsEnabled->default())->toBeFalse();
});

test('a visitor zips the files they ticked on a public folder, and only those', function () {
    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug, $this->c->slug])->assertOk();

    $zip = ZipDownload::query()->findOrFail($response->json('id'));
    expect($zip->status)->toBe(ZipDownload::STATUS_READY)
        ->and($zip->requested_by)->toBeNull()
        ->and($zip->public_scope_type)->toBe('folder')
        ->and(publicZipEntries($zip))->toBe(['a.pdf', 'c.pdf']);

    $this->getJson($response->json('status_url'))->assertOk()->assertJson(['status' => 'ready']);
    $this->get($response->json('download_url'))->assertOk();

    // Logged as public downloads, one per file, as each would be alone.
    expect(ActivityLog::query()->where('action', Action::PublicFileDownloaded->value)->pluck('subject_id')->sort()->values()->all())
        ->toBe(collect([$this->a->id, $this->c->id])->sort()->values()->all());
});

test('a file the page does not offer never gets into the zip', function () {
    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug, $this->secret->slug])->assertOk();

    expect(publicZipEntries(ZipDownload::query()->findOrFail($response->json('id'))))->toBe(['a.pdf']);
});

test('asking only for files the page does not offer is refused, and builds nothing', function () {
    askForPublicZip('folder', $this->folder->slug, [$this->secret->slug])->assertStatus(422);
    askForPublicZip('folder', $this->folder->slug, ['no-such-file'])->assertStatus(422);

    expect(ZipDownload::query()->count())->toBe(0);
});

test('a private folder, an unknown page, or the wrong base address is not found', function () {
    askForPublicZip('folder', $this->private->slug, [$this->secret->slug])->assertNotFound();
    askForPublicZip('group', 'nope', [$this->a->slug])->assertNotFound();
    $this->postJson('/elsewhere/zip-downloads', ['scope' => 'folder', 'slug' => $this->folder->slug, 'files' => [$this->a->slug]])->assertNotFound();
});

test('another visitor cannot see or download somebody else\'s zip', function () {
    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertOk();

    $this->flushSession();

    $this->getJson($response->json('status_url'))->assertNotFound();
    $this->get($response->json('download_url'))->assertNotFound();
});

test('a signed-in user\'s zip cannot be reached through the public routes', function () {
    $staffZip = ZipDownload::query()->create([
        'requested_by' => $this->staff->id,
        'status' => ZipDownload::STATUS_READY,
        'file_ids' => [$this->secret->id],
        'folder_ids' => [],
        'file_count' => 1,
    ]);

    // Even with its id planted in the session.
    $this->withSession(['public_zip_downloads' => [$staffZip->id]]);

    $this->getJson("/public/zip-downloads/{$staffZip->id}")->assertNotFound();
    $this->get("/public/zip-downloads/{$staffZip->id}/download")->assertNotFound();
});

test('a visitor\'s zip cannot be reached through the signed-in routes either', function () {
    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertOk();
    $id = $response->json('id');

    $this->actingAs($this->staff)->getJson("/zip-downloads/{$id}")->assertNotFound();
    $this->actingAs($this->staff)->get("/zip-downloads/{$id}/download")->assertNotFound();
});

test('a folder made private after the zip was built stops the download', function () {
    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertOk();

    Folder::query()->whereKey($this->folder->id)->update(['public' => false]);

    $this->get($response->json('download_url'))->assertForbidden();
    expect(ActivityLog::query()->where('action', Action::PublicFileDownloaded->value)->count())->toBe(0);
});

test('a file moved out of the folder after the build stops the download', function () {
    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug, $this->b->slug])->assertOk();

    File::query()->whereKey($this->b->id)->update(['folder_id' => $this->private->id]);

    $this->get($response->json('download_url'))->assertForbidden();
});

test('a build whose folder went private while it waited fails without writing an archive', function () {
    $zip = ZipDownload::query()->create([
        'requested_by' => null,
        'public_scope_type' => 'folder',
        'public_scope_id' => $this->folder->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$this->a->id],
        'folder_ids' => [],
        'file_count' => 1,
    ]);
    Folder::query()->whereKey($this->folder->id)->update(['public' => false]);

    (new BuildZipDownloadJob($zip->id))->handle();

    expect($zip->refresh()->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($zip->path)->toBeNull();
});

test('a build never adds a file outside the page, even if the row names one', function () {
    // A row the controller would never write — proof the job re-reads the
    // page itself rather than trusting the ids it was given.
    $zip = ZipDownload::query()->create([
        'requested_by' => null,
        'public_scope_type' => 'folder',
        'public_scope_id' => $this->folder->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$this->a->id, $this->secret->id],
        'folder_ids' => [],
        'file_count' => 2,
    ]);

    (new BuildZipDownloadJob($zip->id))->handle();

    expect(publicZipEntries($zip))->toBe(['a.pdf'])
        ->and($zip->refresh()->contained_file_ids)->toBe([$this->a->id]);
});

test('one zip at a time per visitor', function () {
    $pending = ZipDownload::query()->create([
        'requested_by' => null,
        'public_scope_type' => 'folder',
        'public_scope_id' => $this->folder->id,
        'status' => ZipDownload::STATUS_PENDING,
        'file_ids' => [$this->a->id],
        'folder_ids' => [],
        'file_count' => 1,
    ]);
    $this->withSession(['public_zip_downloads' => [$pending->id]]);

    askForPublicZip('folder', $this->folder->slug, [$this->b->slug])->assertStatus(429);
});

test('only a few visitor builds may wait at once across the installation', function () {
    for ($i = 0; $i < 3; $i++) {
        ZipDownload::query()->create([
            'requested_by' => null,
            'public_scope_type' => 'folder',
            'public_scope_id' => $this->folder->id,
            'status' => ZipDownload::STATUS_PENDING,
            'file_ids' => [$this->a->id],
            'folder_ids' => [],
            'file_count' => 1,
        ]);
    }

    askForPublicZip('folder', $this->folder->slug, [$this->b->slug])->assertStatus(429);
});

test('visitors\' archives share a disk budget, so many addresses cannot fill the disk', function () {
    // 1 MB per archive gives visitors 10 MB between them.
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 1);

    ZipDownload::query()->create([
        'requested_by' => null,
        'public_scope_type' => 'folder',
        'public_scope_id' => $this->folder->id,
        'status' => ZipDownload::STATUS_READY,
        'file_ids' => [$this->a->id],
        'folder_ids' => [],
        'file_count' => 1,
        'total_size' => 10 * 1024 * 1024,
    ]);

    askForPublicZip('folder', $this->folder->slug, [$this->b->slug])->assertStatus(429);

    // Signed-in users' archives are not counted against it.
    ZipDownload::query()->update(['requested_by' => $this->staff->id, 'public_scope_type' => null]);
    askForPublicZip('folder', $this->folder->slug, [$this->b->slug])->assertOk();
});

test('a download limit is spent when the zip is delivered', function () {
    File::query()->whereKey($this->a->id)->update(['download_limit' => 1]);

    $response = askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertOk();
    $this->get($response->json('download_url'))->assertOk();

    // The one allowed download went into the zip.
    $this->get("/public/files/{$this->a->slug}/download")->assertForbidden();
    askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertStatus(422);
});

test('a public group page offers its own files', function () {
    $group = Group::query()->create(['name' => 'Partners', 'public' => true]);
    File::query()->whereKey($this->secret->id)->update(['public' => true]);
    FileAssignment::query()->create([
        'file_id' => $this->secret->id,
        'assignable_type' => (new Group)->getMorphClass(),
        'assignable_id' => $group->id,
    ]);

    $response = askForPublicZip('group', $group->slug, [$this->secret->slug, $this->a->slug])->assertOk();

    expect(publicZipEntries(ZipDownload::query()->findOrFail($response->json('id'))))->toBe(['secret.pdf']);
});

test('the public index needs the directory switched on', function () {
    File::query()->whereKey($this->secret->id)->update(['public' => true]);
    askForPublicZip('index', null, [$this->secret->slug])->assertOk();

    $this->flushSession();
    app(Settings::class)->set(Setting::PublicListingEnabled, false);
    askForPublicZip('index', null, [$this->secret->slug])->assertNotFound();
});

test('the pages offer zips only when the switch is on', function () {
    $this->get("/public/folders/{$this->folder->slug}")->assertInertia(
        fn ($page) => $page->where('zip.scope', 'folder')->where('zip.slug', $this->folder->slug)->where('files.0.slug', $this->a->slug),
    );

    app(Settings::class)->set(Setting::PublicZipDownloadsEnabled, false);

    $this->get("/public/folders/{$this->folder->slug}")->assertInertia(fn ($page) => $page->where('zip', null));
});

test('without the zip capability the routes are gone and the pages offer nothing', function () {
    // CapabilityRegistry is bound, not a singleton, so the next resolve
    // reads this. Same switch ZipDownloadCapabilityTest uses.
    config(['projectsend.capabilities_disabled' => 'downloads.zip']);

    askForPublicZip('folder', $this->folder->slug, [$this->a->slug])->assertNotFound();
    $this->get("/public/folders/{$this->folder->slug}")->assertInertia(fn ($page) => $page->where('zip', null));
});
