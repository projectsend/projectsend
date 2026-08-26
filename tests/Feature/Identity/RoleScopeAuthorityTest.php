<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * A role's client_scoped flag is the boundary a limited staff member
 * works inside. StaffAccounts already refuses to let anybody grant
 * permissions they do not hold; this is the same rule for the flag that
 * decides how much of the library the role reaches.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $this->scopedRole = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::ManageUsers, Permission::EditUsers, Permission::CreateUsers] as $permission) {
        RolePermission::query()->create(['role_id' => $this->scopedRole->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $this->scopedRole->id]);
    $this->mine = User::factory()->client()->create();
    $this->rep->assignedClients()->sync([$this->mine->id]);

    // Somebody else's file: the thing the boundary is holding back.
    $this->secret = uploadNamedFile($this->admin, 'not-theirs');
});

function unscopedRoleWith(array $permissions): Role
{
    $role = Role::query()->create(['name' => 'Wide '.Str::random(6), 'client_scoped' => false]);
    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return $role;
}

function stillScoped(User $rep): bool
{
    $rep->refresh()->unsetRelation('role');

    return $rep->isClientScoped();
}

test('a scoped staff member cannot lift the limit off their own role', function () {
    $this->actingAs($this->rep)->patch("/roles/{$this->scopedRole->id}", [
        'name' => $this->scopedRole->name,
        'client_scoped' => false,
        'permissions' => [Permission::ManageUsers->value, Permission::EditUsers->value, Permission::CreateUsers->value],
    ])->assertSessionHasErrors('client_scoped');

    expect($this->scopedRole->fresh()->client_scoped)->toBeTrue()
        ->and(stillScoped($this->rep))->toBeTrue()
        ->and(app(StaffLibraryScope::class)->files($this->rep)->pluck('id')->all())
        ->not->toContain($this->secret->id);
});

test('a scoped staff member cannot mint a role without the limit', function () {
    $this->actingAs($this->rep)->post('/roles', [
        'name' => 'Escape Hatch',
        'client_scoped' => false,
        'permissions' => [Permission::ManageUsers->value],
    ])->assertSessionHasErrors('client_scoped');

    expect(Role::query()->where('name', 'Escape Hatch')->exists())->toBeFalse();
});

/**
 * The `boolean` validation rule accepts "0" and 0 as well as false, and
 * validates without casting -- so a guard that compares the validated
 * value strictly against false sees a string, waves the request through,
 * and the model's own `boolean` cast then writes the flag as false. Both
 * writers read the flag with Request::boolean() for that reason, and
 * these two pin it: the same two requests as above, spelled the other
 * way, have to be refused the same way.
 */
test('the limit cannot be dropped by spelling false as "0"', function () {
    $this->actingAs($this->rep)->patch("/roles/{$this->scopedRole->id}", [
        'name' => $this->scopedRole->name,
        'client_scoped' => '0',
        'permissions' => [Permission::ManageUsers->value, Permission::EditUsers->value, Permission::CreateUsers->value],
    ])->assertSessionHasErrors('client_scoped');

    expect($this->scopedRole->fresh()->client_scoped)->toBeTrue()
        ->and(stillScoped($this->rep))->toBeTrue()
        ->and(app(StaffLibraryScope::class)->files($this->rep)->pluck('id')->all())
        ->not->toContain($this->secret->id);

    // And through the JSON door, where it arrives as an integer.
    $this->actingAs($this->rep)->patchJson("/roles/{$this->scopedRole->id}", [
        'name' => $this->scopedRole->name,
        'client_scoped' => 0,
        'permissions' => [Permission::ManageUsers->value, Permission::EditUsers->value, Permission::CreateUsers->value],
    ])->assertStatus(422);

    expect($this->scopedRole->fresh()->client_scoped)->toBeTrue();
});

test('a role without the limit cannot be minted by spelling false as "0"', function () {
    $this->actingAs($this->rep)->post('/roles', [
        'name' => 'Escape Hatch Zero',
        'client_scoped' => '0',
        'permissions' => [Permission::ManageUsers->value],
    ])->assertSessionHasErrors('client_scoped');

    expect(Role::query()->where('name', 'Escape Hatch Zero')->exists())->toBeFalse();
});

test('a scoped staff member cannot move into an unscoped role that already exists', function () {
    $wide = unscopedRoleWith([Permission::ManageUsers, Permission::EditUsers, Permission::CreateUsers]);

    $this->actingAs($this->rep)->patch("/users/{$this->rep->id}", [
        'name' => $this->rep->name,
        'email' => $this->rep->email,
        'role_id' => $wide->id,
        'active' => true,
    ])->assertSessionHasErrors('role_id');

    expect(stillScoped($this->rep))->toBeTrue()
        ->and(app(StaffLibraryScope::class)->files($this->rep)->pluck('id')->all())
        ->not->toContain($this->secret->id);
});

test('a scoped staff member cannot hand an unscoped role to anybody else either', function () {
    $wide = unscopedRoleWith([Permission::ManageUsers]);

    $this->actingAs($this->rep)->post('/users', [
        'name' => 'New Rep',
        'email' => 'new-rep@example.test',
        'role_id' => $wide->id,
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
    ])->assertSessionHasErrors('role_id');

    expect(User::query()->where('email', 'new-rep@example.test')->exists())->toBeFalse();
});

test('the staff form stops offering a role the request behind it would refuse', function () {
    $wide = unscopedRoleWith([Permission::ManageUsers]);

    $this->actingAs($this->rep)->get('/users/create')->assertInertia(
        fn (AssertableInertia $page) => $page->where('roles', function ($roles) use ($wide) {
            $ids = collect($roles)->pluck('id')->all();

            expect($ids)->toContain($this->scopedRole->id)->not->toContain($wide->id);

            return true;
        })
    );
});

test('a scoped staff member has no business editing an unscoped colleague', function () {
    $wide = unscopedRoleWith([Permission::ManageUsers]);
    $colleague = User::factory()->create(['role_id' => $wide->id]);

    $this->actingAs($this->rep)->patch("/users/{$colleague->id}", [
        'name' => 'Renamed',
        'email' => $colleague->email,
        'role_id' => $wide->id,
        'active' => true,
    ])->assertForbidden();

    expect($colleague->fresh()->name)->not->toBe('Renamed');
});

test('the API surface refuses the same move', function () {
    $wide = unscopedRoleWith([Permission::ManageUsers, Permission::EditUsers, Permission::CreateUsers]);
    $token = $this->rep->createToken('t', [Permission::ManageUsers->value, Permission::EditUsers->value])->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$this->rep->id}", ['role_id' => $wide->id])
        ->assertStatus(422);

    expect(stillScoped($this->rep))->toBeTrue();
});

test('a scoped staff member may still create and edit a role inside their own limit', function () {
    $this->actingAs($this->rep)->post('/roles', [
        'name' => 'Junior Rep',
        'client_scoped' => true,
        'permissions' => [Permission::ManageUsers->value],
    ])->assertSessionHasNoErrors();

    $junior = Role::query()->where('name', 'Junior Rep')->firstOrFail();
    expect($junior->client_scoped)->toBeTrue();

    $this->actingAs($this->rep)->patch("/roles/{$junior->id}", [
        'name' => 'Junior Rep',
        'client_scoped' => true,
        'permissions' => [Permission::ManageUsers->value, Permission::EditUsers->value],
    ])->assertSessionHasNoErrors();

    expect($junior->fresh()->permissions()->pluck('permission')->all())
        ->toContain(Permission::EditUsers->value);
});

test('an unscoped staff member with the same permissions is unaffected', function () {
    $wideRole = unscopedRoleWith([Permission::ManageUsers, Permission::EditUsers, Permission::CreateUsers]);
    $manager = User::factory()->create(['role_id' => $wideRole->id]);

    $this->actingAs($manager)->post('/roles', [
        'name' => 'Another Wide One',
        'client_scoped' => false,
        'permissions' => [Permission::ManageUsers->value],
    ])->assertSessionHasNoErrors();

    expect(Role::query()->where('name', 'Another Wide One')->value('client_scoped'))->toBeFalse();

    $this->actingAs($manager)->patch("/users/{$manager->id}", [
        'name' => $manager->name,
        'email' => $manager->email,
        'role_id' => $wideRole->id,
        'active' => true,
    ])->assertSessionHasNoErrors();
});

test('an administrator is unaffected', function () {
    $this->actingAs($this->admin)->patch("/roles/{$this->scopedRole->id}", [
        'name' => $this->scopedRole->name,
        'client_scoped' => false,
        'permissions' => [Permission::ManageUsers->value],
    ])->assertSessionHasNoErrors();

    expect($this->scopedRole->fresh()->client_scoped)->toBeFalse();
});

test('the seeded Client Manager role keeps its flag, as it always did', function () {
    $clientManager = Role::query()->where('name', SystemRole::ClientManager->value)->firstOrFail();

    $this->actingAs($this->rep)->patch("/roles/{$clientManager->id}", [
        'name' => $clientManager->name,
        'client_scoped' => false,
        'permissions' => [],
    ]);

    expect($clientManager->fresh()->client_scoped)->toBeTrue();
});
