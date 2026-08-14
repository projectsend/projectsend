<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\Permissions\SystemRole;
use Inertia\Testing\AssertableInertia;

test('the migration seeds the built-in system roles', function () {
    // Uploader is NOT seeded on fresh installs — it is a legacy role only
    // recreated by the v1 migration tool.
    expect(Role::query()->where('is_system', true)->pluck('name')->sort()->values()->all())
        ->toBe(['Account Manager', 'Client', 'Client Manager', 'System Administrator']);

    expect(Role::query()->where('is_administrator', true)->sole()->name)->toBe('System Administrator');

    // Only the Client Manager role is client-scoped.
    expect(Role::query()->where('client_scoped', true)->pluck('name')->all())->toBe(['Client Manager']);
});

// The full matrix from the brief (§10): every permission key × every
// system role, asserted against the v1-parity default sets.
test('permission matrix matches the v1 defaults', function (SystemRole $systemRole) {
    $user = User::factory()->role($systemRole)->create();

    $expected = array_map(
        fn (Permission $permission): string => $permission->value,
        $systemRole->defaultPermissions(),
    );

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))
            ->toBe(in_array($permission->value, $expected, true), "{$systemRole->value} / {$permission->value}");
    }
})->with(SystemRole::cases());

test('a user without a role is denied everything', function () {
    $user = User::factory()->create(['role_id' => null]);

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBeFalse($permission->value);
    }
});

test('the administrator role grants permissions added after seeding', function () {
    // No pivot rows exist for the administrator role — every permission
    // is granted by construction, so new enum cases can never be missing.
    $admin = User::factory()->create();

    expect(RolePermission::query()->where('role_id', $admin->role_id)->count())->toBe(0)
        ->and($admin->can(Permission::ManageUpdates->value))->toBeTrue();
});

test('granted permissions are shared with the frontend per role', function () {
    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    $expected = array_map(
        fn (Permission $permission): string => $permission->value,
        SystemRole::Uploader->defaultPermissions(),
    );
    sort($expected);

    $this->actingAs($uploader)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'auth.permissions',
            fn ($granted) => collect($granted)->sort()->values()->all() === $expected,
        ),
    );
});

test('an uploader can view the activity log but not system settings', function () {
    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($uploader);
    $this->get('/activity')->assertOk();
    $this->get('/system/settings/general')->assertForbidden();
    $this->patch('/system/settings/general', ['site_name' => 'Nope'])->assertForbidden();
});

test('an account manager can view the activity log but not edit settings', function () {
    $manager = User::factory()->role(SystemRole::AccountManager)->create();

    $this->actingAs($manager);
    $this->get('/activity')->assertOk();
    $this->get('/system/settings/security')->assertForbidden();
});

test('setup assigns the System Administrator role to the first admin', function () {
    $this->post('/setup', [
        'site_name' => 'ProjectSend',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $admin = User::query()->sole();
    expect($admin->role?->is_administrator)->toBeTrue();
});

test('the projectsend:admin command assigns the System Administrator role', function () {
    $this->artisan('projectsend:admin', [
        '--name' => 'CLI Admin',
        '--email' => 'cli@example.com',
        '--password' => 'super-secret-password',
    ]);

    expect(User::query()->sole()->role?->is_administrator)->toBeTrue();
});

test('stale permission keys in the pivot are ignored', function () {
    $role = Role::query()->create(['name' => 'Custom']);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'a_removed_permission']);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => Permission::Upload->value]);

    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->can(Permission::Upload->value))->toBeTrue()
        ->and(app(PermissionChecker::class)->grantedKeys($user))->toBe(['upload']);
});
