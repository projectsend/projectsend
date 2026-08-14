<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function grantClientUpload(User $client): void
{
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => Permission::Upload->value]);
}

function chunkedUploadAttempt(string $filename, int $size = 4): TestResponse
{
    return test()->postJson('/uploads', [
        'filename' => $filename,
        'size' => $size,
        'type' => 'application/octet-stream',
    ]);
}

test('by default a disallowed extension is rejected for the plain staff upload', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('shell.php', 4, 'application/x-httpd-php'),
        'name' => '',
        'description' => '',
    ])->assertSessionHasErrors('file');

    expect(File::query()->count())->toBe(0);
});

test('by default an allowed extension still uploads fine for staff', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('contract.pdf', 4, 'application/pdf'),
        'name' => '',
        'description' => '',
    ])->assertRedirect();

    expect(File::query()->count())->toBe(1);
});

test('by default a client portal upload of a disallowed extension is rejected', function () {
    $client = User::factory()->client()->create();
    grantClientUpload($client);
    $this->actingAs($client);

    chunkedUploadAttempt('shell.php')->assertJsonValidationErrors('filename');

    expect(File::query()->count())->toBe(0)
        ->and(UploadSession::query()->count())->toBe(0);
});

test('scoping the restriction to clients only exempts staff', function () {
    app(Settings::class)->set(Setting::UploadTypeRestriction, 'clients');

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('tool.exe', 4, 'application/octet-stream'),
        'name' => '',
        'description' => '',
    ])->assertRedirect();

    expect(File::query()->count())->toBe(1);

    $client = User::factory()->client()->create();
    grantClientUpload($client);
    $this->actingAs($client);

    chunkedUploadAttempt('tool.exe')->assertJsonValidationErrors('filename');

    expect(File::query()->count())->toBe(1);
});

test('turning the restriction off allows any extension for everyone', function () {
    app(Settings::class)->set(Setting::UploadTypeRestriction, 'none');

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('tool.exe', 4, 'application/octet-stream'),
        'name' => '',
        'description' => '',
    ])->assertRedirect();

    $client = User::factory()->client()->create();
    grantClientUpload($client);
    $this->actingAs($client);

    $session = chunkedUploadAttempt('shell.php', 3)->assertOk()->json('uploadId');
    $sign = $this->getJson("/uploads/{$session}/parts/1/sign")->assertOk()->json('url');
    $this->call('PUT', $sign, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'abc');
    $this->postJson("/uploads/{$session}/complete")->assertOk();

    expect(File::query()->count())->toBe(2);
});

test('a disallowed extension is rejected at chunked-upload session creation, before any bytes are accepted', function () {
    $this->actingAs($this->admin);

    $this->postJson('/uploads', [
        'filename' => 'shell.php',
        'size' => 10,
        'type' => 'application/x-httpd-php',
    ])->assertJsonValidationErrors('filename');

    expect(UploadSession::query()->count())->toBe(0);
});

test('a disallowed extension is rejected at chunked-upload session creation for a client too', function () {
    app(Settings::class)->set(Setting::UploadTypeRestriction, 'all');
    $client = User::factory()->client()->create();
    grantClientUpload($client);

    $this->actingAs($client);
    $this->postJson('/uploads', [
        'filename' => 'shell.php',
        'size' => 10,
        'type' => 'application/x-httpd-php',
    ])->assertJsonValidationErrors('filename');

    expect(UploadSession::query()->count())->toBe(0);
});

test('the chunked upload completion detects the real mime type instead of trusting the client', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson('/uploads', [
        'filename' => 'note.txt',
        'size' => 11,
        // Spoofed: claims to be HTML so a stored-XSS payload would be
        // served `Content-Type: text/html; Content-Disposition: inline`
        // by FileThumbnailController::preview() if trusted.
        'type' => 'text/html',
    ])->assertOk();

    $sessionId = $response->json('uploadId');

    $sign = $this->getJson("/uploads/{$sessionId}/parts/1/sign")->json('url');
    $this->call('PUT', $sign, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'hello-world')->assertOk();

    $complete = $this->postJson("/uploads/{$sessionId}/complete")->assertOk();
    $file = File::query()->findOrFail($complete->json('file_id'));

    expect($file->mime_type)->not->toBe('text/html');
});

test('staff can view and update the upload restriction settings', function () {
    $this->actingAs($this->admin)->get('/system/settings/uploads')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/uploads')
            ->where('upload_type_restriction', 'all')
            ->has('allowed_upload_extensions'),
    );

    $this->actingAs($this->admin)->patch('/system/settings/uploads', [
        'max_file_size_mb' => 2048,
        'upload_type_restriction' => 'clients',
        'allowed_upload_extensions' => ['pdf', 'PNG', 'pdf'],
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::UploadTypeRestriction))->toBe('clients')
        ->and(app(Settings::class)->get(Setting::AllowedUploadExtensions))->toBe(['pdf', 'png']);
});

test('an invalid restriction value is rejected', function () {
    $this->actingAs($this->admin)->patch('/system/settings/uploads', [
        'max_file_size_mb' => 2048,
        'upload_type_restriction' => 'sometimes',
        'allowed_upload_extensions' => ['pdf'],
    ])->assertSessionHasErrors('upload_type_restriction');
});

test('adding a dangerous extension back to the allow list saves but warns', function () {
    $this->actingAs($this->admin)->patch('/system/settings/uploads', [
        'max_file_size_mb' => 2048,
        'upload_type_restriction' => 'all',
        'allowed_upload_extensions' => ['pdf', 'php'],
    ])->assertRedirect()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'php'));

    expect(app(Settings::class)->get(Setting::AllowedUploadExtensions))->toContain('php');
});
