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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Where this worker's parts live. Not storage_path('app/uploads-tmp')
 * directly: each parallel worker gets its own root (see Tests\TestCase),
 * because session ids restart at 1 in every worker's database and the
 * cleanup below would otherwise delete the others' parts mid-test.
 */
function partsRoot(): string
{
    return (string) config('projectsend.uploads.parts_path');
}

function grantChunkedUploadPermission(User $user): void
{
    RolePermission::query()->firstOrCreate(['role_id' => $user->role_id, 'permission' => Permission::Upload->value]);
}

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

afterEach(function () {
    Illuminate\Support\Facades\File::deleteDirectory(partsRoot());
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
        ->and(is_dir(partsRoot().'/'.$sessionId))->toBeFalse()
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

test('complete refuses a file whose real assembled size exceeds the limit, even when a tiny size was declared', function () {
    $this->actingAs($this->admin);

    // A 1 MB cap. The session is declared as a single byte, so it sails
    // through store()'s check against the client-supplied size.
    app(Settings::class)->set(Setting::MaxFileSizeMb, 1);
    $sessionId = createSession(1, 'sneaky.zip');

    // But the real parts stream ~1.5 MB.
    $chunk = str_repeat('a', 800 * 1024);
    putPart($sessionId, 1, $chunk)->assertOk();
    putPart($sessionId, 2, $chunk)->assertOk();

    $this->postJson("/uploads/{$sessionId}/complete")->assertStatus(422);

    // Nothing is kept: no File row, the assembled bytes are removed, and the
    // session is gone.
    expect(File::query()->count())->toBe(0)
        ->and(UploadSession::query()->find($sessionId))->toBeNull()
        ->and(Storage::disk('files')->allFiles())->toBe([]);
});

test('complete still accepts a file at exactly the limit', function () {
    $this->actingAs($this->admin);

    app(Settings::class)->set(Setting::MaxFileSizeMb, 1);
    $sessionId = createSession(1024 * 1024, 'exact.zip');

    putPart($sessionId, 1, str_repeat('a', 1024 * 1024))->assertOk();

    $response = $this->postJson("/uploads/{$sessionId}/complete")->assertOk();

    expect(File::query()->findOrFail($response->json('file_id'))->size)->toBe(1024 * 1024);
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
    expect(is_dir(partsRoot().'/'.$aborted))->toBeFalse()
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

test('a storage backend that refuses the write fails the upload instead of recording a phantom file', function () {
    // Found against a real GCS bucket, not in a test: the disks are
    // configured with 'throw' => false, so a refused write returns false
    // rather than raising. The assembled bytes were dropped, the upload
    // reported success, and a File row was created pointing at an object
    // that had never been stored — an upload that silently disappears is
    // worse than one that fails.
    $this->actingAs($this->admin);
    $sessionId = createSession(11, 'assembled.txt');

    putPart($sessionId, 1, 'hello-');
    putPart($sessionId, 2, 'world');

    $refusing = Mockery::mock(Illuminate\Contracts\Filesystem\Filesystem::class);
    $refusing->shouldReceive('writeStream')->once()->andReturnFalse();
    Storage::set('files', $refusing);

    $before = File::query()->count();

    // The controller turns a RuntimeException from the assemble step into
    // a validation error on `parts`, so the person uploading is told what
    // went wrong instead of meeting a 500.
    $this->postJson("/uploads/{$sessionId}/complete")
        ->assertStatus(422)
        ->assertJsonPath('errors.parts.0', fn (string $message): bool => str_contains($message, 'Could not write the assembled upload'));

    // The point of the whole test: no row for bytes that were never stored.
    expect(File::query()->count())->toBe($before);
});

// A chunked upload is two requests, and store()'s rule only ever sees the
// first one. Delete the folder while the bytes are in flight and the
// session still names it -- the version of this that nobody can ask for
// in a single request.
test('a folder deleted mid-upload does not swallow the finished file', function () {
    $folder = app(\App\Modules\Files\Folders\FolderService::class)->create('Doomed', null);

    $this->actingAs($this->admin);

    $session = $this->postJson('/uploads', [
        'filename' => 'report.pdf',
        'size' => 11,
        'type' => 'application/pdf',
        'folder_id' => $folder->id,
    ])->assertOk()->json('uploadId');

    putPart($session, 1, 'hello world')->assertOk();

    // Somebody empties the folder while the transfer is running. Deleting
    // a folder deletes every file in its subtree, so anything filed into
    // it afterwards sits inside a folder that was already emptied.
    app(\App\Modules\Files\Folders\FolderService::class)->delete($folder);

    $fileId = $this->postJson("/uploads/{$session}/complete")->assertOk()->json('file_id');

    // The bytes are kept -- they are already uploaded, and discarding
    // somebody's finished transfer over a folder that vanished under them
    // is the harsher surprise. They land at the root, where the uploader
    // can see them and move them.
    expect(File::query()->whereKey($fileId)->value('folder_id'))->toBeNull();
});

test('a folder that survives the upload still receives the file', function () {
    $folder = app(\App\Modules\Files\Folders\FolderService::class)->create('Fine', null);

    $this->actingAs($this->admin);

    $session = $this->postJson('/uploads', [
        'filename' => 'report.pdf',
        'size' => 11,
        'type' => 'application/pdf',
        'folder_id' => $folder->id,
    ])->assertOk()->json('uploadId');

    putPart($session, 1, 'hello world')->assertOk();

    $fileId = $this->postJson("/uploads/{$session}/complete")->assertOk()->json('file_id');

    expect(File::query()->whereKey($fileId)->value('folder_id'))->toBe($folder->id);
});

// The isolation itself cannot be observed from inside one test, but the
// mechanism it rests on can: parts go where the configured root says, so
// giving each worker its own root actually holds them apart.
test('parts are written under the configured root', function () {
    $this->actingAs($this->admin);

    $custom = storage_path('app/uploads-tmp/somewhere-else');
    config(['projectsend.uploads.parts_path' => $custom]);

    $sessionId = createSession(1024);
    putPart($sessionId, 1, 'hello')->assertOk();

    expect(is_file($custom.'/'.$sessionId.'/1.part'))->toBeTrue()
        ->and(is_dir(storage_path('app/uploads-tmp/'.$sessionId)))->toBeFalse();

    Illuminate\Support\Facades\File::deleteDirectory($custom);
});

test('a second complete is refused while one is already finalising the session', function () {
    $this->actingAs($this->admin);
    $sessionId = createSession(11, 'locked.txt');
    putPart($sessionId, 1, 'hello-');
    putPart($sessionId, 2, 'world');

    // Stand in for a completion already in flight by holding the session's
    // lock — a concurrent complete must not assemble the same target file a
    // second time or create a second File row.
    $lock = Cache::lock('upload-complete:'.$sessionId, 120);
    expect($lock->get())->toBeTrue();

    $this->postJson("/uploads/{$sessionId}/complete")->assertStatus(422);

    expect(File::query()->count())->toBe(0)
        ->and(UploadSession::query()->find($sessionId))->not->toBeNull();

    // Once the in-flight completion releases the lock, completing works.
    $lock->release();
    $this->postJson("/uploads/{$sessionId}/complete")->assertOk();
    expect(File::query()->count())->toBe(1);
});
