<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/**
 * `edit_clients` says a staff member manages clients. It does not say
 * they manage *this* one. ClientFilesController::index drew that line
 * with StaffLibraryScope::canAssignClient; its neighbours in the same
 * family checked only that the record was a client at all, which is a
 * type check, not a boundary.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Scoped manager', 'client_scoped' => true]);
    foreach ([Permission::ManageClients, Permission::EditClients, Permission::DeleteClients, Permission::Upload] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    $this->manager = User::factory()->create(['role_id' => $role->id]);
    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->manager->assignedClients()->sync([$this->mine->id]);

    $this->stranger = User::factory()->client()->create(['name' => 'Stranger', 'email' => 'stranger@example.test']);
});

test('the client list names only the clients this manager holds', function () {
    $this->actingAs($this->manager)->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('clients', 1)
            ->where('clients.0.name', 'Mine'),
    );
});

test('a stranger client cannot be opened, changed, or deleted', function () {
    $this->actingAs($this->manager)->get("/clients/{$this->stranger->id}")->assertNotFound();

    $this->actingAs($this->manager)->patch("/clients/{$this->stranger->id}", [
        'name' => 'Renamed By Somebody Else',
        'email' => $this->stranger->email,
        'active' => true,
    ])->assertNotFound();

    // password.confirm sits in front of this one, so the session has to
    // say it was confirmed or the redirect answers before the guard does.
    $this->actingAs($this->manager)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete("/clients/{$this->stranger->id}/two-factor")
        ->assertNotFound();
    $this->actingAs($this->manager)->delete("/clients/{$this->stranger->id}")->assertNotFound();

    expect($this->stranger->fresh()->name)->toBe('Stranger');
});

test('the API answers the same way', function () {
    Sanctum::actingAs($this->manager, ['manage_clients', 'edit_clients', 'delete_clients']);

    $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/clients/{$this->stranger->id}")->assertNotFound();
    $this->patchJson("/api/v1/clients/{$this->stranger->id}", ['name' => 'Nope'])->assertNotFound();
    $this->deleteJson("/api/v1/clients/{$this->stranger->id}/two-factor")->assertNotFound();
    $this->deleteJson("/api/v1/clients/{$this->stranger->id}")->assertNotFound();

    expect(User::query()->whereKey($this->stranger->id)->exists())->toBeTrue();
});

test('a client this manager does hold stays fully manageable', function () {
    $this->actingAs($this->manager)->get("/clients/{$this->mine->id}")->assertOk();

    $this->actingAs($this->manager)->patch("/clients/{$this->mine->id}", [
        'name' => 'Mine, Renamed',
        'email' => $this->mine->email,
        'active' => true,
    ])->assertRedirect();

    expect($this->mine->fresh()->name)->toBe('Mine, Renamed');
});

test('an unscoped administrator reaches every client exactly as before', function () {
    $this->actingAs($this->admin)->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->has('clients', 2),
    );

    $this->actingAs($this->admin)->get("/clients/{$this->stranger->id}")->assertOk();
});
