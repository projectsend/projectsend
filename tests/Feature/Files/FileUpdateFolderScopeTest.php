<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

/**
 * Setting a file's folder_id is the same privileged write in update(),
 * move() and bulkUpdate(). move()/bulkUpdate() already refuse a destination
 * outside the caller's library (StaffLibraryScope::folders); update() must
 * do the same, or a client-scoped staff member could reparent an in-scope
 * file into a folder shared with a client they are not assigned to — which
 * File::scopeVisibleToClient then exposes to that client, sidestepping the
 * very boundary the sharing endpoints enforce.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // A client-scoped staff member (Client Manager holds edit_files) assigned
    // to exactly one client.
    $this->mine = User::factory()->client()->create();
    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->manager->assignedClients()->sync([$this->mine->id]);

    // A folder shared with a *different* client — outside the manager's scope.
    $this->outsider = User::factory()->client()->create();
    $this->outOfScopeFolder = app(FolderService::class)->create('Outsider Folder', null);
    $this->actingAs($this->admin)
        ->post("/folders/{$this->outOfScopeFolder->id}/assignments", ['type' => 'client', 'id' => $this->outsider->id]);

    // A folder shared with the manager's own client — inside their scope.
    $this->inScopeFolder = app(FolderService::class)->create('My Client Folder', null);
    $this->actingAs($this->admin)
        ->post("/folders/{$this->inScopeFolder->id}/assignments", ['type' => 'client', 'id' => $this->mine->id]);
});

test('web update() refuses to reparent a file into an out-of-scope folder', function () {
    $file = File::factory()->create(['name' => 'Report', 'uploaded_by' => $this->manager->id]);

    $this->actingAs($this->manager)
        ->patch("/files/{$file->id}", ['name' => 'Report', 'folder_id' => $this->outOfScopeFolder->id])
        ->assertNotFound();

    expect($file->fresh()->folder_id)->toBeNull();
});

test('web update() still allows reparenting into an in-scope folder', function () {
    $file = File::factory()->create(['name' => 'Report', 'uploaded_by' => $this->manager->id]);

    $this->actingAs($this->manager)
        ->patch("/files/{$file->id}", ['name' => 'Report', 'folder_id' => $this->inScopeFolder->id])
        ->assertRedirect();

    expect($file->fresh()->folder_id)->toBe($this->inScopeFolder->id);
});

test('web update() may still re-save a file that already sits in an out-of-scope folder without moving it', function () {
    // The manager owns this file (so may edit it) but it lives in a folder
    // outside their scope. Re-saving without changing folder_id must not trip
    // the scope guard — only an actual move is checked. folder_id is sent as
    // a string here because that is what the edit form posts.
    $file = File::factory()->create([
        'name' => 'Report',
        'uploaded_by' => $this->manager->id,
        'folder_id' => $this->outOfScopeFolder->id,
    ]);

    $this->actingAs($this->manager)
        ->patch("/files/{$file->id}", ['name' => 'Renamed', 'folder_id' => (string) $this->outOfScopeFolder->id])
        ->assertRedirect();

    expect($file->fresh())
        ->name->toBe('Renamed')
        ->folder_id->toBe($this->outOfScopeFolder->id);
});

test('api update() refuses to reparent a file into an out-of-scope folder', function () {
    $file = File::factory()->create(['name' => 'Report', 'uploaded_by' => $this->manager->id]);

    $token = $this->manager->createToken('t', [
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ])->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/files/{$file->id}", ['folder_id' => $this->outOfScopeFolder->id])
        ->assertNotFound();

    expect($file->fresh()->folder_id)->toBeNull();
});

test('an unscoped admin may reparent into any folder through update()', function () {
    $file = File::factory()->create(['name' => 'Report', 'uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => 'Report', 'folder_id' => $this->outOfScopeFolder->id])
        ->assertRedirect();

    expect($file->fresh()->folder_id)->toBe($this->outOfScopeFolder->id);
});
