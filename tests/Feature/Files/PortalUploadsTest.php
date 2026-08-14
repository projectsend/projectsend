<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
});

function grantUploadToClient(User $client): void
{
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => Permission::Upload->value]);
}

/**
 * The default Client role now includes upload (SystemRole::Client), so a
 * plain client factory no longer has anything to test the "without upload"
 * path against — this is a client on a custom role that grants neither
 * upload nor create_own_folders.
 */
function clientWithoutUpload(): User
{
    $role = Role::query()->create(['name' => 'Client Without Upload', 'is_administrator' => false, 'is_system' => false]);

    return User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);
}

/**
 * Drives the full chunked-upload contract (session -> part -> complete)
 * as whichever user is currently `actingAs()` — the client portal's own
 * upload mechanism since /my-files/upload's old single-request endpoint
 * was removed in favor of the same uploads.* routes staff use.
 */
function uploadAsClient(string $filename = 'statement.pdf', string $content = 'hello-world', ?string $description = null): TestResponse
{
    $session = test()->postJson('/uploads', [
        'filename' => $filename,
        'size' => strlen($content),
        'type' => 'application/pdf',
        'description' => $description,
    ])->assertOk()->json('uploadId');

    $sign = test()->getJson("/uploads/{$session}/parts/1/sign")->assertOk()->json('url');
    test()->call('PUT', $sign, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $content);

    return test()->postJson("/uploads/{$session}/complete");
}

test('a client with the upload permission can upload a file that lands loose and self-owned', function () {
    $client = User::factory()->client()->create();
    grantUploadToClient($client);
    $this->actingAs($client);

    uploadAsClient(description: 'My statement')->assertOk();

    $file = File::query()->latest('id')->firstOrFail();

    expect($file->folder_id)->toBeNull()
        ->and($file->uploaded_by)->toBe($client->id)
        ->and($file->description)->toBe('My statement')
        ->and(ActivityLog::query()->where('action', Action::FileUploaded)->where('subject_name', $file->name)->exists())->toBeTrue();
});

test('a client sees their own portal upload in their my-files listing', function () {
    $client = User::factory()->client()->create();
    grantUploadToClient($client);
    $this->actingAs($client);

    uploadAsClient();
    $file = File::query()->latest('id')->firstOrFail();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/themes/default/my-files')
            ->where('files.0.id', $file->id),
    );
});

test('a client-scoped staff member sees a file their assigned client uploaded', function () {
    $client = User::factory()->client()->create();
    grantUploadToClient($client);
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $this->actingAs($client);
    uploadAsClient();
    $file = File::query()->latest('id')->firstOrFail();

    expect(app(StaffLibraryScope::class)->files($manager)->pluck('id')->all())->toContain($file->id);
});

test('a client without the upload permission is forbidden from creating an upload session', function () {
    $client = clientWithoutUpload();
    $this->actingAs($client);

    $this->postJson('/uploads', ['filename' => 'statement.pdf', 'size' => 11])->assertForbidden();
});

test('the my-files page hides the upload control without the permission', function () {
    $client = clientWithoutUpload();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_upload', false),
    );
});

test('the my-files page exposes the upload control once the permission is granted', function () {
    $client = User::factory()->client()->create();
    grantUploadToClient($client);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_upload', true),
    );
});

test('a client with the upload permission can reach the dedicated upload page', function () {
    $client = User::factory()->client()->create();
    grantUploadToClient($client);

    $this->actingAs($client)->get('/my-files/upload')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/upload')
            ->has('max_file_size_mb')
            ->has('part_size_mb')
            ->has('used_bytes'),
    );
});

test('a client without the upload permission is forbidden from the upload page', function () {
    $client = clientWithoutUpload();

    $this->actingAs($client)->get('/my-files/upload')->assertForbidden();
});

test('a staff account cannot reach the client portal upload page', function () {
    $this->actingAs($this->admin)->get('/my-files/upload')->assertNotFound();
});

test('a client upload notifies staff who can see uploads, in-app', function () {
    $client = User::factory()->client()->create();
    grantUploadToClient($client);
    // A fresh custom role starts with zero granted permissions and
    // is_administrator = false — genuinely unrelated to file visibility,
    // unlike any seeded SystemRole (which all bypass the permission
    // table entirely once is_administrator, or already grant Upload).
    $noAccessRole = Role::query()->create(['name' => 'No File Access']);
    $unrelatedStaff = User::factory()->create(['role_id' => $noAccessRole->id]);

    $this->actingAs($client);
    uploadAsClient()->assertOk();

    $file = File::query()->latest('id')->firstOrFail();

    $entry = InAppNotification::query()
        ->where('user_id', $this->admin->id)
        ->where('type', 'client_uploaded')
        ->sole();

    expect($entry->subject_id)->toBe($file->id)
        ->and($entry->data)->toBe(['clientName' => $client->name, 'itemName' => $file->name, 'fileId' => $file->id]);

    expect(InAppNotification::query()->where('user_id', $unrelatedStaff->id)->exists())->toBeFalse();
});
