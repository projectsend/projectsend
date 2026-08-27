<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\Category;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;

/**
 * A role can hold create_* without edit_*, and a successful create used
 * to redirect unconditionally to the new record's edit page — whose
 * can:edit_* middleware answers such a role with a 403 after the record
 * was created, logged and (for clients) welcomed. The store() methods
 * now fall back to the create form, which shares store()'s own gate and
 * is therefore reachable by exactly whoever just created the record.
 *
 * One test per entity rather than a clever loop, because each store()
 * wants its own payload — but the rule under test is the same four
 * times, and a fifth create flow should copy it.
 */
function roleHolding(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Holder '.uniqid()]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/** A role with no permissions at all — grantable by any staff actor. */
function grantableEmptyRole(): Role
{
    return Role::query()->create(['name' => 'Empty '.uniqid()]);
}

test('a create-only clients role lands back on the create form, success flashed', function () {
    $creator = roleHolding(['create_clients']);

    $response = $this->actingAs($creator)->post('/clients', [
        'name' => 'New Client',
        'email' => 'create-only-client@example.com',
        'password' => 'Sup3r-secret!22',
        'password_confirmation' => 'Sup3r-secret!22',
    ]);

    expect(User::query()->where('email', 'create-only-client@example.com')->exists())->toBeTrue();

    $response->assertRedirect(route('clients.create'))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('clients.create'))->assertOk();
});

test('a clients role that may edit keeps landing on the edit page', function () {
    $creator = roleHolding(['create_clients', 'edit_clients']);

    $response = $this->actingAs($creator)->post('/clients', [
        'name' => 'Editable Client',
        'email' => 'editable-client@example.com',
        'password' => 'Sup3r-secret!22',
        'password_confirmation' => 'Sup3r-secret!22',
    ]);

    $client = User::query()->where('email', 'editable-client@example.com')->firstOrFail();

    $response->assertRedirect(route('clients.edit', $client))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('clients.edit', $client))->assertOk();
});

test('a create-only users role lands back on the create form, success flashed', function () {
    // manage_users guards the whole route group, store included, so a
    // users creator always holds it alongside create_users.
    $creator = roleHolding(['manage_users', 'create_users']);

    $response = $this->actingAs($creator)->post('/users', [
        'name' => 'New Staffer',
        'email' => 'create-only-staffer@example.com',
        'role_id' => grantableEmptyRole()->id,
        'password' => 'Sup3r-secret!22',
        'password_confirmation' => 'Sup3r-secret!22',
    ]);

    expect(User::query()->where('email', 'create-only-staffer@example.com')->exists())->toBeTrue();

    $response->assertRedirect(route('users.create'))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('users.create'))->assertOk();
});

test('a users role that may edit keeps landing on the edit page', function () {
    $creator = roleHolding(['manage_users', 'create_users', 'edit_users']);

    $response = $this->actingAs($creator)->post('/users', [
        'name' => 'Editable Staffer',
        'email' => 'editable-staffer@example.com',
        'role_id' => grantableEmptyRole()->id,
        'password' => 'Sup3r-secret!22',
        'password_confirmation' => 'Sup3r-secret!22',
    ]);

    $user = User::query()->where('email', 'editable-staffer@example.com')->firstOrFail();

    $response->assertRedirect(route('users.edit', $user))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('users.edit', $user))->assertOk();
});

test('a create-only groups role lands back on the create form, success flashed', function () {
    $creator = roleHolding(['create_groups']);

    $response = $this->actingAs($creator)->post('/groups', ['name' => 'New Group', 'public' => false]);

    expect(Group::query()->where('name', 'New Group')->exists())->toBeTrue();

    $response->assertRedirect(route('groups.create'))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('groups.create'))->assertOk();
});

test('a groups role that may edit keeps landing on the edit page', function () {
    $creator = roleHolding(['create_groups', 'edit_groups']);

    $response = $this->actingAs($creator)->post('/groups', ['name' => 'Editable Group', 'public' => false]);

    $group = Group::query()->where('name', 'Editable Group')->firstOrFail();

    $response->assertRedirect(route('groups.edit', $group))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('groups.edit', $group))->assertOk();
});

test('a create-only categories role lands back on the create form, success flashed', function () {
    // The most reachable case: the sidebar shows Categories from
    // create_categories alone, so this whole flow is plain UI clicks.
    $creator = roleHolding(['create_categories']);

    $response = $this->actingAs($creator)->post('/categories', ['name' => 'New Category']);

    expect(Category::query()->where('name', 'New Category')->exists())->toBeTrue();

    $response->assertRedirect(route('categories.create'))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('categories.create'))->assertOk();
});

test('a categories role that may edit keeps landing on the edit page', function () {
    $creator = roleHolding(['create_categories', 'edit_categories']);

    $response = $this->actingAs($creator)->post('/categories', ['name' => 'Editable Category']);

    $category = Category::query()->where('name', 'Editable Category')->firstOrFail();

    $response->assertRedirect(route('categories.edit', $category))->assertSessionHas('success');
    $this->actingAs($creator)->get(route('categories.edit', $category))->assertOk();
});
