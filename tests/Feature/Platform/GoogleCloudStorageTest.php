<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use App\Modules\Platform\Settings\StorageProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * A syntactically real service account key, generated per test.
 *
 * V4 signing is done locally with the private key — no network, no
 * project, no bucket needs to exist — which is what makes the signed-URL
 * assertions below real rather than mocked.
 *
 * @return array<string, string>
 */
function fakeServiceAccountKey(): array
{
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $privateKey);

    return [
        'type' => 'service_account',
        'project_id' => 'projectsend-test',
        'private_key_id' => 'test-key-id',
        'private_key' => $privateKey,
        'client_email' => 'projectsend@projectsend-test.iam.gserviceaccount.com',
        'client_id' => '1234567890',
    ];
}

function configureGcs(array $overrides = []): void
{
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'provider' => StorageProvider::Gcs,
        'bucket' => 'projectsend-files',
        'key_file' => json_encode(fakeServiceAccountKey()),
        ...$overrides,
    ])->save();

    app(ExternalStorageConfigApplier::class)->flush();
    app(ExternalStorageConfigApplier::class)->apply();
}

test('choosing Google Cloud Storage points the external disk at the gcs driver', function () {
    configureGcs();

    expect(config('filesystems.disks.files_external.driver'))->toBe('gcs')
        ->and(config('filesystems.disks.files_external.bucket'))->toBe('projectsend-files')
        // The key file is decoded for the client, and the S3 leftovers
        // from the config stub are cleared rather than left looking like
        // configuration.
        ->and(config('filesystems.disks.files_external.key_file'))->toBeArray()
        ->and(config('filesystems.disks.files_external.key_file')['client_email'])
        ->toBe('projectsend@projectsend-test.iam.gserviceaccount.com')
        ->and(config('filesystems.disks.files_external.key'))->toBeNull()
        ->and(config('filesystems.disks.files_external.secret'))->toBeNull();
});

test('a temporary url can be generated at all', function () {
    // Not a tautology. Laravel's FilesystemAdapter::temporaryUrl() looks
    // for a method named getTemporaryUrl() on the adapter; League's GCS
    // adapter names its method temporaryUrl(). Without the callback
    // registered by GoogleCloudStorageDriver the two never meet and this
    // throws "This driver does not support creating temporary URLs" —
    // which is every download and every preview on a GCS install.
    configureGcs();

    $url = Storage::disk('files_external')->temporaryUrl('2026/07/report.pdf', now()->addHour());

    expect($url)->toStartWith('https://storage.googleapis.com/projectsend-files/2026/07/report.pdf?')
        ->and($url)->toContain('X-Goog-Algorithm=GOOG4-RSA-SHA256')
        ->and($url)->toContain('X-Goog-Signature=');
});

test('the download filename survives into the signed url', function () {
    // The failure this covers is silent, which is why it is asserted on
    // the URL's contents rather than on "a redirect happened": callers
    // speak S3's ResponseContentDisposition, GCS wants responseDisposition,
    // and an unrecognised option is dropped without complaint. The symptom
    // is a download named after the storage key, and nothing in the logs.
    configureGcs();

    $url = Storage::disk('files_external')->temporaryUrl(
        '2026/07/8f3a-uuid.pdf',
        now()->addHour(),
        ['ResponseContentDisposition' => 'attachment; filename="Quarterly report.pdf"'],
    );

    expect($url)->toContain('response-content-disposition=')
        ->and(urldecode($url))->toContain('attachment; filename="Quarterly report.pdf"');
});

test('an option that is already a google name is passed through untranslated', function () {
    configureGcs();

    $url = Storage::disk('files_external')->temporaryUrl(
        '2026/07/report.pdf',
        now()->addHour(),
        ['responseType' => 'application/pdf'],
    );

    expect($url)->toContain('response-content-type=application%2Fpdf');
});

test('the folder setting prefixes the object path for gcs, the way root does for s3', function () {
    configureGcs(['root' => 'projectsend']);

    $url = Storage::disk('files_external')->temporaryUrl('2026/07/report.pdf', now()->addHour());

    expect($url)->toStartWith('https://storage.googleapis.com/projectsend-files/projectsend/2026/07/report.pdf?');
});

test('new uploads are routed to the external disk once gcs is configured', function () {
    configureGcs();

    $event = new ResolvingUploadDisk($this->admin);
    Event::dispatch($event);

    expect($event->disk)->toBe('files_external');
});

test('gcs is judged configured by its key file, not by an access key and secret', function () {
    $settings = ExternalStorageSettings::current();

    // Everything S3 would need, and nothing GCS needs.
    $settings->fill([
        'active' => true,
        'provider' => StorageProvider::Gcs,
        'bucket' => 'projectsend-files',
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
    ])->save();

    expect($settings->isConfigured())->toBeFalse();

    $settings->fill(['key_file' => json_encode(fakeServiceAccountKey())])->save();

    expect($settings->fresh()->isConfigured())->toBeTrue();
});

test('the service account key is encrypted at rest', function () {
    $key = fakeServiceAccountKey();

    ExternalStorageSettings::current()->fill(['key_file' => json_encode($key)])->save();

    $raw = DB::table('external_storage_settings')->value('key_file');

    expect($raw)->not->toContain('BEGIN PRIVATE KEY')
        ->and($raw)->not->toContain('iam.gserviceaccount.com')
        ->and(json_decode((string) ExternalStorageSettings::current()->key_file, true)['private_key'])
        ->toBe($key['private_key']);
});

test('a file stored on gcs downloads as a redirect to a signed url, not an nginx path', function () {
    configureGcs();

    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'original_name' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'path' => '2026/07/contract.pdf',
        'disk' => 'files_external',
    ]);

    $response = $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $response->assertRedirect();
    $response->assertHeaderMissing('X-Accel-Redirect');

    $target = urldecode((string) $response->headers->get('Location'));

    expect($target)->toStartWith('https://storage.googleapis.com/projectsend-files/2026/07/contract.pdf?')
        ->and($target)->toContain('attachment; filename="contract.pdf"');
});

test('staff can save a Google Cloud Storage backend through the settings form', function () {
    $key = json_encode(fakeServiceAccountKey());

    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 'gcs',
        'bucket' => 'projectsend-files',
        'key_file' => $key,
        'use_path_style' => false,
    ])->assertRedirect();

    $settings = ExternalStorageSettings::current();

    expect($settings->provider)->toBe(StorageProvider::Gcs)
        ->and($settings->key_file)->toBe($key)
        ->and($settings->isConfigured())->toBeTrue();
});

test('switching to gcs does not demand an access key or a region', function () {
    // The S3 fields are required_if, not required — otherwise selecting
    // Google would insist on an AWS region that means nothing to it.
    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 'gcs',
        'bucket' => 'projectsend-files',
        'key_file' => json_encode(fakeServiceAccountKey()),
        'use_path_style' => false,
    ])->assertSessionHasNoErrors();
});

test('a blank key file keeps the one already stored', function () {
    $key = json_encode(fakeServiceAccountKey());
    ExternalStorageSettings::current()->fill(['provider' => StorageProvider::Gcs, 'key_file' => $key])->save();

    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 'gcs',
        'bucket' => 'a-different-bucket',
        'key_file' => '',
        'use_path_style' => false,
    ])->assertRedirect();

    expect(ExternalStorageSettings::current()->key_file)->toBe($key)
        ->and(ExternalStorageSettings::current()->bucket)->toBe('a-different-bucket');
});

test('a key file that is not a service account key is rejected before it can be saved', function () {
    // A paste that lost its last line is the likeliest way this goes
    // wrong, and the alternative to catching it here is a 500 at the
    // first upload with nothing pointing at the cause.
    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 'gcs',
        'bucket' => 'projectsend-files',
        'key_file' => '{"type": "service_account", "project_id": "demo"',
        'use_path_style' => false,
    ])->assertSessionHasErrors('key_file');

    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 'gcs',
        'bucket' => 'projectsend-files',
        'key_file' => '{"type": "service_account", "project_id": "demo"}',
        'use_path_style' => false,
    ])->assertSessionHasErrors('key_file');
});

test('the settings screen offers the provider choice and says whether a key is stored', function () {
    ExternalStorageSettings::current()->fill([
        'provider' => StorageProvider::Gcs,
        'key_file' => json_encode(fakeServiceAccountKey()),
    ])->save();

    $this->actingAs($this->admin)->get('/system/settings/storage')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('system/settings/storage')
            ->where('provider', 'gcs')
            ->where('has_key_file', true)
            // The key itself is never sent back to the browser.
            ->missing('key_file'));
});
