<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\ShareTargets;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * The core of the feature: a revision is shared with exactly the people the
 * original is shared with, because it holds no recipients of its own and
 * reads the chain root's instead.
 *
 * These are access-control assertions, not convenience ones — every "can
 * this client see it" here is the same query the download route runs.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
    app(Settings::class)->set(Setting::Theme, 'default');
});

test('a client assigned to the original can see the revision', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    expect(File::query()->whereKey($revision->id)->visibleToClient($client)->exists())->toBeTrue()
        // and it got there without a single row of its own
        ->and(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(0);
});

test('a client assigned to the original can see it through a group too', function () {
    $client = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Contractors']);
    $group->members()->attach($client->id);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWithGroup($original, $group);
    $this->versions->link($revision, $original, $this->admin);

    expect(File::query()->whereKey($revision->id)->visibleToClient($client)->exists())->toBeTrue();
});

test('a client with no claim on the original cannot see the revision', function () {
    $stranger = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    expect(File::query()->whereKey($revision->id)->visibleToClient($stranger)->exists())->toBeFalse();
});

test('sharing the original afterwards reaches every revision at once', function () {
    $client = User::factory()->client()->create();

    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    // Shared after the chain existed: inheritance is derived, not copied,
    // so there is nothing to keep in sync.
    shareFileWith($v1, $client);

    $visible = File::query()->visibleToClient($client)->pluck('id')->all();

    expect($visible)->toContain($v1->id)
        ->and($visible)->toContain($v2->id)
        ->and($visible)->toContain($v3->id);
});

test('unsharing the original removes access to every revision at once', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    FileAssignment::query()->where('file_id', $original->id)->delete();

    expect(File::query()->whereKey($revision->id)->visibleToClient($client)->exists())->toBeFalse();
});

test('a revision in a different folder still inherits the original\'s recipients', function () {
    $client = User::factory()->client()->create();
    $folder = makeFolder('Rev D');

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $folder->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    // Folder placement stays per-file — only the recipient list is inherited.
    expect(File::query()->whereKey($revision->id)->visibleToClient($client)->exists())->toBeTrue();
});

test('an expired revision is hidden from the client even though the original is not', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
    ]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    // Expiry is per-file, deliberately: inheriting recipients is not
    // inheriting every other property.
    expect(File::query()->whereKey($original->id)->visibleToClient($client)->exists())->toBeTrue()
        ->and(File::query()->whereKey($revision->id)->visibleToClient($client)->exists())->toBeFalse();
});

test('the client can download an inherited revision', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);
    Storage::disk('files')->put($revision->path, 'contents');

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($client)->get(route('files.download', $revision))->assertOk();
});

test('the sharing panel shows a revision the recipients it inherits', function () {
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $assigned = app(ShareTargets::class)->assigned($revision->fresh());

    // Reading the revision's own rows would show it shared with nobody,
    // which is the opposite of what is true.
    expect($assigned['clients'])->toHaveCount(1)
        ->and($assigned['clients'][0]['name'])->toBe('Acme Ltd');
});

test('staff cannot assign a client to a revision directly', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($this->admin)
        ->from(route('files.edit', $revision))
        ->post(route('files.assignments.store', $revision), ['type' => 'client', 'id' => $client->id])
        ->assertSessionHasErrors('id');

    expect(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(0);
});

test('the api refuses to assign a client to a revision and names the original', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    $token = $this->admin->createToken('t', [
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson(route('api.files.assignments.store', $revision), ['type' => 'client', 'id' => $client->id]);

    $response->assertStatus(422);
    expect(json_encode($response->json()))->toContain('Rev C');
});

test('a revision of a file in a public group is reachable through that group', function () {
    $group = Group::query()->create(['name' => 'Downloads', 'public' => true]);

    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);

    shareFileWithGroup($original, $group);
    $this->versions->link($revision, $original, $this->admin);

    $ids = File::query()->publiclyVisibleForGroup($group)->pluck('id')->all();

    expect($ids)->toContain($original->id)
        ->and($ids)->toContain($revision->id);
});

test('a revision reachable through a public group is not also listed standalone', function () {
    $group = Group::query()->create(['name' => 'Downloads', 'public' => true]);

    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);

    shareFileWithGroup($original, $group);
    $this->versions->link($revision, $original, $this->admin);

    // Otherwise the public front page would show it twice: once loose,
    // once under the group it inherits.
    expect(File::query()->standalonePublic()->pluck('id')->all())
        ->not->toContain($revision->id);
});
