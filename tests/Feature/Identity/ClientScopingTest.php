<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

/** Upload a file as a given user, optionally into a folder. */
function clientManager(): User
{
    return User::factory()->role(SystemRole::ClientManager)->create();
}

test('the Client Manager role is seeded scoped, non-admin, and can upload', function () {
    $role = Role::query()->where('name', 'Client Manager')->sole();

    expect($role->client_scoped)->toBeTrue()
        ->and($role->is_system)->toBeTrue()
        ->and($role->is_administrator)->toBeFalse()
        ->and($role->permissions()->where('permission', 'upload')->exists())->toBeTrue();
});

test('EnsureSystemRoles is idempotent and repairs a drifted client_scoped flag', function () {
    $role = Role::query()->where('name', 'Client Manager')->sole();
    $role->forceFill(['client_scoped' => false])->save();

    (new EnsureSystemRoles)->ensure();

    expect($role->refresh()->client_scoped)->toBeTrue()
        // Still exactly four seeded system roles — no duplicates created
        // (Uploader is legacy and never seeded).
        ->and(Role::query()->where('is_system', true)->count())->toBe(4);
});

test('a client-scoped staff member sees only their clients content plus their own uploads', function () {
    $scope = app(StaffLibraryScope::class);

    $clientA = User::factory()->client()->create();
    $manager = clientManager();
    $manager->assignedClients()->sync([$clientA->id]);

    // Content shared with A (directly + inside a shared folder), the manager's
    // own upload, and unrelated content nobody scoped should see.
    $sharedFile = uploadNamedFile($this->admin, 'a-direct');
    $this->actingAs($this->admin)->post("/files/{$sharedFile->id}/assignments", ['type' => 'client', 'id' => $clientA->id]);

    $sharedFolder = app(FolderService::class)->create('A Folder', null);
    $this->actingAs($this->admin)->post("/folders/{$sharedFolder->id}/assignments", ['type' => 'client', 'id' => $clientA->id]);
    $inFolder = uploadNamedFile($this->admin, 'a-in-folder', $sharedFolder->id);

    $ownFile = uploadNamedFile($manager, 'my-own');
    $unrelated = uploadNamedFile($this->admin, 'secret');

    $visibleFileIds = $scope->files($manager)->pluck('id')->all();

    expect($visibleFileIds)->toContain($sharedFile->id, $inFolder->id, $ownFile->id)
        ->not->toContain($unrelated->id);
    expect($scope->folders($manager)->pluck('id')->all())->toContain($sharedFolder->id);

    // A file added to the shared folder AFTER assignment is live-visible.
    $later = uploadNamedFile($this->admin, 'a-later', $sharedFolder->id);
    expect($scope->files($manager)->pluck('id')->all())->toContain($later->id);
});

test('a scoped manager with no assigned clients sees only their own uploads', function () {
    $scope = app(StaffLibraryScope::class);
    $manager = clientManager();

    $own = uploadNamedFile($manager, 'mine');
    $other = uploadNamedFile($this->admin, 'not-mine');

    expect($scope->files($manager)->pluck('id')->all())->toBe([$own->id])
        ->not->toContain($other->id);
});

test('direct access to out-of-scope files and folders is forbidden for a scoped manager', function () {
    $clientA = User::factory()->client()->create();
    $manager = clientManager();
    $manager->assignedClients()->sync([$clientA->id]);

    $unrelated = uploadNamedFile($this->admin, 'unrelated');

    $this->actingAs($manager)->get("/files/{$unrelated->id}/download")->assertForbidden();
    $this->actingAs($manager)->getJson("/files/{$unrelated->id}/details")->assertForbidden();
    $this->actingAs($manager)->get("/files/{$unrelated->id}")->assertForbidden();

    // In-scope: their own upload is reachable.
    $own = uploadNamedFile($manager, 'own');
    $this->actingAs($manager)->get("/files/{$own->id}/download")->assertOk();
});

// The mirror of the sharing guard below: reaching a file through one of
// your own clients must not let you revoke a different client's access to
// it. Un-sharing is destructive, so it needs the same gate as sharing.
test('a scoped manager cannot un-share a file from a client that is not theirs', function () {
    $clientA = User::factory()->client()->create();
    $clientB = User::factory()->client()->create(['name' => 'Not Mine']);
    $manager = clientManager();
    $manager->assignedClients()->sync([$clientA->id]);

    // The manager's own file (so Gate::update passes on ownership alone),
    // shared by the admin with both clients — only one of which is theirs.
    $file = uploadNamedFile($manager, 'shared');
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $clientA->id]);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $clientB->id]);
    expect($file->assignments()->count())->toBe(2);

    $this->actingAs($manager)->delete("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $clientB->id])
        ->assertSessionHasErrors('id');
    expect($file->assignments()->count())->toBe(2);

    // Their own client's assignment is still theirs to remove.
    $this->actingAs($manager)->delete("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $clientA->id])
        ->assertSessionHasNoErrors();
    expect($file->assignments()->count())->toBe(1);
});

test('a scoped manager can only share with their assigned clients', function () {
    $clientA = User::factory()->client()->create();
    $clientB = User::factory()->client()->create(['name' => 'Not Mine']);
    $manager = clientManager();
    $manager->assignedClients()->sync([$clientA->id]);

    $file = uploadNamedFile($manager, 'mine');

    // Sharing with the unassigned client B is rejected.
    $this->actingAs($manager)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $clientB->id])
        ->assertSessionHasErrors('id');
    expect($file->assignments()->count())->toBe(0);

    // Sharing with the assigned client A succeeds.
    $this->actingAs($manager)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $clientA->id])
        ->assertSessionHasNoErrors();
    expect($file->assignments()->count())->toBe(1);

    // The details picker only offers the assigned client.
    $names = collect(
        $this->actingAs($manager)->getJson("/files/{$file->id}/details")->json('shares.available_clients')
    )->pluck('name');
    expect($names)->not->toContain('Not Mine');

    // The file edit page's picker is filtered the same way.
    $this->actingAs($manager)->get("/files/{$file->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('available_clients', fn ($clients) => collect($clients)->doesntContain('name', 'Not Mine')));
});

test('assigning clients to a staff user syncs only for a client-scoped role', function () {
    $clientA = User::factory()->client()->create();
    $scopedRole = Role::query()->where('name', 'Client Manager')->sole();
    $plainRole = Role::query()->where('name', SystemRole::AccountManager->value)->sole();

    // Create scoped → clients persist.
    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Rep', 'email' => 'rep@example.test', 'role_id' => $scopedRole->id,
        'password' => 'password-123', 'password_confirmation' => 'password-123',
        'assigned_clients' => [$clientA->id],
    ])->assertRedirect();
    $rep = User::query()->where('email', 'rep@example.test')->sole();
    expect($rep->assignedClients()->pluck('users.id')->all())->toBe([$clientA->id]);

    // Switch to a non-scoped role → assignments cleared.
    $this->actingAs($this->admin)->patch("/users/{$rep->id}", [
        'name' => 'Rep', 'email' => 'rep@example.test', 'role_id' => $plainRole->id,
        'active' => true, 'assigned_clients' => [$clientA->id],
    ])->assertRedirect();
    expect($rep->refresh()->assignedClients()->count())->toBe(0);
});

test('a custom role can be made client-scoped, but a built-in role cannot change scope', function () {
    // Custom role toggles on.
    $this->actingAs($this->admin)->post('/roles', ['name' => 'Reps', 'client_scoped' => true, 'permissions' => ['upload']])
        ->assertRedirect();
    $custom = Role::query()->where('name', 'Reps')->sole();
    expect($custom->client_scoped)->toBeTrue();

    // Attempting to un-scope the built-in Client Manager via update is ignored.
    $builtIn = Role::query()->where('name', 'Client Manager')->sole();
    $this->actingAs($this->admin)->patch("/roles/{$builtIn->id}", ['name' => 'Client Manager', 'client_scoped' => false, 'permissions' => ['upload']])
        ->assertRedirect();
    expect($builtIn->refresh()->client_scoped)->toBeTrue();
});
