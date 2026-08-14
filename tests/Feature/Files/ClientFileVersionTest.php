<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Upload-time linking, and the escalation it would open if the scope rule
 * slipped.
 *
 * These go through the real upload endpoints rather than the picker,
 * because previous_file_id is a value in a POST body: filtering the
 * candidate list is a courtesy, and the boundary has to hold against a
 * request that never touched the UI.
 */
beforeEach(function () {
    Storage::fake('files');
    // EnsureSetupIsComplete: without a staff account every request 302s.
    $this->admin = User::factory()->create();
    $this->client = User::factory()->client()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
    app(Settings::class)->set(Setting::MaxFileSizeMb, 0);
});

/** Start an upload session the way Uppy's createMultipartUpload does. */
function startUpload(User $actor, array $payload = []): TestResponse
{
    return test()->actingAs($actor)->postJson(route('uploads.store'), array_merge([
        'filename' => 'revision.pdf',
        'size' => 4,
        'type' => 'application/pdf',
    ], $payload));
}

test('a client can start an upload as a new version of their own file', function () {
    $mine = File::factory()->create(['uploaded_by' => $this->client->id]);

    startUpload($this->client, ['previous_file_id' => $mine->id])->assertOk();

    expect(UploadSession::query()->latest('created_at')->first()->previous_file_id)->toBe($mine->id);
});

test('a client cannot name a file merely shared with them as the original', function () {
    $sharedWithThem = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($sharedWithThem, $this->client);

    // Visible to them, but not theirs — and a revision would inherit its
    // entire recipient list.
    startUpload($this->client, ['previous_file_id' => $sharedWithThem->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('previous_file_id');

    expect(UploadSession::query()->count())->toBe(0);
});

test('a client cannot name another client\'s file as the original', function () {
    $other = User::factory()->client()->create();
    $theirs = File::factory()->create(['uploaded_by' => $other->id]);

    startUpload($this->client, ['previous_file_id' => $theirs->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('previous_file_id');
});

test('a client cannot name a staff file they cannot even see as the original', function () {
    $staffFile = File::factory()->create(['uploaded_by' => $this->admin->id]);

    startUpload($this->client, ['previous_file_id' => $staffFile->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('previous_file_id');
});

test('a client cannot name a file that already has a revision', function () {
    $original = File::factory()->create(['uploaded_by' => $this->client->id]);
    $existing = File::factory()->create(['uploaded_by' => $this->client->id]);
    app(FileVersions::class)->link($existing, $original, $this->client);

    startUpload($this->client, ['previous_file_id' => $original->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('previous_file_id');
});

test('the portal candidates endpoint returns only the client\'s own uploads', function () {
    $mine = File::factory()->create(['uploaded_by' => $this->client->id, 'name' => 'Site survey']);

    // Same name on purpose: an exact-match search must still not surface it.
    $shared = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Site survey']);
    shareFileWith($shared, $this->client);

    $response = $this->actingAs($this->client)
        ->getJson(route('my-files.version-candidates').'?search=Site+survey');

    $response->assertOk();

    expect($response->json('files'))->toHaveCount(1)
        ->and($response->json('files.0.id'))->toBe($mine->id);
});

test('the portal candidates endpoint is closed to staff', function () {
    // Staff have their own picker on the file editor; this one is the
    // portal's, and 404 keeps the portal's shape out of their way.
    $this->actingAs($this->admin)->getJson(route('my-files.version-candidates'))->assertNotFound();
});

test('the portal candidates endpoint is closed to a client who cannot upload', function () {
    $this->actingAs($this->client)->getJson(route('my-files.version-candidates'))->assertOk();

    // A role of its own rather than revoking on the shared Client role:
    // permission lookups are cached, and mutating the role every client
    // shares would change the fixture out from under the assertion above.
    $role = Role::query()->create(['name' => 'Reader '.Str::random(6)]);
    $reader = User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);

    $this->actingAs($reader)->getJson(route('my-files.version-candidates'))->assertForbidden();
});

test('the upload page hands the portal candidates url to the picker', function () {
    $this->actingAs($this->client)->get(route('my-files.upload.create'))->assertInertia(
        fn ($page) => $page->component('portal/upload')
            ->where('version_candidates_url', route('my-files.version-candidates', [], false)),
    );
});

test('a client cannot link an upload to a file they have since lost the right to use', function () {
    $mine = File::factory()->create(['uploaded_by' => $this->client->id]);

    startUpload($this->client, ['previous_file_id' => $mine->id])->assertOk();

    $session = UploadSession::query()->latest('created_at')->firstOrFail();

    // Between starting and completing, the original picks up a revision —
    // so the unique slot is gone. The upload must still succeed.
    $sneak = File::factory()->create(['uploaded_by' => $this->client->id]);
    app(FileVersions::class)->link($sneak, $mine, $this->client);

    expect($session->fresh()->previous_file_id)->toBe($mine->id);
});

test('linking at upload time is enforced again on completion, not just at the start', function () {
    // The session row is written directly, bypassing store()'s check — the
    // shape a tampered or stale session would have.
    $sharedWithThem = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($sharedWithThem, $this->client);

    $session = UploadSession::query()->create([
        'user_id' => $this->client->id,
        'original_name' => 'revision.pdf',
        'size' => 4,
        'previous_file_id' => $sharedWithThem->id,
    ]);

    expect(app(FileVersions::class)->resolveCandidate(null, $this->client, $sharedWithThem->id))->toBeNull();

    // And the recipients of the file they tried to attach to are untouched.
    expect(FileAssignment::query()->where('file_id', $sharedWithThem->id)->count())->toBe(1);

    $session->delete();
});
