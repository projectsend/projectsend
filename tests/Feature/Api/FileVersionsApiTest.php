<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
    $this->token = $this->admin->createToken('t', [
        Permission::Upload->value,
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ])->plainTextToken;
});

test('the resource reports both ends of a version link', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    $this->versions->link($revision, $original, $this->admin);

    $response = $this->withToken($this->token)->getJson("/api/v1/files/{$revision->id}");

    $response->assertOk();

    expect($response->json('data.is_revision'))->toBeTrue()
        ->and($response->json('data.sharing_root_id'))->toBe($original->id)
        ->and($response->json('data.previous_version.name'))->toBe('Rev C')
        ->and($response->json('data.next_version'))->toBeNull();
});

test('the resource reports an ordinary file as not a revision', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $response = $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}");

    expect($response->json('data.is_revision'))->toBeFalse()
        ->and($response->json('data.sharing_root_id'))->toBeNull()
        ->and($response->json('data.previous_version'))->toBeNull();
});

test('the index carries the version fields too', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    $this->versions->link($revision, $original, $this->admin);

    $response = $this->withToken($this->token)->getJson('/api/v1/files');

    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('name', 'Rev D');

    expect($row['previous_version']['name'])->toBe('Rev C');
});

test('a token can link and unlink a version', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)
        ->putJson("/api/v1/files/{$revision->id}/version", ['previous_file_id' => $original->id])
        ->assertOk();

    expect($revision->fresh()->previous_file_id)->toBe($original->id);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/files/{$revision->id}/version")
        ->assertOk();

    expect($revision->fresh()->previous_file_id)->toBeNull();
});

test('re-submitting an existing link is a no-op, not a 404', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    // An integration retrying a request that already succeeded must not be
    // told the original does not exist. Caught by a live API smoke test,
    // not by the suite: link() is idempotent, but the candidate lookup in
    // front of it was excluding the file's own current original.
    $this->withToken($this->token)
        ->putJson("/api/v1/files/{$revision->id}/version", ['previous_file_id' => $original->id])
        ->assertOk();

    expect($revision->fresh()->previous_file_id)->toBe($original->id);
});

test('a token without edit permissions cannot link a version', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $readOnly = $this->admin->createToken('r', [Permission::Upload->value])->plainTextToken;

    $this->withToken($readOnly)
        ->putJson("/api/v1/files/{$revision->id}/version", ['previous_file_id' => $original->id])
        ->assertForbidden();
});

test('an out-of-scope original 404s rather than confirming it exists', function () {
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    $staffer = User::factory()->role(SystemRole::ClientManager)->create();
    $staffer->assignedClients()->attach($mine->id);
    $token = $staffer->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $subject = File::factory()->create(['uploaded_by' => $staffer->id]);
    shareFileWith($subject, $mine);

    $outOfScope = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($outOfScope, $theirs);

    $this->withToken($token)
        ->putJson("/api/v1/files/{$subject->id}/version", ['previous_file_id' => $outOfScope->id])
        ->assertNotFound();
});

test('linking rejects an original that already has a revision', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $taken = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($taken, $original, $this->admin);

    // Excluded by the candidate rule, so it never reaches link().
    $this->withToken($this->token)
        ->putJson("/api/v1/files/{$revision->id}/version", ['previous_file_id' => $original->id])
        ->assertNotFound();
});

test('assigning a client to a revision is refused and names the original', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    $response = $this->withToken($this->token)
        ->postJson("/api/v1/files/{$revision->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $response->assertStatus(422);

    expect(json_encode($response->json()))->toContain('Rev C')
        ->and(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(0);
});

test('linking through the api moves recipients onto the original', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($revision, $client);

    $this->withToken($this->token)
        ->putJson("/api/v1/files/{$revision->id}/version", ['previous_file_id' => $original->id])
        ->assertOk();

    expect(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(0)
        ->and(FileAssignment::query()->where('file_id', $original->id)->count())->toBe(1)
        // …and the client still reaches the file, now by inheritance.
        ->and(File::query()->whereKey($revision->id)->visibleToClient($client)->exists())->toBeTrue();
});
