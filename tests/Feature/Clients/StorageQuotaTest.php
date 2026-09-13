<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function grantUploadPermission(User $client): void
{
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => Permission::Upload->value]);
}

function createChunkedSession(int $size, string $filename = 'chunked.zip'): string
{
    $response = test()->postJson('/uploads', [
        'filename' => $filename,
        'size' => $size,
        'type' => 'application/octet-stream',
    ]);

    $response->assertOk();

    return $response->json('uploadId');
}

function putChunkedPart(string $sessionId, int $part, string $content): void
{
    $sign = test()->getJson("/uploads/{$sessionId}/parts/{$part}/sign")->assertOk();
    test()->call('PUT', $sign->json('url'), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $content);
}

function makeClientFile(User $client, int $sizeBytes, string $name = 'existing'): File
{
    return File::factory()->create([
        'uploaded_by' => $client->id,
        'name' => $name,
        'original_name' => $name.'.pdf',
        'path' => '2026/07/'.Str::uuid()->toString().'.pdf',
        'mime_type' => 'application/pdf',
        'size' => $sizeBytes,
    ]);
}

// The simple single-request client upload endpoint (POST /my-files/upload)
// was removed once the client portal moved to the same chunked upload
// mechanism staff uses — see "a client under quota can create and
// complete a chunked session", "...rejected at session creation",
// "...rejected at completion", and "a quota of 0 is unlimited for
// chunked uploads too" below for this file's equivalent coverage.

test('usage sums only that client\'s own non-deleted files', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();

    $kept = makeClientFile($client, 1024 * 1024, 'kept');
    makeClientFile($client, 2 * 1024 * 1024, 'deleted')->delete();
    makeClientFile($other, 5 * 1024 * 1024, 'someone-elses');

    $usage = app(ClientStorageUsage::class);

    expect($usage->usedBytes($client))->toBe($kept->size);
});

test('the default storage quota setting prefills a new client\'s quota field', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 500);

    $this->actingAs($this->admin)->get('/clients/create')->assertInertia(
        fn (AssertableInertia $page) => $page->where('default_storage_quota_mb', 500),
    );
});

test('both client screens show the quota that will actually be enforced, floor included', function () {
    // The screens present this as what happens, not as a value being
    // edited, so they have to show the effective number. The edit screen
    // in particular mirrors quotaMb()'s resolution client-side to draw the
    // usage bar: handed the raw setting on a floored installation, it
    // computes an effective quota of 0, prints "unlimited", and hides the
    // bar entirely — for a client whose next upload is about to be
    // rejected for exceeding a limit the screen said did not exist.
    config()->set('projectsend.platform.default_client_quota_mb', 2048);
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 0);

    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);

    $this->actingAs($this->admin)->get('/clients/create')->assertInertia(
        fn (AssertableInertia $page) => $page->where('default_storage_quota_mb', 2048),
    );

    $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('default_storage_quota_mb', 2048),
    );
});

test('the settings form still edits the stored setting, never the floor', function () {
    // The other half, and the reason this is not one change applied
    // everywhere. That field is read, then written back on save. Prefilled
    // with the floor, the next save of that page would write the
    // platform's number into the setting as the administrator's own
    // choice — where it would outlive the floor being removed.
    config()->set('projectsend.platform.default_client_quota_mb', 2048);
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 0);

    $this->actingAs($this->admin)->get('/system/settings/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->where('default_client_storage_quota_mb', 0),
    );
});

test('creating a client with a quota typed into the form stores it', function () {
    // The 500 this exists for. `integer` validates a numeric string and
    // does not convert it, so the form's "2048" reached
    // ClientAccounts::create()'s `int $storageQuotaMb` under strict_types
    // and raised a TypeError. Blank was fine -- null ?? 0 is an int -- so
    // every existing test here went through the one branch that worked,
    // and it shipped to the whole fleet on 2.4.1.
    //
    // post() and not postJson(): a form body is strings, which is the
    // condition. A JSON number would pass on the unfixed code.
    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Quota Ltd',
        'email' => 'typed-quota@example.test',
        'password' => 'a-sufficiently-long-password',
        'password_confirmation' => 'a-sufficiently-long-password',
        'storage_quota_mb' => '2048',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect(User::query()->where('email', 'typed-quota@example.test')->sole()->storage_quota_mb)->toBe(2048);
});

test('clearing the storage quota field to blank on the edit form resets it to inherit the site default', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 150);
    $client = User::factory()->client()->create(['storage_quota_mb' => 100]);

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name,
        'email' => $client->email,
        'active' => true,
        'storage_quota_mb' => '',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $client->refresh();
    expect($client->storage_quota_mb)->toBe(0)
        ->and(app(ClientStorageUsage::class)->quotaMb($client))->toBe(150);
});

test('a client under quota can create and complete a chunked session', function () {
    $client = User::factory()->client()->create(['storage_quota_mb' => 1]);
    grantUploadPermission($client);
    $this->actingAs($client);

    $sessionId = createChunkedSession(11, 'small.txt');
    putChunkedPart($sessionId, 1, 'hello-');
    putChunkedPart($sessionId, 2, 'world');

    $this->postJson("/uploads/{$sessionId}/complete")->assertOk();

    expect(File::query()->where('uploaded_by', $client->id)->exists())->toBeTrue();
});

test('a chunked session whose declared size already exceeds quota is rejected at session creation', function () {
    $client = User::factory()->client()->create(['storage_quota_mb' => 1]);
    grantUploadPermission($client);
    makeClientFile($client, 1000 * 1024); // ~1000 KB already used, out of a 1 MB quota

    $this->actingAs($client);
    $this->postJson('/uploads', [
        'filename' => 'huge.pdf',
        'size' => 200 * 1024,
        'type' => 'application/pdf',
    ])->assertJsonValidationErrors('size');

    expect(UploadSession::query()->count())->toBe(0);
});

test('a client whose quota fills while the transfer is running is rejected at completion', function () {
    $client = User::factory()->client()->create(['storage_quota_mb' => 1]);
    grantUploadPermission($client);
    $this->actingAs($client);

    // Declared honestly, and there is room for it when the session opens.
    $sessionId = createChunkedSession(50 * 1024, 'honest.txt');
    putChunkedPart($sessionId, 1, str_repeat('a', 50 * 1024));

    // The rest of the quota goes while the bytes are in flight — another
    // device, a staff member uploading on their behalf, a second transfer
    // finishing first. A big file takes a long time and the check at
    // session creation is only true of the moment it was made, which is
    // why completion asks again rather than trusting it.
    makeClientFile($client, 1000 * 1024);

    $this->postJson("/uploads/{$sessionId}/complete")->assertJsonValidationErrors('size');

    expect(File::query()->where('uploaded_by', $client->id)->count())->toBe(1) // only the file that filled the quota
        ->and(UploadSession::query()->find($sessionId))->toBeNull();

    $paths = Storage::disk('files')->allFiles();
    expect(collect($paths)->filter(fn (string $path) => str_ends_with($path, '.txt')))->toBeEmpty();
});

test('a quota of 0 is unlimited for chunked uploads too, when no site default is set', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 0);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);
    grantUploadPermission($client);
    makeClientFile($client, 500 * 1024 * 1024);
    $this->actingAs($client);

    $sessionId = createChunkedSession(11, 'another.txt');
    putChunkedPart($sessionId, 1, 'hello-');
    putChunkedPart($sessionId, 2, 'world');

    $this->postJson("/uploads/{$sessionId}/complete")->assertOk();
});

test('ClientStorageUsage::quotaMb() resolves a client with no custom quota to the site default', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 250);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);

    expect(app(ClientStorageUsage::class)->quotaMb($client))->toBe(250);
});

test('a platform floor holds a client the installation never gave a quota to', function () {
    // The hole this closes: the setting's own default is 0, 0 means
    // unlimited, and an account that arrived without an explicit quota --
    // a self-registered one, say -- inherits it. On an installation a
    // platform runs for other people that is unmetered hosting one account
    // away, so a platform may put a floor under it from the environment,
    // exactly as it sets the seat caps.
    config()->set('projectsend.platform.default_client_quota_mb', 1);
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 0);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);
    grantUploadPermission($client);
    makeClientFile($client, 1000 * 1024);
    $this->actingAs($client);

    $this->postJson('/uploads', [
        'filename' => 'over-the-floor.pdf',
        'size' => 200 * 1024,
        'type' => 'application/pdf',
    ])->assertJsonValidationErrors('size');
});

test('a floor is under the setting, never over it', function () {
    // An administrator who has chosen a number keeps it, including a
    // larger one. The floor is for the installation that chose nothing.
    config()->set('projectsend.platform.default_client_quota_mb', 100);
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 250);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);

    expect(app(ClientStorageUsage::class)->quotaMb($client))->toBe(250);
});

test('an install with no platform behind it is unaffected', function () {
    // Nothing set anywhere is still unlimited. This must not become a
    // ceiling that appears on self-hosted installs by default.
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 0);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);

    expect(app(ClientStorageUsage::class)->quotaMb($client))->toBe(0);
});

test('ClientStorageUsage::quotaMb() lets a client\'s own custom quota override the site default', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 250);
    $client = User::factory()->client()->create(['storage_quota_mb' => 500]);

    expect(app(ClientStorageUsage::class)->quotaMb($client))->toBe(500);
});

test('a client with no custom quota is limited by the site default once one is set', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 1);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);
    grantUploadPermission($client);
    makeClientFile($client, 1000 * 1024); // ~1000 KB already used, out of the 1 MB site default
    $this->actingAs($client);

    $this->postJson('/uploads', [
        'filename' => 'over-the-default.pdf',
        'size' => 200 * 1024,
        'type' => 'application/pdf',
    ])->assertJsonValidationErrors('size');
});

test('the rejection names the quota the client is actually held to', function () {
    // storage_quota_mb of 0 means "no quota of their own", and the site
    // default is what is then enforced — so the column is the one number
    // the message must not print.
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 1);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);
    grantUploadPermission($client);
    makeClientFile($client, 1000 * 1024);
    $this->actingAs($client);

    $response = $this->postJson('/uploads', [
        'filename' => 'over-the-default.pdf',
        'size' => 200 * 1024,
        'type' => 'application/pdf',
    ])->assertJsonValidationErrors('size');

    expect($response->json('errors.size.0'))->toBe('This upload would exceed your storage quota of 1 MB.');
});

test('the same is true when the real byte count is what pushes them over', function () {
    // The completion check is a second copy of the same sentence, and had
    // the same bug.
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 1);
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);
    grantUploadPermission($client);
    $this->actingAs($client);

    $sessionId = createChunkedSession(50 * 1024, 'honest.txt');
    putChunkedPart($sessionId, 1, str_repeat('a', 50 * 1024));

    makeClientFile($client, 1000 * 1024);

    $response = $this->postJson("/uploads/{$sessionId}/complete")->assertJsonValidationErrors('size');

    expect($response->json('errors.size.0'))->toBe('This upload would exceed your storage quota of 1 MB.');
});

test('a client with a quota of their own still sees their own number', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 250);
    $client = User::factory()->client()->create(['storage_quota_mb' => 1]);
    grantUploadPermission($client);
    makeClientFile($client, 1000 * 1024);
    $this->actingAs($client);

    $response = $this->postJson('/uploads', [
        'filename' => 'over-their-own.pdf',
        'size' => 200 * 1024,
        'type' => 'application/pdf',
    ])->assertJsonValidationErrors('size');

    expect($response->json('errors.size.0'))->toBe('This upload would exceed your storage quota of 1 MB.');
});

test('a client\'s own custom quota is unaffected by later changes to the site default', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 1);
    $client = User::factory()->client()->create(['storage_quota_mb' => 500]); // an explicit, much larger override
    grantUploadPermission($client);
    makeClientFile($client, 1000 * 1024); // already past the 1 MB site default, but well under this client's own 500 MB
    $this->actingAs($client);

    $sessionId = createChunkedSession(200 * 1024, 'still-fine.pdf');
    putChunkedPart($sessionId, 1, str_repeat('a', 200 * 1024));

    $this->postJson("/uploads/{$sessionId}/complete")->assertOk();
});

test('a self-registered client with no explicit quota inherits the site default automatically', function () {
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 1);

    $this->post('/register', [
        'name' => 'Self Registered',
        'email' => 'self-registered@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect();

    $client = User::query()->where('email', 'self-registered@example.com')->sole();
    expect($client->storage_quota_mb)->toBe(0); // never set explicitly — inherits at enforcement time

    grantUploadPermission($client);
    $this->actingAs($client);

    // Well within the 1 MB site default up front, then a second upload
    // that would push the client over it — the flood scenario this
    // feature exists to close.
    makeClientFile($client, 900 * 1024);

    $this->postJson('/uploads', [
        'filename' => 'flood-attempt.pdf',
        'size' => 200 * 1024,
        'type' => 'application/pdf',
    ])->assertJsonValidationErrors('size');
});

test('a staff upload into a client\'s folder is unaffected by that client\'s quota', function () {
    $client = User::factory()->client()->create(['storage_quota_mb' => 1]);
    makeClientFile($client, 1000 * 1024); // already near the 1 MB quota

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('staff-upload.pdf', 500, 'application/pdf'),
        'name' => '',
        'description' => '',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    // The staff upload is attributed to the staff member, not the client,
    // so it never counts against the client's quota.
    $staffFile = File::query()->where('original_name', 'staff-upload.pdf')->sole();
    expect($staffFile->uploaded_by)->toBe($this->admin->id);
});
