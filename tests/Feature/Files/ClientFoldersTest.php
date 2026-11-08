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

// Their own uploads go with the folder, so a client the administrator
// has not allowed to delete files cannot delete them this way either.
test('a client without delete_files cannot delete their own folder while it holds their own files', function () {
    $client = User::factory()->client()->create();
    expect($client->can('delete_files'))->toBeFalse();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine'])->assertRedirect();
    $folder = Folder::query()->where('name', 'Mine')->sole();
    $own = File::factory()->create([
        'uploaded_by' => $client->id,
        'folder_id' => $folder->id,
        'name' => 'Invoice',
        'original_name' => 'invoice.pdf',
        'path' => '2026/08/invoice.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->actingAs($client)->delete("/my-folders/{$folder->id}")->assertRedirect();

    expect(Folder::query()->find($folder->id))->not->toBeNull()
        ->and(File::query()->find($own->id))->not->toBeNull();

    // With the permission, the folder and its files go together.
    RolePermission::query()->create(['role_id' => $client->role_id, 'permission' => 'delete_files']);
    app()->forgetScopedInstances();

    $this->actingAs($client->fresh())->delete("/my-folders/{$folder->id}")->assertRedirect();
    expect(Folder::query()->find($folder->id))->toBeNull()
        ->and(File::query()->find($own->id))->toBeNull();
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

test('a subfolder the client cannot enter is not named to them either', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'My Uploads'])->assertRedirect();
    $mine = Folder::query()->where('name', 'My Uploads')->sole();

    // Staff files something of another client's away inside it. The client
    // may open their own folder, but this subfolder was never shared with
    // them — and its name alone would say that other client exists.
    $this->actingAs($this->admin);
    $hidden = makeStaffFolder('Northwind Traders - contracts', $mine);

    $this->actingAs($client)->get('/my-files?folder='.$mine->id)->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0),
    );

    // Entering it was already refused; the name was the whole leak.
    $this->actingAs($client)->get('/my-files?folder='.$hidden->id)->assertNotFound();
});

test('a client browsing their own folder still sees the subfolders they may enter', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Parent'])->assertRedirect();
    $parent = Folder::query()->where('name', 'Parent')->sole();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Child', 'parent_id' => $parent->id])->assertRedirect();

    // The narrowing must not swallow the client's own nesting.
    $this->actingAs($client)->get('/my-files?folder='.$parent->id)->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 1)->where('folders.0.name', 'Child'),
    );
});

test('a folder a client made inside a share goes with the share', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $shared = makeStaffFolder('Project');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($client)->post('/my-folders', ['name' => 'Drafts', 'parent_id' => $shared->id])->assertRedirect();
    $drafts = Folder::query()->where('name', 'Drafts')->sole();

    // Staff hand the project to somebody else, and file into it for them.
    $this->actingAs($this->admin)->delete("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $other->id]);
    $forOther = File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $drafts->id]);

    expect(Folder::query()->visibleToClient($client)->pluck('id'))->not->toContain($drafts->id)
        ->and(File::query()->visibleToClient($client)->pluck('id'))->not->toContain($forOther->id)
        ->and(File::query()->visibleToClient($other)->pluck('id'))->toContain($forOther->id);

    expect($this->actingAs($client)->get('/my-files?folder='.$drafts->id)->status())->not->toBe(200);
    $this->actingAs($client)->patch("/my-folders/{$drafts->id}", ['name' => 'Mine now'])->assertForbidden();
    expect($drafts->fresh()->name)->toBe('Drafts');
});

test('a client keeps the folders in a tree of their own', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Top'])->assertRedirect();
    $top = Folder::query()->where('name', 'Top')->sole();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Inner', 'parent_id' => $top->id])->assertRedirect();
    $inner = Folder::query()->where('name', 'Inner')->sole();

    expect(Folder::query()->visibleToClient($client)->pluck('id')->all())->toContain($top->id, $inner->id);

    // Staff filing into the client's own tree still reaches the client.
    $filed = File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $inner->id]);
    expect(File::query()->visibleToClient($client)->pluck('id'))->toContain($filed->id);
});

test('a client-scoped staff member cannot file into the tree of a client off their roster', function () {
    $role = Role::query()->create(['name' => 'Scoped filer', 'client_scoped' => true]);
    foreach (['upload', 'edit_files', 'edit_others_files'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }
    $rep = User::factory()->create(['role_id' => $role->id]);
    $mine = User::factory()->client()->create();
    $stranger = User::factory()->client()->create();
    $rep->assignedClients()->sync([$mine->id]);

    // The stranger's own top-level folder, which staff also shared with
    // the rep's client: the rep can reach it, and it reaches the stranger.
    $this->actingAs($stranger)->post('/my-folders', ['name' => 'Stranger tree'])->assertRedirect();
    $tree = Folder::query()->where('name', 'Stranger tree')->sole();
    $this->actingAs($this->admin)->post("/folders/{$tree->id}/assignments", ['type' => 'client', 'id' => $mine->id]);

    $file = File::factory()->create(['uploaded_by' => $rep->id, 'folder_id' => null]);
    $this->actingAs($rep)->patch("/files/{$file->id}/move", ['folder_id' => $tree->id]);

    expect($file->fresh()->folder_id)->toBeNull();
});

test('a client cannot upload into a folder they made inside a share that ended', function () {
    $client = User::factory()->client()->create();
    $shared = makeStaffFolder('Project');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($client)->post('/my-folders', ['name' => 'Drafts', 'parent_id' => $shared->id])->assertRedirect();
    $drafts = Folder::query()->where('name', 'Drafts')->sole();

    expect(Folder::uploadableBy($client, $drafts))->toBeTrue();

    $this->actingAs($this->admin)->delete("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    expect(Folder::uploadableBy($client->fresh(), $drafts->fresh()))->toBeFalse();
});

test('a client cannot delete their own folder while it holds a folder staff made', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->post('/my-folders', ['name' => 'Home'])->assertRedirect();
    $home = Folder::query()->where('name', 'Home')->sole();
    $this->actingAs($this->admin);
    $staffMade = makeStaffFolder('Contracts', $home);

    $this->actingAs($client)->delete("/my-folders/{$home->id}")->assertRedirect()->assertSessionHas('error');

    expect(Folder::query()->find($home->id))->not->toBeNull()
        ->and(Folder::query()->find($staffMade->id))->not->toBeNull();
});

test('the move picker asks about every folder without a query for each', function () {
    $client = User::factory()->client()->create();
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => 'edit_files']);
    foreach (range(1, 12) as $i) {
        $this->actingAs($client)->post('/my-folders', ['name' => "Own {$i}"]);
    }
    $file = File::factory()->create(['uploaded_by' => $client->id]);

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->actingAs($client)->get("/my-files/{$file->id}/edit")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('folders', 12));
    $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());

    expect($queries)->toBeLessThan(60);
});
