<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function grantChunkedUploadPermission(User $user): void
{
    RolePermission::query()->firstOrCreate(['role_id' => $user->role_id, 'permission' => Permission::Upload->value]);
}

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

afterEach(function () {
    Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/uploads-tmp'));
});

function createSession(int $size = 1024, string $filename = 'big.zip'): string
{
    $response = test()->postJson('/uploads', [
        'filename' => $filename,
        'size' => $size,
        'type' => 'application/octet-stream',
    ]);

    $response->assertOk();

    return $response->json('uploadId');
}

function putPart(string $sessionId, int $part, string $content): TestResponse
{
    $sign = test()->getJson("/uploads/{$sessionId}/parts/{$part}/sign")->assertOk();
    $url = $sign->json('url');

    return test()->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $content);
}

test('a session within the size limit is created; over the limit is refused', function () {
    $this->actingAs($this->admin);

    createSession(1024);

    $this->postJson('/uploads', [
        'filename' => 'huge.zip',
        'size' => 3 * 1024 * 1024 * 1024, // 3 GB > 2048 MB default
        'type' => 'application/octet-stream',
    ])->assertStatus(422);

    // 0 = unlimited: a 50 GB declaration is fine.
    app(Settings::class)->set(Setting::MaxFileSizeMb, 0);
    createSession(50 * 1024 * 1024 * 1024, 'giant.zip');
});

test('parts upload via signed urls, list for resume, and tampering is rejected', function () {
    $this->actingAs($this->admin);
    $sessionId = createSession();

    $response = putPart($sessionId, 1, 'hello-');
    $response->assertOk();
    expect($response->headers->get('ETag'))->toBe('"'.md5('hello-').'"');

    putPart($sessionId, 2, 'world')->assertOk();

    // Resume contract: both parts listed in order with sizes.
    $this->getJson("/uploads/{$sessionId}/parts")->assertOk()->assertJson([
        ['PartNumber' => 1, 'Size' => 6],
        ['PartNumber' => 2, 'Size' => 5],
    ]);

    // A tampered signature is refused.
    $sign = $this->getJson("/uploads/{$sessionId}/parts/3/sign")->json('url');
    $this->call('PUT', $sign.'tampered', [], [], [], [], 'x')->assertStatus(403);
});

test('complete assembles parts in order into a verified File record', function () {
    $this->actingAs($this->admin);
    $sessionId = createSession(11, 'assembled.txt');

    putPart($sessionId, 1, 'hello-');
    putPart($sessionId, 2, 'world');

    $response = $this->postJson("/uploads/{$sessionId}/complete")->assertOk();

    $file = File::query()->findOrFail($response->json('file_id'));

    expect($file->original_name)->toBe('assembled.txt')
        ->and($file->size)->toBe(11)
        ->and($file->checksum)->toBe(hash('sha256', 'hello-world'))
        ->and(Storage::disk('files')->get($file->path))->toBe('hello-world')
        ->and(UploadSession::query()->find($sessionId))->toBeNull()
        ->and(is_dir(storage_path('app/uploads-tmp/'.$sessionId)))->toBeFalse()
        ->and(ActivityLog::query()->where('action', Action::FileUploaded)->where('subject_name', 'assembled')->exists())->toBeTrue();
});

test('complete with a missing middle part fails and creates nothing', function () {
    $this->actingAs($this->admin);
    $sessionId = createSession();

    putPart($sessionId, 1, 'aaa');
    putPart($sessionId, 3, 'ccc');

    $this->postJson("/uploads/{$sessionId}/complete")->assertStatus(422);

    expect(File::query()->count())->toBe(0)
        ->and(UploadSession::query()->find($sessionId))->not->toBeNull();
});

test('sessions are private to their owner', function () {
    $this->actingAs($this->admin);
    $sessionId = createSession();

    $other = User::factory()->role(SystemRole::Uploader)->create();
    $this->actingAs($other);

    $this->getJson("/uploads/{$sessionId}/parts/1/sign")->assertNotFound();
    $this->getJson("/uploads/{$sessionId}/parts")->assertNotFound();
    $this->postJson("/uploads/{$sessionId}/complete")->assertNotFound();
    $this->deleteJson("/uploads/{$sessionId}")->assertNotFound();
});

test('a client without the upload permission cannot create sessions', function () {
    // The default Client role now includes upload (SystemRole::Client) —
    // revoke it from this client's own role rather than relying on a
    // plain factory client lacking it.
    $client = User::factory()->client()->create();
    RolePermission::query()->where('role_id', $client->role_id)->where('permission', Permission::Upload->value)->delete();

    $this->actingAs($client);
    $this->postJson('/uploads', ['filename' => 'x.zip', 'size' => 10])->assertForbidden();
});

test('a staff account without the upload permission cannot create sessions', function () {
    $staff = User::factory()->role(SystemRole::AccountManager)->create();
    RolePermission::query()->where('role_id', $staff->role_id)->where('permission', Permission::Upload->value)->delete();

    $this->actingAs($staff);
    $this->postJson('/uploads', ['filename' => 'x.zip', 'size' => 10])->assertForbidden();
});

test('a client with the upload permission can create a session and complete an upload', function () {
    $client = User::factory()->client()->create();
    grantChunkedUploadPermission($client);

    $this->actingAs($client);
    $sessionId = createSession(11, 'client-upload.txt');

    putPart($sessionId, 1, 'hello-');
    putPart($sessionId, 2, 'world');

    $response = $this->postJson("/uploads/{$sessionId}/complete")->assertOk();

    $file = File::query()->findOrFail($response->json('file_id'));
    expect($file->uploaded_by)->toBe($client->id);
});

test('a chunked session stays private to its owner across staff and client actors', function () {
    $this->actingAs($this->admin);
    $staffSession = createSession();

    $client = User::factory()->client()->create();
    grantChunkedUploadPermission($client);
    $this->actingAs($client);

    $this->getJson("/uploads/{$staffSession}/parts")->assertNotFound();
    $this->postJson("/uploads/{$staffSession}/complete")->assertNotFound();

    $clientSession = createSession(11, 'client-owned.txt');

    $this->actingAs($this->admin);
    $this->getJson("/uploads/{$clientSession}/parts")->assertNotFound();
    $this->postJson("/uploads/{$clientSession}/complete")->assertNotFound();
});

test('abort deletes parts and the purge command clears only stale sessions', function () {
    $this->actingAs($this->admin);

    $aborted = createSession();
    putPart($aborted, 1, 'data');
    $this->deleteJson("/uploads/{$aborted}")->assertNoContent();
    expect(is_dir(storage_path('app/uploads-tmp/'.$aborted)))->toBeFalse()
        ->and(UploadSession::query()->find($aborted))->toBeNull();

    $fresh = createSession(100, 'fresh.zip');
    $stale = createSession(100, 'stale.zip');
    UploadSession::query()->whereKey($stale)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('projectsend:purge-stale-uploads')->assertSuccessful();

    expect(UploadSession::query()->find($fresh))->not->toBeNull()
        ->and(UploadSession::query()->find($stale))->toBeNull();
});

// The quota is only checkable at complete(), against the assembled size —
// so without a per-part bound a session can absorb unlimited bytes that
// never become a File row and never count against anything.
test('an oversized upload part is rejected and leaves nothing on disk', function () {
    // Keep the test cheap: the limit is twice the configured part size, so
    // 1 MB here means a ~2 MB body is enough to cross it.
    config(['projectsend.upload_part_size_mb' => 1]);

    $user = User::factory()->create();

    $session = $this->actingAs($user)->postJson('/uploads', [
        'filename' => 'big.pdf',
        'size' => 1024,
        'type' => 'application/pdf',
    ])->assertOk()->json('uploadId');

    $url = $this->actingAs($user)->getJson("/uploads/{$session}/parts/1/sign")->assertOk()->json('url');

    $this->actingAs($user)
        ->call('PUT', $url, [], [], [], [], str_repeat('x', 2 * 1024 * 1024 + 1024))
        ->assertStatus(413);

    // Refused, not truncated — a partial part would assemble into a
    // silently corrupt file.
    expect($this->actingAs($user)->getJson("/uploads/{$session}/parts")->json())->toBe([]);
});

test('a part within the size limit is still accepted', function () {
    config(['projectsend.upload_part_size_mb' => 1]);

    $user = User::factory()->create();

    $session = $this->actingAs($user)->postJson('/uploads', [
        'filename' => 'ok.pdf',
        'size' => 1024,
        'type' => 'application/pdf',
    ])->assertOk()->json('uploadId');

    $url = $this->actingAs($user)->getJson("/uploads/{$session}/parts/1/sign")->assertOk()->json('url');

    $this->actingAs($user)
        ->call('PUT', $url, [], [], [], [], str_repeat('x', 512 * 1024))
        ->assertOk();

    expect($this->actingAs($user)->getJson("/uploads/{$session}/parts")->json())->toHaveCount(1);
});
