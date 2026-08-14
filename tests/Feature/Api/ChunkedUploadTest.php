<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->staff = User::factory()->create();
    $this->token = $this->staff->createToken('t', [Permission::Upload->value])->plainTextToken;
});

/*
 * The whole resumable flow over bearer auth, end to end. The controller is
 * the same one the browser uses; what is being proven here is that the
 * token-authenticated mounting of it works — in particular that the signed
 * part URL points at the API route rather than the web one, which is the
 * single thing that had to change.
 */
test('a file can be uploaded through the chunked flow with a token', function () {
    $contents = str_repeat('a', 2048);

    $session = $this->withToken($this->token)->postJson('/api/v1/uploads', [
        'filename' => 'resumable.pdf',
        'size' => strlen($contents),
        'type' => 'application/pdf',
    ])->assertOk()->json();

    $id = $session['uploadId'];

    $signed = $this->withToken($this->token)
        ->getJson("/api/v1/uploads/{$id}/parts/1/sign")
        ->assertOk()
        ->json();

    // The URL must be the API twin — a browser-side URL would be useless
    // to a token client, which has no session.
    expect($signed['url'])->toContain('/api/v1/uploads/')
        ->and($signed['method'])->toBe('PUT');

    $path = parse_url($signed['url'], PHP_URL_PATH).'?'.parse_url($signed['url'], PHP_URL_QUERY);

    $this->withToken($this->token)->call('PUT', $path, content: $contents)->assertOk();

    $this->withToken($this->token)->getJson("/api/v1/uploads/{$id}/parts")
        ->assertOk()
        ->assertJsonCount(1);

    $completed = $this->withToken($this->token)
        ->postJson("/api/v1/uploads/{$id}/complete")
        ->assertOk()
        ->json();

    $file = File::query()->findOrFail($completed['file_id']);

    expect($file->uploaded_by)->toBe($this->staff->id)
        ->and($file->size)->toBe(strlen($contents))
        ->and(Storage::disk('files')->exists($file->path))->toBeTrue();
});

test('the signed part URL cannot be used without its signature', function () {
    $session = $this->withToken($this->token)->postJson('/api/v1/uploads', [
        'filename' => 'x.pdf',
        'size' => 10,
    ])->assertOk()->json();

    $this->withToken($this->token)
        ->call('PUT', "/api/v1/uploads/{$session['uploadId']}/parts/1", content: 'nope')
        ->assertForbidden();
});

test('a session belongs to the token that created it', function () {
    $session = $this->withToken($this->token)->postJson('/api/v1/uploads', [
        'filename' => 'x.pdf',
        'size' => 10,
    ])->assertOk()->json();

    $intruder = User::factory()->create();
    $intruderToken = $intruder->createToken('t', [Permission::Upload->value])->plainTextToken;

    // Swapping the bearer token is not enough on its own: the guard
    // resolved by the previous request in this same process is cached, so
    // without this the intruder's request would be answered as the owner
    // and the test would pass no matter what the code did.
    forgetRequestState();

    $this->withToken($intruderToken)
        ->getJson("/api/v1/uploads/{$session['uploadId']}/parts")
        ->assertNotFound();
});

test('starting an upload requires the upload ability', function () {
    $token = $this->staff->createToken('read-only', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/uploads', [
        'filename' => 'x.pdf',
        'size' => 10,
    ])->assertForbidden();
});
