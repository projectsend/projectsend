<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('t', [
        Permission::Upload->value,
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
        Permission::DeleteFiles->value,
        Permission::DeleteOthersFiles->value,
        Permission::CreateOwnFolders->value,
        Permission::UploadPublic->value,
    ])->plainTextToken;
});

/** A token for a staff member whose role holds exactly these permissions. */
function folderApiToken(array $permissions): string
{
    $user = staffWithPermissions(array_map(fn (Permission $p): string => $p->value, $permissions));

    return $user->createToken('t', array_map(fn (Permission $p): string => $p->value, $permissions))->plainTextToken;
}

/** A client manager scoped to one client, and that client. */
function scopedFolderManager(): array
{
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    return [$manager, $client];
}

test('folders list with their place in the tree', function () {
    $clients = makeFolder('Clients');
    $acme = makeFolder('Acme', $clients);
    $year = makeFolder('2026', $acme);

    $rows = collect($this->withToken($this->token)->getJson('/api/v1/folders')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$year->id]['parent_id'])->toBe($acme->id)
        ->and($rows[$year->id]['path'])->toBe('Clients / Acme / 2026')
        ->and($rows[$year->id]['ancestors'])->toBe([
            ['id' => $clients->id, 'name' => 'Clients'],
            ['id' => $acme->id, 'name' => 'Acme'],
        ])
        ->and($rows[$clients->id]['ancestors'])->toBe([])
        ->and($rows[$clients->id]['path'])->toBe('Clients');
});

test('filters narrow the listing', function () {
    $top = makeFolder('Projects');
    $child = makeFolder('Invoices', $top);
    $other = makeFolder('Archive');

    $ids = fn (string $query) => $this->withToken($this->token)->getJson("/api/v1/folders?{$query}")->assertOk()->json('data.*.id');

    expect($ids("parent_id={$top->id}"))->toBe([$child->id])
        ->and($ids('top_level=1'))->toEqualCanonicalizing([$top->id, $other->id])
        ->and($ids('search=voice'))->toBe([$child->id]);
});

test('polling with updated_since returns what changed, oldest first', function () {
    $this->travelTo(now()->subDay());
    $old = makeFolder('Old');
    $this->travelBack();

    $since = now()->subMinute()->toIso8601String();
    $new = makeFolder('New');

    $ids = $this->withToken($this->token)
        ->getJson('/api/v1/folders?updated_since='.urlencode($since))
        ->assertOk()->json('data.*.id');

    expect($ids)->toBe([$new->id])->not->toContain($old->id);
});

test('a token without a file ability cannot list folders', function () {
    $token = folderApiToken([Permission::ViewNews]);

    $this->withToken($token)->getJson('/api/v1/folders')->assertForbidden();
});

/*
 * The listing is the library screen's own scope, and the trail above a
 * folder must not name folders the caller cannot reach.
 */
test('a client-scoped token sees only its folders, and not the names above them', function () {
    [$manager, $client] = scopedFolderManager();

    $secret = makeFolder('Board minutes');
    $shared = makeFolder('Acme', $secret);
    $unrelated = makeFolder('Somebody else');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $token = $manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $rows = collect($this->withToken($token)->getJson('/api/v1/folders')->assertOk()->json('data'))->keyBy('id');

    expect($rows->keys()->all())->toContain($shared->id)
        ->not->toContain($unrelated->id)
        ->not->toContain($secret->id)
        ->and($rows[$shared->id]['ancestors'])->toBe([])
        ->and($rows[$shared->id]['path'])->toBe('Acme');

    $this->withToken($token)->getJson("/api/v1/folders/{$unrelated->id}")->assertForbidden();
    $this->withToken($token)->getJson("/api/v1/folders/{$shared->id}")->assertOk()->assertJsonPath('data.path', 'Acme');
});

test('an unscoped token sees the whole trail of the same folder', function () {
    $secret = makeFolder('Board minutes');
    $shared = makeFolder('Acme', $secret);

    $this->withToken($this->token)->getJson("/api/v1/folders/{$shared->id}")
        ->assertOk()
        ->assertJsonPath('data.path', 'Board minutes / Acme');
});

test('a folder can be created at the top or inside another', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/folders', ['name' => 'Clients'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Clients')
        ->assertJsonPath('data.parent_id', null);

    $parentId = $response->json('data.id');

    $this->withToken($this->token)->postJson('/api/v1/folders', ['name' => 'Acme', 'parent_id' => $parentId])
        ->assertStatus(201)
        ->assertJsonPath('data.parent_id', $parentId)
        ->assertJsonPath('data.path', 'Clients / Acme');

    $folder = Folder::query()->where('name', 'Acme')->firstOrFail();
    expect($folder->created_by)->toBe($this->admin->id)
        ->and(ActivityLog::query()->where('action', Action::FolderCreated)->count())->toBe(2);
});

test('creating a folder that already exists returns it instead of a second one', function () {
    $parent = makeFolder('Clients');
    $existing = makeFolder('Acme', $parent);

    $this->withToken($this->token)->postJson('/api/v1/folders', ['name' => 'Acme', 'parent_id' => $parent->id])
        ->assertStatus(200)
        ->assertJsonPath('data.id', $existing->id);

    // Same name somewhere else is a different folder.
    $this->withToken($this->token)->postJson('/api/v1/folders', ['name' => 'Acme'])->assertStatus(201);

    expect(Folder::query()->where('name', 'Acme')->count())->toBe(2);
});

test('creating needs upload as well as create_own_folders', function () {
    $token = folderApiToken([Permission::CreateOwnFolders]);

    $this->withToken($token)->postJson('/api/v1/folders', ['name' => 'Nope'])->assertForbidden();

    expect(Folder::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('a folder cannot be created inside a public folder without upload_public', function () {
    $public = makeFolder('Press kit');
    $public->update(['public' => true]);

    $token = folderApiToken([Permission::CreateOwnFolders, Permission::Upload, Permission::EditOthersFiles]);

    $this->withToken($token)->postJson('/api/v1/folders', ['name' => 'Drafts', 'parent_id' => $public->id])->assertForbidden();

    expect(Folder::query()->where('name', 'Drafts')->exists())->toBeFalse();
});

test('a client-scoped token cannot create inside a folder it cannot see', function () {
    [$manager] = scopedFolderManager();
    $hidden = makeFolder('Somebody else');

    $token = $manager->createToken('t', [Permission::CreateOwnFolders->value, Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/folders', ['name' => 'Sneaky', 'parent_id' => $hidden->id])->assertNotFound();

    expect(Folder::query()->where('name', 'Sneaky')->exists())->toBeFalse();
});

test('the depth cap applies', function () {
    $parent = null;

    // The deepest folder allowed: one more level is refused.
    for ($i = 0; $i < Folder::MAX_DEPTH; $i++) {
        $parent = makeFolder("Level {$i}", $parent);
    }

    $this->withToken($this->token)->postJson('/api/v1/folders', ['name' => 'Too deep', 'parent_id' => $parent?->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});

test('a folder can be renamed and moved, carrying its subtree', function () {
    $from = makeFolder('From');
    $to = makeFolder('To');
    $folder = makeFolder('Acme', $from);
    $child = makeFolder('2026', $folder);

    $this->withToken($this->token)->patchJson("/api/v1/folders/{$folder->id}", ['name' => 'Acme Inc', 'parent_id' => $to->id])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Inc')
        ->assertJsonPath('data.parent_id', $to->id)
        ->assertJsonPath('data.path', 'To / Acme Inc');

    $this->withToken($this->token)->getJson("/api/v1/folders/{$child->id}")
        ->assertJsonPath('data.path', 'To / Acme Inc / 2026');

    $this->withToken($this->token)->patchJson("/api/v1/folders/{$folder->id}", ['parent_id' => null])
        ->assertOk()
        ->assertJsonPath('data.parent_id', null);

    expect(ActivityLog::query()->where('action', Action::FolderRenamed)->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', Action::FolderMoved)->count())->toBe(2);
});

test('only the fields sent change', function () {
    $parent = makeFolder('Parent');
    $folder = makeFolder('Acme', $parent);

    $this->withToken($this->token)->patchJson("/api/v1/folders/{$folder->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('a folder cannot be moved into itself or below itself', function () {
    $folder = makeFolder('Acme');
    $child = makeFolder('2026', $folder);

    $this->withToken($this->token)->patchJson("/api/v1/folders/{$folder->id}", ['parent_id' => $child->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});

test('a folder cannot be moved into a public folder without upload_public', function () {
    $public = makeFolder('Press kit');
    $public->update(['public' => true]);
    $folder = makeFolder('Private drafts');

    $token = folderApiToken([Permission::Upload, Permission::EditFiles, Permission::EditOthersFiles]);

    $this->withToken($token)->patchJson("/api/v1/folders/{$folder->id}", ['parent_id' => $public->id])->assertForbidden();

    expect($folder->fresh()?->parent_id)->toBeNull();
});

test('an empty folder is deleted', function () {
    $folder = makeFolder('Empty');

    $this->withToken($this->token)->deleteJson("/api/v1/folders/{$folder->id}")->assertNoContent();

    expect(Folder::query()->whereKey($folder->id)->exists())->toBeFalse();
});

test('a folder with content is refused unless the cascade is asked for', function () {
    $folder = makeFolder('Acme');
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $folder->id]);

    $this->withToken($this->token)->deleteJson("/api/v1/folders/{$folder->id}")
        ->assertStatus(409)
        ->assertJsonPath('type', 'conflict');

    expect(Folder::query()->whereKey($folder->id)->exists())->toBeTrue()
        ->and(File::query()->whereKey($file->id)->exists())->toBeTrue();

    $this->withToken($this->token)->deleteJson("/api/v1/folders/{$folder->id}", ['content_action' => 'cascade_delete'])
        ->assertNoContent();

    expect(Folder::query()->whereKey($folder->id)->exists())->toBeFalse()
        ->and(File::query()->whereKey($file->id)->exists())->toBeFalse();
});

test('a folder holding only a subfolder counts as not empty', function () {
    $folder = makeFolder('Acme');
    makeFolder('2026', $folder);

    $this->withToken($this->token)->deleteJson("/api/v1/folders/{$folder->id}")->assertStatus(409);
});

test('the cascade is refused when it would take a file the token may not delete', function () {
    $staff = staffWithPermissions([
        Permission::Upload->value, Permission::EditFiles->value,
        Permission::DeleteFiles->value, Permission::CreateOwnFolders->value,
    ]);
    $token = $staff->createToken('t', [Permission::DeleteFiles->value])->plainTextToken;

    $folder = Folder::query()->create(['name' => 'Reports', 'created_by' => $staff->id]);
    $foreign = File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $folder->id]);

    $this->withToken($token)->deleteJson("/api/v1/folders/{$folder->id}", ['content_action' => 'cascade_delete'])
        ->assertForbidden();

    expect(Folder::query()->whereKey($folder->id)->exists())->toBeTrue()
        ->and(File::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('a folder can be shared with a client and unshared', function () {
    $folder = makeFolder('Acme');
    $client = User::factory()->client()->create();

    $this->withToken($this->token)->postJson("/api/v1/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertOk()
        ->assertJsonPath('data.assignments.0.type', 'client')
        ->assertJsonPath('data.assignments.0.id', $client->id);

    // Again: still one share.
    $this->withToken($this->token)->postJson("/api/v1/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertOk();

    expect(FolderAssignment::query()->where('folder_id', $folder->id)->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', Action::FolderShared)->exists())->toBeTrue();

    $this->withToken($this->token)->deleteJson("/api/v1/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertOk()
        ->assertJsonPath('data.assignments', []);

    expect(FolderAssignment::query()->where('folder_id', $folder->id)->exists())->toBeFalse();
});

test('a client-scoped token cannot share with somebody else\'s client', function () {
    [$manager] = scopedFolderManager();
    $stranger = User::factory()->client()->create();
    $folder = Folder::query()->create(['name' => 'Mine', 'created_by' => $manager->id]);

    $token = $manager->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $stranger->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id');

    expect(FolderAssignment::query()->where('folder_id', $folder->id)->exists())->toBeFalse();
});

test('a file reports its folder\'s parent', function () {
    $parent = makeFolder('Clients');
    $folder = makeFolder('Acme', $parent);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $folder->id]);

    $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}")
        ->assertOk()
        ->assertJsonPath('data.folder.parent_id', $parent->id);
});
