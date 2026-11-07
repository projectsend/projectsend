<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\OrphanFileScanner;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Files\Storage\UploadLocations;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('a fresh install has one location, the server disk, and it is the default', function () {
    $locations = StorageLocation::query()->get();

    expect($locations)->toHaveCount(1)
        ->and($locations[0]->disk)->toBe('files')
        ->and($locations[0]->is_default)->toBeTrue()
        ->and($locations[0]->isLocal())->toBeTrue();
});

test('the secret is encrypted at rest', function () {
    $location = bucketLocation(['secret' => 'super-secret-access-key']);

    $raw = DB::table('storage_locations')->where('id', $location->id)->value('secret');

    expect($raw)->not->toBe('super-secret-access-key')
        ->and($location->fresh()->secret)->toBe('super-secret-access-key');
});

test('the secret never reaches the cache store', function () {
    // The column above is only half the promise: this class caches its
    // resolved settings forever on every process boot, and a cache store
    // encrypts nothing. See the same pair in MailProviderSettingsTest.
    bucketLocation(['secret' => 'super-secret-access-key']);

    Cache::flush();
    app(ExternalStorageConfigApplier::class)->apply();

    $cached = Cache::get('platform.storage_locations.v5');

    expect($cached)->toBeArray()->not->toBeEmpty()
        ->and(json_encode($cached))->not->toContain('super-secret-access-key');
});

test('the secret never reaches the database cache store either', function () {
    // Against the store config/cache.php actually defaults to, read as the
    // raw row an operator would find in a dump.
    config(['cache.default' => 'database']);

    bucketLocation(['secret' => 'super-secret-access-key']);

    Cache::store('database')->flush();
    app(ExternalStorageConfigApplier::class)->apply();

    $rows = DB::table('cache')->pluck('value')->implode('|');

    expect($rows)->not->toContain('super-secret-access-key');
});

test('apply() still configures the secret it no longer caches', function () {
    // The other half: keeping the credential out of the cache must not
    // stop the disk from being configured with it.
    $location = bucketLocation(['secret' => 'super-secret-access-key']);

    applyStorage();

    expect(config("filesystems.disks.{$location->disk}.secret"))->toBe('super-secret-access-key');
});

test('apply() is a no-op when not fully configured', function () {
    $location = bucketLocation();
    DB::table('storage_locations')->where('id', $location->id)->update(['secret' => null]);

    applyStorage();

    expect(config("filesystems.disks.{$location->disk}"))->toBeNull();
});

test('apply() registers a disk for every configured bucket', function () {
    $minio = bucketLocation([
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
        'endpoint' => 'https://minio.example.test',
        'use_path_style' => true,
        'root' => 'projectsend',
    ]);
    // Registered even while it takes no uploads, so its files keep downloading.
    $aws = bucketLocation(['bucket' => 'other-bucket', 'region' => 'eu-central-1', 'accepts_uploads' => false]);

    applyStorage();

    expect(config("filesystems.disks.{$minio->disk}.key"))->toBe('AKIAEXAMPLE')
        ->and(config("filesystems.disks.{$minio->disk}.secret"))->toBe('shh')
        ->and(config("filesystems.disks.{$minio->disk}.bucket"))->toBe('my-bucket')
        ->and(config("filesystems.disks.{$minio->disk}.region"))->toBe('us-east-1')
        ->and(config("filesystems.disks.{$minio->disk}.endpoint"))->toBe('https://minio.example.test')
        ->and(config("filesystems.disks.{$minio->disk}.use_path_style_endpoint"))->toBeTrue()
        ->and(config("filesystems.disks.{$minio->disk}.root"))->toBe('projectsend')
        ->and(config("filesystems.disks.{$minio->disk}.throw"))->toBeFalse()
        ->and(config("filesystems.disks.{$aws->disk}.bucket"))->toBe('other-bucket');
});

test('a folder inside the bucket does not break the disk it belongs to', function () {
    // The disk carried both names for the folder — `root`, which Laravel's
    // own drivers read, and `prefix`, which this application's GCS driver
    // reads. Laravel wraps any disk with a non-empty `prefix` in
    // League\Flysystem\PathPrefixing\PathPrefixedAdapter, which lives in
    // an optional package nobody installs, so resolving an S3 disk with a
    // folder set died with "class not found" — and took every page that
    // touches storage with it (#1788, reported by @veenone). GCS still
    // gets it: see the folder test in GoogleCloudStorageTest.
    $location = bucketLocation(['root' => 'Documentation']);

    applyStorage();

    expect(config("filesystems.disks.{$location->disk}.root"))->toBe('Documentation')
        ->and(config("filesystems.disks.{$location->disk}.prefix"))->toBeNull();

    // Resolving the disk is where it died. No network: building an S3
    // client talks to nobody.
    expect(Storage::disk($location->disk)->path('report.pdf'))->toBeString();
});

test('new uploads stay on the local disk while it is the default, and go to a bucket once that is', function () {
    $location = bucketLocation();

    expect(resolvedDiskFor($this->admin))->toBe('files');

    makeDefault($location);

    // A client with no locations of their own follows the default too.
    expect(resolvedDiskFor($this->admin))->toBe($location->disk)
        ->and(resolvedDiskFor(User::factory()->client()->create()))->toBe($location->disk);
});

test('a real upload lands on the external disk once the backend is active, and local uploads made before activation are unaffected', function () {
    Storage::fake('files');

    uploadImageFile($this->admin);

    $localFile = File::query()->latest('id')->first();
    expect($localFile->disk)->toBe('files');
    Storage::disk('files')->assertExists($localFile->path);

    $location = bucketLocation();
    makeDefault($location);
    Storage::fake($location->disk);

    uploadImageFile($this->admin);

    $externalFile = File::query()->latest('id')->first();
    expect($externalFile->id)->not->toBe($localFile->id)
        ->and($externalFile->disk)->toBe($location->disk);
    Storage::disk($location->disk)->assertExists($externalFile->path);

    // The first file, uploaded before activation, still resolves to local
    // disk and is unaffected by the backend switch (no migration tool).
    Storage::disk('files')->assertExists($localFile->refresh()->path);
    expect($localFile->disk)->toBe('files');
});

test('apply() supplies no credentials at all when authenticating as the server role', function () {
    // Nothing to leave blank and nothing to read: Laravel only builds a
    // `credentials` entry for the S3 client when both halves are
    // non-empty, and the AWS SDK falls back to its default credential
    // provider chain when none is given. An empty string here would be a
    // credential, and would fail instead of falling through.
    $location = bucketLocation(['use_instance_role' => true, 'key' => null, 'secret' => null]);

    applyStorage();

    expect(config("filesystems.disks.{$location->disk}.key"))->toBeNull()
        ->and(config("filesystems.disks.{$location->disk}.secret"))->toBeNull()
        ->and(config("filesystems.disks.{$location->disk}.bucket"))->toBe('my-bucket')
        ->and(config("filesystems.disks.{$location->disk}.region"))->toBe('us-east-1');
});

test('a leftover stored secret is never applied once the server role is authenticating', function () {
    // The row is written straight here, bypassing the controller that
    // clears the credential — the runtime must not fall back to a secret
    // it was told to stop using.
    $location = bucketLocation(['use_instance_role' => true, 'key' => 'AKIALEFTOVER', 'secret' => 'leftover-secret']);

    applyStorage();

    expect(config("filesystems.disks.{$location->disk}.key"))->toBeNull()
        ->and(config("filesystems.disks.{$location->disk}.secret"))->toBeNull();
});

test('new uploads go to the external disk with no stored credentials when the server role is authenticating', function () {
    // The silent failure this guards: isConfigured() demanding a key and
    // a secret would leave the disk "unconfigured" forever, and every
    // upload would keep landing on the local disk without saying so.
    $location = bucketLocation(['use_instance_role' => true, 'key' => null, 'secret' => null]);
    makeDefault($location);

    expect(resolvedDiskFor($this->admin))->toBe($location->disk);
});

test('booting against a row older than the provider column does not throw', function () {
    // #1770. The exact shape of a 2.0/2.1 install with S3 configured,
    // upgrading to 2.2 or later: the code is new, the schema is still old,
    // and boot happens before `artisan migrate` gets a chance to run.
    //
    // The failure this pins was not a wrong answer but a fatal one — an
    // UnhandledMatchError on a null provider, thrown during
    // PlatformServiceProvider::boot(), which took down every artisan
    // command including the one that would have added the column. The
    // official image reported it as "database unreachable" and
    // restart-looped.
    legacyStorageRow();

    applyStorage();

    // Read as S3, which is the only thing a row predating the column could
    // have been — and is what the migration's own default says.
    expect(config('filesystems.disks.files_external.bucket'))->toBe('my-bucket')
        ->and(config('filesystems.disks.files_external.key'))->toBe('AKIAEXAMPLE')
        ->and(config('filesystems.disks.files_external.secret'))->toBe('shh')
        ->and(config('filesystems.disks.files_external.driver'))->toBe('s3');
});

test('an upload still reaches the external disk while the schema is mid-upgrade', function () {
    // The other half: not throwing is not the same as behaving. An
    // installation that was storing files in a bucket before the upgrade
    // must not quietly start writing them to local disk during it.
    legacyStorageRow();

    expect(resolvedDiskFor($this->admin))->toBe('files_external');
});

test('the migration carries the old row over on its own disk name, as the default only if it was switched on', function (bool $active) {
    resetStorageLocationsMigration(function () use ($active): void {
        ExternalStorageSettings::query()->create([
            'active' => $active,
            'provider' => 's3',
            'key' => 'AKIAEXAMPLE',
            'secret' => 'shh',
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'root' => 'clients',
        ]);
    });

    // Carried over even when it was off: files can be in it from when it
    // last was on.
    $bucket = StorageLocation::query()->where('disk', 'files_external')->firstOrFail();
    $local = StorageLocation::query()->where('disk', 'files')->firstOrFail();

    expect($bucket->bucket)->toBe('my-bucket')
        ->and($bucket->root)->toBe('clients')
        // Copied as stored, and still readable with the same key.
        ->and($bucket->secret)->toBe('shh')
        ->and($bucket->is_default)->toBe($active)
        ->and($local->is_default)->toBe(! $active);
})->with(['switched on' => true, 'switched off' => false]);

// ---- Where a client's uploads go ---------------------------------------

test('a client uploads to their first location, or the one they asked for if it is theirs', function () {
    $client = User::factory()->client()->create();
    $eu = bucketLocation(['name' => 'EU']);
    $us = bucketLocation(['name' => 'US']);
    $other = bucketLocation(['name' => 'Someone else\'s']);
    assignLocations($client, [$us, $eu]);
    applyStorage();

    expect(resolvedDiskFor($client))->toBe($us->disk)
        ->and(resolvedDiskFor($client, $eu->id))->toBe($eu->disk)
        // Not theirs: their first, not the one asked for.
        ->and(resolvedDiskFor($client, $other->id))->toBe($us->disk)
        // Staff always use the default, whatever is assigned to clients.
        ->and(resolvedDiskFor($this->admin, $eu->id))->toBe('files');
});

test('a location that stopped taking uploads is skipped for the next one', function () {
    $client = User::factory()->client()->create();
    $first = bucketLocation(['accepts_uploads' => false]);
    $local = StorageLocation::query()->where('disk', 'files')->firstOrFail();
    assignLocations($client, [$first, $local]);
    applyStorage();

    expect(resolvedDiskFor($client))->toBe('files')
        ->and(app(UploadLocations::class)->usableFor($client)->pluck('id')->all())->toBe([$local->id]);
});

test('a client chooses one of their own locations when the upload starts, and the bytes go there', function () {
    Storage::fake('files');
    $client = uploadingClient();
    $first = bucketLocation(['name' => 'First']);
    $second = bucketLocation(['name' => 'Second']);
    assignLocations($client, [$first, $second]);
    applyStorage();
    Storage::fake($first->disk);
    Storage::fake($second->disk);

    $this->actingAs($client);
    uploadChunkedAsClient(['storage_location_id' => $second->id])->assertOk();

    $file = File::query()->latest('id')->firstOrFail();
    expect($file->disk)->toBe($second->disk);
    Storage::disk($second->disk)->assertExists($file->path);
});

test('a client cannot choose a location that is not theirs', function () {
    $client = uploadingClient();
    $theirs = bucketLocation();
    $notTheirs = bucketLocation();
    assignLocations($client, [$theirs]);
    applyStorage();

    $this->actingAs($client)->postJson('/uploads', [
        'filename' => 'statement.pdf',
        'size' => 11,
        'type' => 'application/pdf',
        'storage_location_id' => $notTheirs->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['storage_location_id']);
});

test('the client upload page lists their usable locations, the preferred first, without the bucket', function () {
    $client = uploadingClient();
    $eu = bucketLocation(['name' => 'Client archive (EU)', 'client_note' => 'Kept in Frankfurt']);
    $local = StorageLocation::query()->where('disk', 'files')->firstOrFail();
    assignLocations($client, [$eu, $local]);
    applyStorage();

    $this->actingAs($client)->get(route('my-files.upload.create'))
        ->assertInertia(fn ($page) => $page
            ->where('storage_locations.0', ['id' => $eu->id, 'name' => 'Client archive (EU)', 'note' => 'Kept in Frankfurt'])
            ->where('storage_locations.1.id', $local->id)
            ->missing('storage_locations.0.bucket'));
});

// ---- The client edit card ------------------------------------------------

test('an administrator sets a client\'s locations in order, replacing what was there', function () {
    $client = User::factory()->client()->create();
    $a = bucketLocation();
    $b = bucketLocation();
    $local = StorageLocation::query()->where('disk', 'files')->firstOrFail();
    assignLocations($client, [$a]);

    $this->actingAs($this->admin)->patch(route('clients.update', $client), clientUpdatePayload($client, [
        'storage_location_ids' => [$b->id, $local->id],
    ]))->assertSessionHasNoErrors();

    expect($client->storageLocations()->pluck('storage_locations.id')->all())->toBe([$b->id, $local->id]);
});

test('a staff member who cannot change settings cannot change a client\'s locations', function () {
    $client = User::factory()->client()->create();
    $a = bucketLocation();
    $editor = staffWithPermissions(['edit_clients']);

    $this->actingAs($editor)->patch(route('clients.update', $client), clientUpdatePayload($client, [
        'storage_location_ids' => [$a->id],
    ]));

    expect($client->storageLocations()->count())->toBe(0);
});

test('saving a client without the list leaves their locations alone', function () {
    $client = User::factory()->client()->create();
    $a = bucketLocation();
    assignLocations($client, [$a]);

    $this->actingAs($this->admin)->patch(route('clients.update', $client), clientUpdatePayload($client))
        ->assertSessionHasNoErrors();

    expect($client->storageLocations()->pluck('storage_locations.id')->all())->toBe([$a->id]);
});

// ---- Settings → Storage --------------------------------------------------

test('staff can add a bucket, which gets a disk of its own', function () {
    $this->actingAs($this->admin)->post('/system/settings/storage/locations', validStorageSettingsPayload([
        'name' => 'Media vault',
        'access_key' => 'AKIANEW',
        'bucket' => 'media',
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $location = StorageLocation::query()->where('name', 'Media vault')->firstOrFail();
    expect($location->key)->toBe('AKIANEW')
        ->and($location->bucket)->toBe('media')
        ->and($location->disk)->toStartWith('files_loc_')
        ->and($location->is_default)->toBeFalse()
        ->and(config("filesystems.disks.{$location->disk}.bucket"))->toBe('media');
});

test('saving with a blank secret keeps the previously stored secret', function () {
    $location = bucketLocation(['secret' => 'first-secret']);

    $this->actingAs($this->admin)->patch("/system/settings/storage/locations/{$location->id}", validStorageSettingsPayload([
        'secret' => '',
        'bucket' => 'renamed-bucket',
    ]))->assertSessionHasNoErrors();

    $location->refresh();
    expect($location->secret)->toBe('first-secret')
        ->and($location->bucket)->toBe('renamed-bucket');
});

test('saving with the server role asked for needs no access key, and deletes the stored credentials', function () {
    $location = bucketLocation();

    $payload = validStorageSettingsPayload(['use_instance_role' => true]);
    unset($payload['access_key'], $payload['secret']);

    $this->actingAs($this->admin)->patch("/system/settings/storage/locations/{$location->id}", $payload)
        ->assertSessionHasNoErrors();

    $location->refresh();
    expect($location->use_instance_role)->toBeTrue()
        ->and($location->key)->toBeNull()
        ->and($location->secret)->toBeNull()
        ->and($location->isConfigured())->toBeTrue();

    // And nothing is left in the row for a database dump to carry.
    expect(DB::table('storage_locations')->where('id', $location->id)->value('secret'))->toBeNull();
});

test('an access key is still required when it is missing rather than blank', function () {
    // Not the same case as an empty string: a validation rule that only
    // runs on a present attribute would let this through.
    $payload = validStorageSettingsPayload();
    unset($payload['access_key']);

    $this->actingAs($this->admin)->post('/system/settings/storage/locations', $payload)
        ->assertSessionHasErrors(['access_key']);
});

test('a new bucket without a secret is refused rather than saved unusable', function () {
    $this->actingAs($this->admin)->post('/system/settings/storage/locations', validStorageSettingsPayload(['secret' => '']))
        ->assertSessionHasErrors(['secret']);

    expect(StorageLocation::query()->count())->toBe(1);
});

test('the connection test runs without an access key when the server role is authenticating', function () {
    // The button has to accept the same configuration the save does —
    // otherwise there is no way to check an IAM-role setup before
    // switching uploads over to it, which is the whole point of it.
    //
    // Credentials are put in the environment so the SDK's default chain
    // resolves instantly from there rather than reaching for the EC2
    // metadata service, and the endpoint is a closed local port so the
    // request fails at once. This asserts the request is *made*, offline
    // and in well under a second — not that a bucket exists.
    putenv('AWS_ACCESS_KEY_ID=AKIAENVEXAMPLE');
    putenv('AWS_SECRET_ACCESS_KEY=env-secret');

    try {
        $response = $this->actingAs($this->admin)->post('/system/settings/storage/test', [
            'provider' => 's3',
            'use_instance_role' => true,
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'endpoint' => 'http://127.0.0.1:1',
            'use_path_style' => true,
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        expect(session('storage_test_result'))->toBeString();
    } finally {
        putenv('AWS_ACCESS_KEY_ID');
        putenv('AWS_SECRET_ACCESS_KEY');
    }
});

test('saving external storage settings rejects invalid input', function () {
    $this->actingAs($this->admin)->post('/system/settings/storage/locations', validStorageSettingsPayload([
        'name' => '',
        'access_key' => '',
        'bucket' => '',
        'region' => '',
    ]))->assertSessionHasErrors(['name', 'access_key', 'bucket', 'region']);
});

test('saving external storage settings signals the queue worker to restart', function () {
    Cache::forget('illuminate:queue:restart');

    $this->actingAs($this->admin)->post('/system/settings/storage/locations', validStorageSettingsPayload());

    expect(Cache::get('illuminate:queue:restart'))->not->toBeNull();
});

test('the default can be moved to a bucket taking uploads, and only one location is ever the default', function () {
    $location = bucketLocation();
    $closed = bucketLocation(['accepts_uploads' => false]);

    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'default_location_id' => $closed->id,
        'upload_notification_names_location' => false,
    ])->assertSessionHasErrors(['default_location_id']);

    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'default_location_id' => $location->id,
        'upload_notification_names_location' => false,
    ])->assertSessionHasNoErrors();

    expect(StorageLocation::query()->where('is_default', true)->pluck('id')->all())->toBe([$location->id]);
});

test('the default cannot stop taking uploads', function () {
    $location = bucketLocation();
    makeDefault($location);

    $this->actingAs($this->admin)->patch("/system/settings/storage/locations/{$location->id}", validStorageSettingsPayload([
        'accepts_uploads' => false,
    ]))->assertSessionHasErrors(['accepts_uploads']);

    expect($location->fresh()->accepts_uploads)->toBeTrue();
});

test('a location still holding files cannot be removed, an empty one can, the server disk never', function () {
    $full = bucketLocation();
    $empty = bucketLocation();
    File::factory()->create(['disk' => $full->disk, 'uploaded_by' => $this->admin->id]);
    $local = StorageLocation::query()->where('disk', 'files')->firstOrFail();

    $this->actingAs($this->admin)->delete("/system/settings/storage/locations/{$full->id}")
        ->assertSessionHasErrors(['location']);
    $this->actingAs($this->admin)->delete("/system/settings/storage/locations/{$empty->id}")
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->delete("/system/settings/storage/locations/{$local->id}")
        ->assertStatus(422);

    expect(StorageLocation::query()->find($full->id))->not->toBeNull()
        ->and(StorageLocation::query()->find($empty->id))->toBeNull();
});

test('the storage screen never hands back a secret', function () {
    bucketLocation(['secret' => 'super-secret-access-key']);

    $this->actingAs($this->admin)->get('/system/settings/storage')
        ->assertInertia(fn ($page) => $page
            ->where('locations.1.has_secret', true)
            ->missing('locations.1.secret'))
        ->assertDontSee('super-secret-access-key');
});

test('clients cannot save external storage settings', function () {
    $this->actingAs(User::factory()->client()->create())
        ->post('/system/settings/storage/locations', validStorageSettingsPayload())
        ->assertForbidden();
});

test('the entire storage settings surface is unavailable in the cloud edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs($this->admin);

    $this->get('/system/settings/storage')->assertNotFound();
    $this->post('/system/settings/storage/locations', validStorageSettingsPayload())->assertNotFound();
});

test('cloud edition never honors a stored external storage backend, even one written outside the form', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    // Simulate a stray/legacy row, bypassing the controller entirely —
    // the runtime gate must hold regardless of how it got there.
    $client = User::factory()->client()->create();
    $location = bucketLocation(['key' => 'AKIASTRAY', 'secret' => 'stray-secret', 'bucket' => 'stray-bucket']);
    makeDefault($location);
    assignLocations($client, [$location]);

    expect(resolvedDiskFor($this->admin))->toBe('files')
        ->and(resolvedDiskFor($client))->toBe('files');
});

test('a value cached while running as Community cannot leak into Cloud without an explicit flush', function () {
    // Warm the cache while Community is active and the backend is fully
    // configured — this is the exact scenario found manually: nothing
    // besides saving the settings form ever calls flush(), so switching
    // editions alone must not be enough to keep the old behavior alive.
    config()->set('projectsend.edition', Edition::Community);

    $location = bucketLocation();
    makeDefault($location);

    expect(config("filesystems.disks.{$location->disk}.bucket"))->toBe('my-bucket');

    // Switch to Cloud — deliberately WITHOUT calling flush() again, since
    // nothing in the app does that on an edition change either.
    config()->set('projectsend.edition', Edition::Cloud);
    config(["filesystems.disks.{$location->disk}" => null]);

    app(ExternalStorageConfigApplier::class)->apply();

    expect(config("filesystems.disks.{$location->disk}"))->toBeNull()
        ->and(resolvedDiskFor($this->admin))->toBe('files');
});

// ---- Notification, Files filter, orphans --------------------------------

test('the client upload email names the location only when the setting is on', function (bool $on) {
    Notification::fake();
    Storage::fake('files');
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    app(Settings::class)->set(Setting::AdminNotificationEmails, ['ops@example.com']);
    app(Settings::class)->set(Setting::UploadNotificationNamesLocation, $on);

    $client = uploadingClient();
    $eu = bucketLocation(['name' => 'Client archive (EU)']);
    assignLocations($client, [$eu]);
    applyStorage();
    Storage::fake($eu->disk);

    $this->actingAs($client);
    uploadChunkedAsClient()->assertOk();

    Notification::assertSentOnDemand(AdminClientUploadedNotification::class, function (AdminClientUploadedNotification $notification) use ($on): bool {
        $lines = implode("\n", $notification->toMail(new AnonymousNotifiable)->introLines);

        return str_contains($lines, 'Stored in: Client archive (EU)') === $on;
    });
})->with(['on' => true, 'off' => false]);

test('the files list filters by location, and offers the filter only with more than one', function () {
    $this->actingAs($this->admin)->get('/files')
        ->assertInertia(fn ($page) => $page->where('location_options', []));

    $bucket = bucketLocation(['name' => 'Archive']);
    $inBucket = File::factory()->create(['disk' => $bucket->disk, 'uploaded_by' => $this->admin->id, 'name' => 'in-bucket']);
    File::factory()->create(['disk' => 'files', 'uploaded_by' => $this->admin->id, 'name' => 'on-server']);

    $this->actingAs($this->admin)->get('/files?location='.$bucket->id)
        ->assertInertia(fn ($page) => $page
            ->has('location_options', 2)
            ->where('searching', true)
            ->has('files', 1)
            ->where('files.0.id', $inBucket->id));
});

test('the orphan scanner looks in every bucket, under the name it was given', function () {
    $a = bucketLocation(['name' => 'Archive', 'bucket' => 'archive']);
    $b = bucketLocation(['name' => 'Media', 'bucket' => 'media', 'accepts_uploads' => false]);
    applyStorage();

    $disks = app(OrphanFileScanner::class)->scannedDisks();

    expect(array_keys($disks))->toBe(['files', $a->disk, $b->disk])
        ->and($disks[$a->disk])->toBe(['label' => 'Archive', 'location' => 's3://archive'])
        ->and($disks[$b->disk]['location'])->toBe('s3://media');
});

/**
 * A configured S3 bucket location, saved and flushed into the applier's
 * cache. Not the default unless makeDefault() says so.
 *
 * @param  array<string, mixed>  $attributes
 */
function bucketLocation(array $attributes = []): StorageLocation
{
    $location = new StorageLocation;
    $location->disk = 'files_loc_'.Str::lower((string) Str::ulid());
    $location->fill([
        'name' => 'Bucket '.Str::random(4),
        'provider' => 's3',
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
        ...$attributes,
    ]);
    $location->save();

    app(ExternalStorageConfigApplier::class)->flush();

    return $location;
}

function makeDefault(StorageLocation $location): void
{
    StorageLocation::query()->update(['is_default' => false]);
    $location->forceFill(['is_default' => true])->save();

    applyStorage();
}

function applyStorage(): void
{
    app(ExternalStorageConfigApplier::class)->flush();
    app(ExternalStorageConfigApplier::class)->apply();
}

/** @param  list<StorageLocation>  $locations  first preferred */
function assignLocations(User $client, array $locations): void
{
    $sync = [];
    foreach ($locations as $index => $location) {
        $sync[$location->id] = ['priority' => $index + 1];
    }

    $client->storageLocations()->sync($sync);
}

function resolvedDiskFor(User $uploader, ?int $requestedLocationId = null): string
{
    $event = new ResolvingUploadDisk($uploader, $requestedLocationId);
    Event::dispatch($event);

    return $event->disk;
}

function uploadingClient(): User
{
    $client = User::factory()->client()->create();
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => Permission::Upload->value]);

    return $client;
}

/** @param  array<string, mixed>  $session */
function uploadChunkedAsClient(array $session = []): TestResponse
{
    $id = test()->postJson('/uploads', [
        'filename' => 'statement.pdf',
        'size' => 11,
        'type' => 'application/pdf',
        ...$session,
    ])->assertOk()->json('uploadId');

    $sign = test()->getJson("/uploads/{$id}/parts/1/sign")->assertOk()->json('url');
    test()->call('PUT', $sign, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'hello-world');

    return test()->postJson("/uploads/{$id}/complete");
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientUpdatePayload(User $client, array $overrides = []): array
{
    return [
        'name' => $client->name,
        'email' => $client->email,
        'active' => true,
        'storage_quota_mb' => null,
        ...$overrides,
    ];
}

/** The schema as it was before the storage locations migration. */
function withoutStorageLocationsTable(): void
{
    Schema::disableForeignKeyConstraints();
    Schema::table('upload_sessions', function (Blueprint $table) {
        $table->dropConstrainedForeignId('storage_location_id');
    });
    Schema::drop('client_storage_location');
    Schema::drop('storage_locations');
    Schema::enableForeignKeyConstraints();

    app(ExternalStorageConfigApplier::class)->flush();
}

/**
 * Runs the storage locations migration again from the previous schema,
 * after $before has put the old external storage row in place.
 */
function resetStorageLocationsMigration(Closure $before): void
{
    withoutStorageLocationsTable();
    $before();

    $migration = require database_path('migrations/2026_10_09_090000_create_storage_locations_tables.php');
    $migration->up();

    app(ExternalStorageConfigApplier::class)->flush();
}

/**
 * An external storage row exactly as 2.0/2.1 wrote it: S3 credentials in
 * place, and none of the columns added since.
 *
 * Written with the query builder and then stripped back, rather than
 * hand-rolled SQL, so it stays honest if the table changes shape again.
 */
function legacyStorageRow(): void
{
    withoutStorageLocationsTable();

    DB::table('external_storage_settings')->insert([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => Crypt::encryptString('shh'),
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
        'use_path_style' => false,
    ]);

    Schema::table('external_storage_settings', function (Blueprint $table) {
        $table->dropColumn(['provider', 'key_file', 'use_instance_role']);
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validStorageSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Client archive (EU)',
        'accepts_uploads' => true,
        'access_key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
        'endpoint' => null,
        'use_path_style' => false,
        'root' => null,
    ], $overrides);
}
