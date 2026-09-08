<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Putting a file in a public folder publishes it, because
 * File::isEffectivelyPublic() is "my own flag, or my folder's". So the
 * destination is a way to publish without ever touching the switch that
 * `upload_public` guards.
 *
 * The client half of this has always been gated, on
 * `upload_to_public_folders` — see the picker in MyFilesController, whose
 * comment calls that the established meaning of the two keys. The staff
 * half never asked, so on a staff role that key did nothing at all.
 *
 * Reported as GHSA-237r-jx85-j3hr.
 */
beforeEach(function () {
    Storage::fake('files');

    $this->public = Folder::query()->create([
        'name' => 'Brochures', 'slug' => 'brochures', 'path' => '/', 'public' => true,
    ]);
    $this->private = Folder::query()->create([
        'name' => 'Internal', 'slug' => 'internal', 'path' => '/', 'public' => false,
    ]);
});

function publicFolderStaff(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6)]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

function uploadInto(User $staff, Folder $folder)
{
    return test()->actingAs($staff)->post('/files', [
        'file' => UploadedFile::fake()->create('brochure.pdf', 8),
        'folder_id' => $folder->id,
    ]);
}

test('an uploader without either public key cannot publish through a folder', function () {
    $staff = publicFolderStaff([Permission::Upload]);

    uploadInto($staff, $this->public)->assertForbidden();
});

test('the same uploader can still upload into a private folder', function () {
    // The tightening is about publishing, not about uploading.
    $staff = publicFolderStaff([Permission::Upload]);

    uploadInto($staff, $this->private)->assertRedirect();
});

test('upload_to_public_folders is what opens it', function () {
    // The key already exists and already means this for clients. It simply
    // did nothing on a staff role.
    $staff = publicFolderStaff([Permission::Upload, Permission::UploadToPublicFolders]);

    uploadInto($staff, $this->public)->assertRedirect();
});

test('upload_public opens it too', function () {
    // Somebody who may set a file public may certainly put one where
    // everything is.
    $staff = publicFolderStaff([Permission::Upload, Permission::UploadPublic]);

    uploadInto($staff, $this->public)->assertRedirect();
});

test('a public ancestor counts, not just the folder itself', function () {
    // isEffectivelyPublic() walks up, so a private folder inside a public
    // one is still published. A check on the folder's own flag would miss
    // exactly this.
    $child = Folder::query()->create([
        'name' => 'Drafts', 'slug' => 'drafts', 'path' => '/'.$this->public->id.'/',
        'parent_id' => $this->public->id, 'public' => false,
    ]);

    $staff = publicFolderStaff([Permission::Upload]);

    uploadInto($staff, $child)->assertForbidden();
});

test('an administrator is unaffected', function () {
    expect(Folder::uploadableBy(User::factory()->create(), $this->public))->toBeTrue();
});

test('the chunked and API paths answer the same way', function () {
    // Every upload route asks Folder::uploadableBy(), which is the point
    // of it being there — one answer rather than four.
    $staff = publicFolderStaff([Permission::Upload]);

    expect(Folder::uploadableBy($staff, $this->public))->toBeFalse()
        ->and(Folder::uploadableBy($staff, $this->private))->toBeTrue();
});
