<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the secret is encrypted at rest', function () {
    ExternalStorageSettings::current()->fill(['secret' => 'super-secret-access-key'])->save();

    $raw = DB::table('external_storage_settings')->value('secret');

    expect($raw)->not->toBe('super-secret-access-key')
        ->and(ExternalStorageSettings::current()->secret)->toBe('super-secret-access-key');
});

test('the secret never reaches the cache store', function () {
    // The column above is only half the promise: this class caches its
    // resolved settings forever on every process boot, and a cache store
    // encrypts nothing. See the same pair in MailProviderSettingsTest.
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'super-secret-access-key',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();

    Cache::flush();
    app(ExternalStorageConfigApplier::class)->apply();

    $cached = Cache::get('platform.external_storage_settings.v3');

    expect($cached)->toBeArray()
        ->and(json_encode($cached))->not->toContain('super-secret-access-key');
});

test('the secret never reaches the database cache store either', function () {
    // Against the store config/cache.php actually defaults to, read as the
    // raw row an operator would find in a dump.
    config(['cache.default' => 'database']);

    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'super-secret-access-key',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();

    Cache::store('database')->flush();
    app(ExternalStorageConfigApplier::class)->apply();

    $rows = DB::table('cache')->pluck('value')->implode('|');

    expect($rows)->not->toContain('super-secret-access-key');
});

test('apply() still configures the secret it no longer caches', function () {
    // The other half: keeping the credential out of the cache must not
    // stop the disk from being configured with it.
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'super-secret-access-key',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();

    app(ExternalStorageConfigApplier::class)->flush();
    app(ExternalStorageConfigApplier::class)->apply();

    expect(config('filesystems.disks.files_external.secret'))->toBe('super-secret-access-key');
});

test('apply() is a no-op when not fully configured', function () {
    $originalKey = config('filesystems.disks.files_external.key');

    ExternalStorageSettings::current()->fill(['active' => true, 'key' => 'AKIA...'])->save();

    app(ExternalStorageConfigApplier::class)->flush();
    app(ExternalStorageConfigApplier::class)->apply();

    expect(config('filesystems.disks.files_external.key'))->toBe($originalKey);
});

test('apply() overrides the files_external disk config once fully configured and active', function () {
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
        'endpoint' => 'https://minio.example.test',
        'use_path_style' => true,
        'root' => 'projectsend',
    ])->save();

    app(ExternalStorageConfigApplier::class)->flush();
    app(ExternalStorageConfigApplier::class)->apply();

    expect(config('filesystems.disks.files_external.key'))->toBe('AKIAEXAMPLE')
        ->and(config('filesystems.disks.files_external.secret'))->toBe('shh')
        ->and(config('filesystems.disks.files_external.bucket'))->toBe('my-bucket')
        ->and(config('filesystems.disks.files_external.region'))->toBe('us-east-1')
        ->and(config('filesystems.disks.files_external.endpoint'))->toBe('https://minio.example.test')
        ->and(config('filesystems.disks.files_external.use_path_style_endpoint'))->toBeTrue()
        ->and(config('filesystems.disks.files_external.root'))->toBe('projectsend');
});

test('the ResolvingUploadDisk listener leaves new uploads on the local disk when not configured', function () {
    app(ExternalStorageConfigApplier::class)->flush();

    $event = new ResolvingUploadDisk($this->admin);
    Event::dispatch($event);

    expect($event->disk)->toBe('files');
});

test('the ResolvingUploadDisk listener redirects new uploads to files_external once configured and active', function () {
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();

    app(ExternalStorageConfigApplier::class)->flush();

    $event = new ResolvingUploadDisk($this->admin);
    Event::dispatch($event);

    expect($event->disk)->toBe('files_external');
});

test('a real upload lands on the external disk once the backend is active, and local uploads made before activation are unaffected', function () {
    Storage::fake('files');
    Storage::fake('files_external');

    uploadImageFile($this->admin);

    $localFile = File::query()->latest('id')->first();
    expect($localFile->disk)->toBe('files');
    Storage::disk('files')->assertExists($localFile->path);

    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();
    app(ExternalStorageConfigApplier::class)->flush();

    uploadImageFile($this->admin);

    $externalFile = File::query()->latest('id')->first();
    expect($externalFile->id)->not->toBe($localFile->id)
        ->and($externalFile->disk)->toBe('files_external');
    Storage::disk('files_external')->assertExists($externalFile->path);

    // The first file, uploaded before activation, still resolves to local
    // disk and is unaffected by the backend switch (no migration tool).
    Storage::disk('files')->assertExists($localFile->refresh()->path);
    expect($localFile->disk)->toBe('files');
});

test('staff can save external storage settings', function () {
    config()->set('projectsend.edition', Edition::Community);

    $this->actingAs($this->admin)->patch('/system/settings/storage', validStorageSettingsPayload([
        'access_key' => 'AKIANEW',
        'bucket' => 'renamed-bucket',
    ]))->assertRedirect();

    $settings = ExternalStorageSettings::current();
    expect($settings->key)->toBe('AKIANEW')
        ->and($settings->bucket)->toBe('renamed-bucket');
});

test('saving with a blank secret keeps the previously stored secret', function () {
    $this->actingAs($this->admin)->patch('/system/settings/storage', validStorageSettingsPayload([
        'secret' => 'first-secret',
    ]));

    $this->actingAs($this->admin)->patch('/system/settings/storage', validStorageSettingsPayload([
        'secret' => '',
        'bucket' => 'renamed-bucket',
    ]));

    $settings = ExternalStorageSettings::current();
    expect($settings->secret)->toBe('first-secret')
        ->and($settings->bucket)->toBe('renamed-bucket');
});

test('saving external storage settings rejects invalid input', function () {
    $this->actingAs($this->admin)->patch('/system/settings/storage', validStorageSettingsPayload([
        'access_key' => '',
        'bucket' => '',
        'region' => '',
    ]))->assertSessionHasErrors(['access_key', 'bucket', 'region']);
});

test('saving external storage settings signals the queue worker to restart', function () {
    Cache::forget('illuminate:queue:restart');

    $this->actingAs($this->admin)->patch('/system/settings/storage', validStorageSettingsPayload());

    expect(Cache::get('illuminate:queue:restart'))->not->toBeNull();
});

test('clients cannot save external storage settings', function () {
    $this->actingAs(User::factory()->client()->create())
        ->patch('/system/settings/storage', validStorageSettingsPayload())
        ->assertForbidden();
});

test('the entire storage settings surface is unavailable in the cloud edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs($this->admin);

    $this->get('/system/settings/storage')->assertNotFound();
    $this->patch('/system/settings/storage', validStorageSettingsPayload())->assertNotFound();
});

test('cloud edition never honors a stored external storage backend, even one written outside the form', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    // Simulate a stray/legacy row, bypassing the controller entirely —
    // the runtime gate must hold regardless of how it got there.
    ExternalStorageSettings::query()->create([
        'active' => true,
        'key' => 'AKIASTRAY',
        'secret' => 'stray-secret',
        'bucket' => 'stray-bucket',
        'region' => 'us-east-1',
    ]);

    app(ExternalStorageConfigApplier::class)->flush();

    $event = new ResolvingUploadDisk($this->admin);
    Event::dispatch($event);

    expect($event->disk)->toBe('files');
});

test('a value cached while running as Community cannot leak into Cloud without an explicit flush', function () {
    // Warm the cache while Community is active and the backend is fully
    // configured — this is the exact scenario found manually: nothing
    // besides saving the settings form ever calls flush(), so switching
    // editions alone must not be enough to keep the old behavior alive.
    config()->set('projectsend.edition', Edition::Community);

    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();

    app(ExternalStorageConfigApplier::class)->flush();
    app(ExternalStorageConfigApplier::class)->apply();

    expect(config('filesystems.disks.files_external.bucket'))->toBe('my-bucket');

    // Switch to Cloud — deliberately WITHOUT calling flush() again, since
    // nothing in the app does that on an edition change either.
    config()->set('projectsend.edition', Edition::Cloud);
    config(['filesystems.disks.files_external.bucket' => '']);

    app(ExternalStorageConfigApplier::class)->apply();

    expect(config('filesystems.disks.files_external.bucket'))->toBe('');

    $event = new ResolvingUploadDisk($this->admin);
    Event::dispatch($event);

    expect($event->disk)->toBe('files');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validStorageSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'active' => true,
        'access_key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
        'endpoint' => null,
        'use_path_style' => false,
        'root' => null,
    ], $overrides);
}
