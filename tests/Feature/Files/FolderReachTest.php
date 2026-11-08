<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * A folder shares everything in it with everybody it, or a folder above
 * it, is shared with. So putting something in a folder is sharing it.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach (['upload', 'edit_files', 'create_own_folders'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->stranger = User::factory()->client()->create(['name' => 'Stranger Corp']);
    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->rep->assignedClients()->sync([$this->mine->id]);
});

function reachFolder(string $name, User ...$sharedWith): Folder
{
    $folder = makeFolder($name);
    $folder->forceFill(['created_by' => test()->admin->id])->save();

    foreach ($sharedWith as $client) {
        FolderAssignment::query()->create(['folder_id' => $folder->id, 'assignable_type' => $client->getMorphClass(), 'assignable_id' => $client->id]);
    }

    return $folder;
}

function reachFile(User $owner): File
{
    return File::factory()->create([
        'uploaded_by' => $owner->id, 'name' => 'a-file', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
    ]);
}

/*
 * A scoped staff member shares only with their own clients. A folder also
 * shared with somebody else's client was in their library, and moving or
 * uploading a file into it shared the file with that client too.
 */
test('a client-scoped staff member cannot place a file where it reaches a client off their roster', function () {
    $both = reachFolder('Shared with both', $this->mine, $this->stranger);
    $inside = makeFolder('Inside', $both);
    $file = reachFile($this->rep);
    app()->forgetScopedInstances();

    $this->actingAs($this->rep)->patch("/files/{$file->id}/move", ['folder_id' => $both->id])->assertForbidden();
    $this->actingAs($this->rep)->patch("/files/{$file->id}/move", ['folder_id' => $inside->id])->assertForbidden();

    $token = $this->rep->createToken('t', ['upload', 'edit_files'])->plainTextToken;
    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", ['folder_id' => $both->id])->assertForbidden();

    expect($file->refresh()->folder_id)->toBeNull()
        ->and(File::query()->visibleToClient($this->stranger)->whereKey($file->id)->exists())->toBeFalse();
});

test('a client-scoped staff member still places files in a folder that reaches only their own clients', function () {
    $ours = reachFolder('Ours', $this->mine);
    $file = reachFile($this->rep);
    app()->forgetScopedInstances();

    $this->actingAs($this->rep)->patch("/files/{$file->id}/move", ['folder_id' => $ours->id])->assertRedirect();

    expect($file->refresh()->folder_id)->toBe($ours->id);
});

test('an unscoped staff member places files in any folder', function () {
    $both = reachFolder('Shared with both', $this->mine, $this->stranger);
    $file = reachFile($this->admin);

    $this->actingAs($this->admin)->patch("/files/{$file->id}/move", ['folder_id' => $both->id])->assertRedirect();

    expect($file->refresh()->folder_id)->toBe($both->id);
});

/*
 * A client's folder inside a folder staff shared with a group showed
 * everything the client put in it to every member. Clients do not see
 * each other.
 */
test('a client cannot make a folder, or fill one, inside a folder shared with a group', function () {
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    foreach ([$alice, $bob] as $client) {
        foreach (['upload', 'create_own_folders'] as $permission) {
            RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => $permission]);
        }
    }
    $group = Group::query()->create(['name' => 'Everyone']);
    $group->members()->sync([$alice->id, $bob->id]);
    $shared = reachFolder('Group folder');
    FolderAssignment::query()->create(['folder_id' => $shared->id, 'assignable_type' => $group->getMorphClass(), 'assignable_id' => $group->id]);

    $this->actingAs($alice)->post('/my-folders', ['name' => 'Alice private', 'parent_id' => $shared->id])->assertForbidden();
    expect(Folder::query()->where('name', 'Alice private')->exists())->toBeFalse();

    // One made before the rule is still not a place to upload into.
    $old = makeFolder('Made earlier', $shared);
    $old->forceFill(['created_by' => $alice->id])->save();
    expect(Folder::uploadableBy($alice, $old->refresh()))->toBeFalse();
});

test('a client still makes and fills folders in their own space and in a folder shared with them alone', function () {
    $alice = User::factory()->client()->create();
    foreach (['upload', 'create_own_folders'] as $permission) {
        RolePermission::query()->firstOrCreate(['role_id' => $alice->role_id, 'permission' => $permission]);
    }
    $justAlice = reachFolder('Just Alice', $alice);

    $this->actingAs($alice)->post('/my-folders', ['name' => 'Mine inside', 'parent_id' => $justAlice->id])->assertRedirect();

    $made = Folder::query()->where('name', 'Mine inside')->sole();
    expect(Folder::uploadableBy($alice, $made))->toBeTrue();
});
