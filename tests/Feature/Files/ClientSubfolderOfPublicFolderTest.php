<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A client owns any folder they create, and owning a folder was the whole
 * of the client's upload rule. So a client shared a public folder that
 * staff kept read-only could create a folder inside it, upload into that,
 * and the file was public: the folder inherits its parent's public flag.
 * That walked past the parent's allow_client_uploads and the role's
 * upload_to_public_folders both.
 *
 * Reported as GHSA-65c7-3fpf-q89h.
 */
beforeEach(function () {
    Storage::fake('files');
    User::factory()->create();
});

function subfolderClient(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Client Role '.Str::random(6)]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);
}

function sharedPublicFolder(User $client, bool $allowClientUploads): Folder
{
    $folder = Folder::query()->create([
        'name' => 'Press kit', 'slug' => 'press-kit-'.Str::random(6), 'path' => '/',
        'public' => true, 'allow_client_uploads' => $allowClientUploads,
    ]);
    $folder->assignments()->create(['assignable_type' => $client->getMorphClass(), 'assignable_id' => $client->id]);

    return $folder;
}

function clientUploadInto(User $client, Folder $folder)
{
    return test()->actingAs($client)->postJson('/uploads', [
        'filename' => 'planted.pdf', 'size' => 8, 'type' => 'application/pdf', 'folder_id' => $folder->id,
    ]);
}

test('a client cannot create a folder inside a read-only public folder', function () {
    $client = subfolderClient([Permission::Upload, Permission::CreateOwnFolders, Permission::UploadToPublicFolders]);
    $public = sharedPublicFolder($client, allowClientUploads: false);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine', 'parent_id' => $public->id])
        ->assertForbidden();

    expect(Folder::query()->where('name', 'Mine')->exists())->toBeFalse();
});

test('a client cannot upload into a folder of their own that sits inside a read-only public folder', function () {
    // The folder may already exist: made before staff turned uploads off,
    // or before this fix.
    $client = subfolderClient([Permission::Upload, Permission::CreateOwnFolders, Permission::UploadToPublicFolders]);
    $public = sharedPublicFolder($client, allowClientUploads: false);
    $mine = Folder::query()->create([
        'name' => 'Mine', 'parent_id' => $public->id, 'path' => $public->subtreePathPrefix(), 'created_by' => $client->id,
    ]);

    expect(Folder::uploadableBy($client, $mine))->toBeFalse();
    clientUploadInto($client, $mine)->assertForbidden();
});

test('a client cannot move their file into such a folder either', function () {
    $client = subfolderClient([Permission::Upload, Permission::CreateOwnFolders, Permission::EditFiles]);
    $public = sharedPublicFolder($client, allowClientUploads: false);
    $mine = Folder::query()->create([
        'name' => 'Mine', 'parent_id' => $public->id, 'path' => $public->subtreePathPrefix(), 'created_by' => $client->id,
    ]);
    $file = File::factory()->create(['uploaded_by' => $client->id, 'folder_id' => null, 'public' => false]);

    $this->actingAs($client)->patch("/my-files/{$file->id}", ['name' => $file->name, 'folder_id' => $mine->id])
        ->assertForbidden();

    expect($file->fresh()->folder_id)->toBeNull();
});

test('without upload_to_public_folders, an open public folder is still closed to a client subfolder', function () {
    $client = subfolderClient([Permission::Upload, Permission::CreateOwnFolders]);
    $public = sharedPublicFolder($client, allowClientUploads: true);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine', 'parent_id' => $public->id])
        ->assertForbidden();
});

test('a public folder that opts into client uploads still takes a client subfolder and its uploads', function () {
    // The client could upload into the parent directly, so a folder of
    // their own inside it publishes nothing they could not already.
    $client = subfolderClient([Permission::Upload, Permission::CreateOwnFolders, Permission::UploadToPublicFolders]);
    $public = sharedPublicFolder($client, allowClientUploads: true);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine', 'parent_id' => $public->id])
        ->assertRedirect();

    $mine = Folder::query()->where('name', 'Mine')->sole();
    expect(Folder::uploadableBy($client, $mine))->toBeTrue();
});

test('a private shared folder still takes a client subfolder, and the client can upload into it', function () {
    $client = subfolderClient([Permission::Upload, Permission::CreateOwnFolders]);
    $shared = Folder::query()->create(['name' => 'Shared', 'path' => '/', 'public' => false]);
    $shared->assignments()->create(['assignable_type' => $client->getMorphClass(), 'assignable_id' => $client->id]);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine', 'parent_id' => $shared->id])
        ->assertRedirect();

    $mine = Folder::query()->where('name', 'Mine')->sole();
    expect(Folder::uploadableBy($client, $mine))->toBeTrue();
});
