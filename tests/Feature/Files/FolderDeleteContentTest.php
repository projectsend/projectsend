<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deleting a folder cascades to every file in its subtree, and a File's
 * `deleted` hook removes the bytes from disk. So the folder route must
 * not destroy what the file route refuses to hand over.
 *
 * MyFoldersController::destroy already draws this line for clients. These
 * are the staff cases.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function folderDeleteRole(array $permissions, bool $clientScoped = false): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6), 'client_scoped' => $clientScoped]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

function folderDeleteUpload(User $as, string $name, ?int $folderId = null): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name.'.pdf', 4, 'application/pdf'),
        'name' => '',
        'description' => '',
        'folder_id' => $folderId,
    ]);

    return File::query()->latest('id')->firstOrFail();
}

test('a folder is not a way around delete_others_files', function () {
    $staff = folderDeleteRole([
        Permission::CreateOwnFolders, Permission::DeleteFiles,
        Permission::Upload, Permission::EditFiles,
    ]);

    $folder = Folder::query()->create(['name' => 'Reports', 'created_by' => $staff->id]);
    $foreign = folderDeleteUpload($this->admin, 'someone-elses', $folder->id);

    // The file route already refuses this one.
    $this->actingAs($staff)->delete("/files/{$foreign->id}")->assertForbidden();

    $this->actingAs($staff)->delete("/folders/{$folder->id}")->assertRedirect();

    expect(File::query()->whereKey($foreign->id)->exists())->toBeTrue()
        ->and(Folder::query()->whereKey($folder->id)->exists())->toBeTrue();
});

test('the refusal says how many files are in the way', function () {
    $staff = folderDeleteRole([
        Permission::CreateOwnFolders, Permission::DeleteFiles,
        Permission::Upload, Permission::EditFiles,
    ]);

    $folder = Folder::query()->create(['name' => 'Reports', 'created_by' => $staff->id]);
    folderDeleteUpload($this->admin, 'one', $folder->id);
    folderDeleteUpload($this->admin, 'two', $folder->id);

    $this->actingAs($staff)->delete("/folders/{$folder->id}")
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '2 files'));
});

test('a nested file is reached too', function () {
    $staff = folderDeleteRole([
        Permission::CreateOwnFolders, Permission::DeleteFiles,
        Permission::Upload, Permission::EditFiles,
    ]);

    $parent = Folder::query()->create(['name' => 'Parent', 'created_by' => $staff->id]);
    $child = Folder::query()->create([
        'name' => 'Child', 'parent_id' => $parent->id,
        'path' => "/{$parent->id}/", 'created_by' => $staff->id,
    ]);
    $foreign = folderDeleteUpload($this->admin, 'deep', $child->id);

    $this->actingAs($staff)->delete("/folders/{$parent->id}");

    expect(File::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('the library boundary is asked as well, not only the permission', function () {
    $staff = folderDeleteRole([
        Permission::CreateOwnFolders, Permission::DeleteFiles,
        Permission::DeleteOthersFiles, Permission::Upload, Permission::EditFiles,
    ], clientScoped: true);

    $folder = Folder::query()->create(['name' => 'Scoped', 'created_by' => $staff->id]);
    $outside = folderDeleteUpload($this->admin, 'outside-the-library', $folder->id);

    // Both delete permissions are held, so the permission half of
    // FilePolicy::delete passes and only StaffLibraryScope can refuse --
    // which is the half a per-permission check would have missed.
    $this->actingAs($staff)->delete("/files/{$outside->id}")->assertForbidden();

    $this->actingAs($staff)->delete("/folders/{$folder->id}")->assertRedirect();

    expect(File::query()->whereKey($outside->id)->exists())->toBeTrue()
        ->and(Folder::query()->whereKey($folder->id)->exists())->toBeTrue();
});

test('a folder holding only the deleter own files still goes', function () {
    $staff = folderDeleteRole([
        Permission::CreateOwnFolders, Permission::DeleteFiles,
        Permission::Upload, Permission::EditFiles,
    ]);

    $folder = Folder::query()->create(['name' => 'Mine', 'created_by' => $staff->id]);
    $own = folderDeleteUpload($staff, 'my-own', $folder->id);

    $this->actingAs($staff)->delete("/folders/{$folder->id}")->assertRedirect();

    expect(File::query()->whereKey($own->id)->exists())->toBeFalse()
        ->and(Folder::query()->whereKey($folder->id)->exists())->toBeFalse();
});

test('an administrator holding both permissions is unaffected', function () {
    $folder = Folder::query()->create(['name' => 'Anything', 'created_by' => $this->admin->id]);
    $other = User::factory()->create();
    $theirs = folderDeleteUpload($other, 'theirs', $folder->id);

    $this->actingAs($this->admin)->delete("/folders/{$folder->id}")->assertRedirect();

    expect(File::query()->whereKey($theirs->id)->exists())->toBeFalse();
});
