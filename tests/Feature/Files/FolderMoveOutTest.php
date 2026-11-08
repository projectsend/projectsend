<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Taking something out of a shared folder takes it away from everybody the
 * folder reached. That is unsharing, so it is asked the way unsharing is:
 * a client-scoped staff member may not take it from somebody else's
 * client, and a folder leaves a shared parent only with the rights to
 * unshare every file in it.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach (['upload', 'edit_files', 'edit_others_files', 'create_own_folders'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->stranger = User::factory()->client()->create(['name' => 'Stranger Corp']);
    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->rep->assignedClients()->sync([$this->mine->id]);
});

function moveOutFolder(string $name, ?Folder $parent, User ...$sharedWith): Folder
{
    $folder = makeFolder($name, $parent);
    $folder->forceFill(['created_by' => test()->admin->id])->save();

    foreach ($sharedWith as $client) {
        FolderAssignment::query()->create(['folder_id' => $folder->id, 'assignable_type' => $client->getMorphClass(), 'assignable_id' => $client->id]);
    }

    return $folder;
}

function moveOutFile(User $owner, Folder $folder): File
{
    return File::factory()->create([
        'uploaded_by' => $owner->id, 'folder_id' => $folder->id,
        'name' => 'a-file', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
    ]);
}

test('a client-scoped staff member cannot move a file out from under somebody else\'s client', function () {
    $both = moveOutFolder('Shared with both', null, $this->mine, $this->stranger);
    $file = moveOutFile($this->admin, $both);

    $this->actingAs($this->rep)->patch("/files/{$file->id}/move", ['folder_id' => null])->assertSessionHas('error');
    expect($file->fresh()->folder_id)->toBe($both->id);

    $mineOnly = moveOutFolder('Mine only', null, $this->mine);
    $this->actingAs($this->rep)->patch("/files/{$file->id}/move", ['folder_id' => $mineOnly->id])->assertSessionHas('error');
    $this->actingAs($this->rep)->patch('/files/bulk-edit', [
        'file_ids' => [$file->id], 'folder_action' => 'move', 'folder_id' => null,
        'description_action' => 'no_change', 'expiration_action' => 'no_change', 'category_action' => 'no_change',
    ])->assertSessionHas('error');
    expect($file->fresh()->folder_id)->toBe($both->id);

    $token = $this->rep->createToken('t', ['edit_files', 'edit_others_files'])->plainTextToken;
    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", ['folder_id' => null])->assertForbidden();
    expect($file->fresh()->folder_id)->toBe($both->id);
});

test('moving out of a folder that reaches only their own clients is still allowed', function () {
    $mineOnly = moveOutFolder('Mine only', null, $this->mine);
    $other = moveOutFile($this->admin, $mineOnly);
    $this->actingAs($this->rep)->patch("/files/{$other->id}/move", ['folder_id' => null])->assertSessionMissing('error');
    expect($other->fresh()->folder_id)->toBeNull();
});

test('a client-scoped staff member cannot move a folder out from under somebody else\'s client', function () {
    $both = moveOutFolder('Shared with both', null, $this->mine, $this->stranger);
    $sub = moveOutFolder('Sub', $both);
    $sub->forceFill(['created_by' => $this->rep->id])->save();

    $this->actingAs($this->rep)->patch("/folders/{$sub->id}/move", ['parent_id' => null])->assertSessionHas('error');
    expect($sub->fresh()->parent_id)->toBe($both->id);

    $token = $this->rep->createToken('t', ['edit_files', 'edit_others_files'])->plainTextToken;
    $this->withToken($token)->patchJson("/api/v1/folders/{$sub->id}", ['parent_id' => null])->assertForbidden();
    expect($sub->fresh()->parent_id)->toBe($both->id);
});

test('a folder leaves a shared parent only with the rights to unshare every file in it', function () {
    $owner = staffWithPermissions(['upload', 'edit_files', 'create_own_folders']);
    $colleague = User::factory()->create();
    $shared = moveOutFolder('Shared', null, $this->stranger);
    $sub = moveOutFolder('Team', $shared);
    $sub->forceFill(['created_by' => $owner->id])->save();
    moveOutFile($colleague, $sub);

    $this->actingAs($owner)->patch("/folders/{$sub->id}/move", ['parent_id' => null])->assertSessionHas('error');
    expect($sub->fresh()->parent_id)->toBe($shared->id);

    File::query()->where('folder_id', $sub->id)->forceDelete();
    moveOutFile($owner, $sub);

    $this->actingAs($owner)->patch("/folders/{$sub->id}/move", ['parent_id' => null])->assertSessionMissing('error');
    expect($sub->fresh()->parent_id)->toBeNull();
});
