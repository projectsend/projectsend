<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function makeStaffFolder(string $name, ?Folder $parent = null): Folder
{
    return app(FolderService::class)->create($name, $parent);
}

test('a client with create_own_folders can create a top-level folder, visible to them afterward', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->post('/my-folders', ['name' => 'My Documents'])->assertRedirect();

    $folder = Folder::query()->where('name', 'My Documents')->sole();
    expect($folder->created_by)->toBe($client->id)
        ->and(ActivityLog::query()->where('action', Action::FolderCreated)->where('actor_id', $client->id)->exists())->toBeTrue();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 1)
            ->where('folders.0.name', 'My Documents')
            ->where('folders.0.is_mine', true)
            ->where('folders.0.can_update', true)
            ->where('folders.0.can_delete', true),
    );
});

test('a client can nest a folder inside a folder staff shared with them', function () {
    $client = User::factory()->client()->create();
    $shared = makeStaffFolder('Shared With Me');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Nested', 'parent_id' => $shared->id])->assertRedirect();

    $nested = Folder::query()->where('name', 'Nested')->sole();
    expect($nested->parent_id)->toBe($shared->id)
        ->and($nested->created_by)->toBe($client->id);

    $this->actingAs($client)->get('/my-files?folder='.$shared->id)->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 1)->where('folders.0.name', 'Nested'),
    );
});

test('a client can nest a folder inside one they created themselves', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Parent'])->assertRedirect();
    $parent = Folder::query()->where('name', 'Parent')->sole();

    $this->actingAs($client)->post('/my-folders', ['name' => 'Child', 'parent_id' => $parent->id])->assertRedirect();

    $child = Folder::query()->where('name', 'Child')->sole();
    expect($child->parent_id)->toBe($parent->id);

    // The child is nested, so root listing shows only the parent.
    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 1)->where('folders.0.name', 'Parent'),
    );
});

test('a client cannot nest a folder inside one not visible to them', function () {
    $client = User::factory()->client()->create();
    $hidden = makeStaffFolder('Not Shared');

    $this->actingAs($client)->post('/my-folders', ['name' => 'Sneaky', 'parent_id' => $hidden->id])->assertNotFound();

    expect(Folder::query()->where('name', 'Sneaky')->exists())->toBeFalse();
});

test('a client-owned folder is visible to staff scoped to that client', function () {
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Client Space'])->assertRedirect();
    $folder = Folder::query()->where('name', 'Client Space')->sole();

    $this->actingAs($manager)->get('/files?folder='.$folder->id)->assertInertia(
        fn (AssertableInertia $page) => $page->where('folder.name', 'Client Space'),
    );

    // An unassigned scoped staffer can't reach it: the folder param
    // doesn't resolve via their scope, so FoldersController::index()
    // silently falls back to their own (empty) root instead of 404ing.
    $unassignedManager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->actingAs($unassignedManager)->get('/files?folder='.$folder->id)->assertInertia(
        fn (AssertableInertia $page) => $page->where('folder', null),
    );
});

test('a client can rename a folder they created, but not one staff created and merely shared with them', function () {
    $client = User::factory()->client()->create();
    $shared = makeStaffFolder('Staff Owned');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine'])->assertRedirect();
    $own = Folder::query()->where('name', 'Mine')->sole();

    $this->actingAs($client)->patch("/my-folders/{$own->id}", ['name' => 'Renamed'])->assertRedirect();
    expect($own->refresh()->name)->toBe('Renamed');

    $this->actingAs($client)->patch("/my-folders/{$shared->id}", ['name' => 'Nope'])->assertForbidden();
    expect($shared->refresh()->name)->toBe('Staff Owned');
});

test('a client can delete a folder they created, cascading to its contents', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Doomed'])->assertRedirect();
    $folder = Folder::query()->where('name', 'Doomed')->sole();

    $this->actingAs($client)->post('/my-folders', ['name' => 'Doomed Child', 'parent_id' => $folder->id]);
    $child = Folder::query()->where('name', 'Doomed Child')->sole();

    $this->actingAs($client)->delete("/my-folders/{$folder->id}")->assertRedirect();

    expect(Folder::query()->find($folder->id))->toBeNull()
        ->and(Folder::query()->find($child->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::FolderDeleted)->where('actor_id', $client->id)->exists())->toBeTrue();
});

// Deleting a folder cascades to its files and the bytes go with them, so
// owning the folder must not become authority over content someone else
// put in it — staff routinely upload into a client's own folder.
test('a client cannot delete their own folder while it holds files they did not upload', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine'])->assertRedirect();
    $folder = Folder::query()->where('name', 'Mine')->sole();

    $staffFile = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'folder_id' => $folder->id,
        'name' => 'Contract',
        'original_name' => 'contract.pdf',
        'path' => '2026/08/contract.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->actingAs($client)->delete("/my-folders/{$folder->id}")->assertRedirect();

    expect(Folder::query()->find($folder->id))->not->toBeNull()
        ->and(File::query()->find($staffFile->id))->not->toBeNull();

    // Once the staff file is out of the way the client's own folder deletes
    // as before — the guard is about foreign content, not about locking the
    // folder permanently.
    $staffFile->forceDelete();

    $this->actingAs($client)->delete("/my-folders/{$folder->id}")->assertRedirect();
    expect(Folder::query()->find($folder->id))->toBeNull();
});

test('a client cannot delete a folder shared with them but created by staff', function () {
    $client = User::factory()->client()->create();
    $shared = makeStaffFolder('Staff Owned');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($client)->delete("/my-folders/{$shared->id}")->assertForbidden();
    expect(Folder::query()->find($shared->id))->not->toBeNull();
});

test('a client without create_own_folders is forbidden from creating, renaming, or deleting', function () {
    $role = Role::query()->create(['name' => 'No-Folder Client', 'is_administrator' => false, 'is_system' => false]);
    $client = User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);
    $own = makeStaffFolder('Placeholder');
    $own->forceFill(['created_by' => $client->id])->save();

    $this->actingAs($client);
    $this->post('/my-folders', ['name' => 'Nope'])->assertForbidden();
    $this->patch("/my-folders/{$own->id}", ['name' => 'Nope'])->assertForbidden();
    $this->delete("/my-folders/{$own->id}")->assertForbidden();
});

test('a client with create_own_folders but not upload cannot create a folder, but can still manage one they already own', function () {
    // Upload travels with create_own_folders in the default Client role
    // now, but an admin can still separate them — creating a new folder
    // requires both (an empty one you can never fill isn't useful on its
    // own), while renaming/deleting one you already made doesn't.
    $role = Role::query()->create(['name' => 'Folders Without Upload', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'create_own_folders']);
    $client = User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);
    $own = makeStaffFolder('Existing');
    $own->forceFill(['created_by' => $client->id])->save();

    $this->actingAs($client);
    $this->post('/my-folders', ['name' => 'Nope'])->assertForbidden();
    $this->patch("/my-folders/{$own->id}", ['name' => 'Renamed'])->assertRedirect();
    expect($own->refresh()->name)->toBe('Renamed');
});

test('a loose file inside a client-created folder shows nested, not at root', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'My Folder'])->assertRedirect();
    $folder = Folder::query()->where('name', 'My Folder')->sole();

    File::factory()->create([
        'folder_id' => $folder->id,
        'name' => 'note', 'original_name' => 'note.pdf', 'path' => 'x', 'mime_type' => 'application/pdf',
        'size' => 10, 'checksum' => str_repeat('a', 64), 'uploaded_by' => $client->id,
    ]);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 1)->has('files', 0),
    );
    $this->actingAs($client)->get('/my-files?folder='.$folder->id)->assertInertia(
        fn (AssertableInertia $page) => $page->has('files', 1)->where('files.0.name', 'note'),
    );
});
