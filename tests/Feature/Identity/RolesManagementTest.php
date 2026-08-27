<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use Inertia\Testing\AssertableInertia;

test('ensure recreates a missing system role with its default permissions', function () {
    $uploader = Role::query()->where('name', 'Account Manager')->sole();
    RolePermission::query()->where('role_id', $uploader->id)->delete();
    // Bypass the model guard to simulate corruption.
    Role::query()->where('id', $uploader->id)->delete();

    (new EnsureSystemRoles)->ensure();

    $restored = Role::query()->where('name', 'Account Manager')->sole();
    expect($restored->is_system)->toBeTrue()
        ->and($restored->permissions()->count())->toBe(count(SystemRole::AccountManager->defaultPermissions()));
});

test('ensure never overwrites customized permissions of an existing role', function () {
    $uploader = Role::query()->where('name', 'Account Manager')->sole();
    RolePermission::query()->where('role_id', $uploader->id)->delete();
    RolePermission::query()->create(['role_id' => $uploader->id, 'permission' => Permission::Upload->value]);

    (new EnsureSystemRoles)->ensure();

    expect($uploader->permissions()->pluck('permission')->all())->toBe(['upload']);
});

test('ensure repairs tampered flags', function () {
    Role::query()->where('name', 'Client')->update(['is_administrator' => true]);

    (new EnsureSystemRoles)->ensure();

    expect(Role::query()->where('name', 'Client')->sole()->is_administrator)->toBeFalse();
});

test('system roles cannot be deleted or renamed through the model', function () {
    $role = Role::query()->where('name', 'Account Manager')->sole();

    expect(fn () => $role->delete())->toThrow(RuntimeException::class);
    expect(function () use ($role) {
        $role->name = 'Renamed';
        $role->save();
    })->toThrow(RuntimeException::class);
});

test('an administrator can view the roles index', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/roles')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('roles', 4)
            ->where('roles.0.name', 'System Administrator')
            ->where('roles.0.permissions_count', null),
    );
});

test('the roles index tags each role type and orders system, then client, then custom', function () {
    $this->actingAs(User::factory()->create());
    Role::query()->create(['name' => 'Auditor']); // a custom role

    $this->get('/roles')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('roles.0.type', 'system')
        ->where('roles.0.name', 'System Administrator')
        ->where('roles.3.type', 'client')   // the client role sits after the staff system roles
        ->where('roles.3.name', 'Client')
        ->where('roles.4.type', 'custom')   // custom roles last
        ->where('roles.4.name', 'Auditor'));

    $this->get('/roles?type=custom')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('roles', 1)->where('roles.0.name', 'Auditor'));

    $this->get('/roles?type=client')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('roles', 1)->where('roles.0.name', 'Client'));

    $this->get('/roles?type=system')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('roles', 3));  // Administrator, Account Manager, Client Manager (Client excluded)
});

test('creating a role flashes a success message that reaches the frontend', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/roles', ['name' => 'Flashed', 'permissions' => []])
        ->assertSessionHas('success', 'Role created.');

    // The flash is exposed as a shared Inertia prop on the next page load.
    $id = Role::query()->where('name', 'Flashed')->value('id');
    $this->get("/roles/{$id}")->assertInertia(fn (AssertableInertia $page) => $page->where('flash.success', 'Role created.'));
});

test('a custom role can be created with permissions and shows up for its users', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/roles', [
        'name' => 'Auditor',
        'permissions' => ['view_actions_log', 'view_statistics'],
    ])->assertRedirect();

    $role = Role::query()->where('name', 'Auditor')->sole();
    expect($role->is_system)->toBeFalse();

    $auditor = User::factory()->create(['role_id' => $role->id]);
    expect($auditor->can('view_actions_log'))->toBeTrue()
        ->and($auditor->can('edit_settings'))->toBeFalse();
});

test('updating a built-in role changes permissions but keeps the name', function () {
    $this->actingAs(User::factory()->create());

    $uploader = Role::query()->where('name', 'Account Manager')->sole();

    $this->patch("/roles/{$uploader->id}", [
        'name' => 'Account Manager',
        'permissions' => ['upload'],
    ])->assertRedirect();

    expect($uploader->refresh()->name)->toBe('Account Manager')
        ->and($uploader->permissions()->pluck('permission')->all())->toBe(['upload']);
});

test('the administrator role rejects edits', function () {
    $this->actingAs(User::factory()->create());

    $admin = Role::query()->where('is_administrator', true)->sole();

    $this->patch("/roles/{$admin->id}", ['name' => 'Hacked', 'permissions' => []])
        ->assertSessionHasErrors('permissions');
});

test('deleting is limited to unused custom roles', function () {
    $this->actingAs(User::factory()->create());

    $system = Role::query()->where('name', 'Account Manager')->sole();
    $this->delete("/roles/{$system->id}")->assertSessionHasErrors('role');

    $custom = Role::query()->create(['name' => 'Temp']);
    $member = User::factory()->create(['role_id' => $custom->id]);
    $this->delete("/roles/{$custom->id}")->assertSessionHasErrors('role');

    // Soft-deleting the member still blocks deletion (the reference remains).
    $member->delete();
    $this->delete("/roles/{$custom->id}")->assertSessionHasErrors('role');

    $member->forceDelete();
    $this->delete("/roles/{$custom->id}")->assertRedirect(route('roles.index'));
    expect(Role::query()->where('name', 'Temp')->exists())->toBeFalse();
});

test('roles management requires the manage_users permission', function () {
    $this->actingAs(User::factory()->role(SystemRole::AccountManager)->create());

    $this->get('/roles')->assertForbidden();
});

test('roles management is present in the cloud edition too', function () {
    // Follows users.manage, which gates both screens — see the capability's
    // own comment for why that opened in 2.2.0.
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs(User::factory()->create());

    $this->get('/roles')->assertOk();
});
