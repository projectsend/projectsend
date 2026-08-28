<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

// Staff and clients are deleted from two different screens that share one
// implementation of "what happens to this account's files". These cases run
// against both, so the two cannot answer the same question differently.
//
// The semantics of cascading and reassigning belong to DeletedAccountContent
// and are covered by DeletedAccountContentTest; what matters here is that
// both routes reach them, and enforce the same rules on the way.

dataset('accounts', [
    'staff' => [fn () => User::factory()->create(), 'users'],
    'client' => [fn () => User::factory()->client()->create(), 'clients'],
]);

test('deleting an account with no content needs no choice', function (Closure $make, string $segment) {
    $target = $make();

    $this->actingAs($this->admin)->delete("/{$segment}/{$target->id}")->assertRedirect();

    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();
})->with('accounts');

test('deleting an account that owns files requires a choice', function (Closure $make, string $segment) {
    $target = $make();
    File::factory()->create(['uploaded_by' => $target->id]);

    $this->actingAs($this->admin)->delete("/{$segment}/{$target->id}")
        ->assertSessionHasErrors('content_action');

    expect(User::withTrashed()->find($target->id)->trashed())->toBeFalse();
})->with('accounts');

test('cascade_delete removes the account content', function (Closure $make, string $segment) {
    $target = $make();
    $file = File::factory()->create(['uploaded_by' => $target->id]);

    $this->actingAs($this->admin)
        ->delete("/{$segment}/{$target->id}", ['content_action' => 'cascade_delete'])
        ->assertRedirect();

    expect(File::withTrashed()->find($file->id)->trashed())->toBeTrue();
})->with('accounts');

test('reassign hands the content to the chosen account', function (Closure $make, string $segment) {
    $target = $make();
    $file = File::factory()->create(['uploaded_by' => $target->id]);
    $heir = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/{$segment}/{$target->id}", ['content_action' => 'reassign', 'reassign_to_id' => $heir->id])
        ->assertRedirect();

    expect(File::find($file->id)->uploaded_by)->toBe($heir->id);
})->with('accounts');

// These three rules are why the shared copy matters: each one is a guard
// against destroying or misfiling data, and a version of it that fell behind
// on one of the two screens would be a real hole.
test('reassigning to the account being deleted is refused', function (Closure $make, string $segment) {
    $target = $make();
    File::factory()->create(['uploaded_by' => $target->id]);

    $this->actingAs($this->admin)
        ->delete("/{$segment}/{$target->id}", ['content_action' => 'reassign', 'reassign_to_id' => $target->id])
        ->assertSessionHasErrors('reassign_to_id');
})->with('accounts');

test('reassigning to an inactive account is refused', function (Closure $make, string $segment) {
    $target = $make();
    File::factory()->create(['uploaded_by' => $target->id]);
    $inactive = User::factory()->create(['active' => false]);

    $this->actingAs($this->admin)
        ->delete("/{$segment}/{$target->id}", ['content_action' => 'reassign', 'reassign_to_id' => $inactive->id])
        ->assertSessionHasErrors('reassign_to_id');
})->with('accounts');

test('an unrecognised content action is refused', function (Closure $make, string $segment) {
    $target = $make();
    File::factory()->create(['uploaded_by' => $target->id]);

    $this->actingAs($this->admin)
        ->delete("/{$segment}/{$target->id}", ['content_action' => 'something_else'])
        ->assertSessionHasErrors('content_action');
})->with('accounts');

test('both screens offer the same reassignment candidates', function () {
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);
    $staff = User::factory()->role(SystemRole::ClientManager)->create(['name' => 'Sam Staff']);
    User::factory()->create(['name' => 'Inactive One', 'active' => false]);

    $fromUsers = $this->actingAs($this->admin)->get("/users/{$staff->id}")
        ->viewData('page')['props']['reassign_candidates'];
    $fromClients = $this->actingAs($this->admin)->get("/clients/{$client->id}")
        ->viewData('page')['props']['reassign_candidates'];

    $names = fn (array $rows) => collect($rows)->pluck('name')->sort()->values()->all();

    // Each page excludes the row it is editing, so compare the rest.
    expect($names(array_filter($fromUsers, fn ($r) => $r['name'] !== 'Acme Ltd')))
        ->toBe($names(array_filter($fromClients, fn ($r) => $r['name'] !== 'Sam Staff')))
        // Inactive accounts cannot inherit anything, on either screen.
        ->and(collect($fromUsers)->pluck('name'))->not->toContain('Inactive One')
        ->and(collect($fromClients)->pluck('name'))->not->toContain('Inactive One');
});

/*
|--------------------------------------------------------------------------
| Who the picker is sent to, and what it may name
|--------------------------------------------------------------------------
|
| The candidate list is the delete dialog's picker: every active account in
| the installation, by name and role. It sits on index pages next to a
| listing that is deliberately narrowed, and it was narrowed by nothing.
|
*/

/** @return array{0: User, 1: User, 2: User} viewer, their client, a stranger's client */
function scopedClientManager(array $permissions): array
{
    $role = Role::query()->create(['name' => 'Scoped rep '.count($permissions), 'client_scoped' => true]);
    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    $viewer = User::factory()->create(['role_id' => $role->id, 'name' => 'The Rep']);
    $mine = User::factory()->client()->create(['name' => 'Mine']);
    $viewer->assignedClients()->attach($mine->id);

    $stranger = User::factory()->client()->create(['name' => 'Stranger Ltd']);

    return [$viewer, $mine, $stranger];
}

test('the picker names no client the viewer may not see', function () {
    [$viewer] = scopedClientManager([
        Permission::ManageClients->value,
        Permission::DeleteClients->value,
    ]);

    $props = $this->actingAs($viewer)->get('/clients')->assertOk()->viewData('page')['props'];

    $names = collect($props['reassign_candidates'])->pluck('name');

    // Two lines above it, the listing itself is narrowed to their roster.
    expect(collect($props['clients'])->pluck('name')->all())->toBe(['Mine'])
        ->and($names)->toContain('Mine')
        ->and($names)->not->toContain('Stranger Ltd');
});

test('the picker is not sent at all to somebody who may not delete', function () {
    [$viewer] = scopedClientManager([Permission::ManageClients->value]);

    $props = $this->actingAs($viewer)->get('/clients')->assertOk()->viewData('page')['props'];

    expect($props['reassign_candidates'])->toBe([]);
});

test('the same holds for the staff list', function () {
    $role = Role::query()->create(['name' => 'Staff lister']);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => Permission::ManageUsers->value],
    ]);
    $viewer = User::factory()->create(['role_id' => $role->id]);

    $props = $this->actingAs($viewer)->get('/users')->assertOk()->viewData('page')['props'];

    expect($props['reassign_candidates'])->toBe([]);
});

test('an unscoped administrator still gets every active account', function () {
    // The half that must not narrow: nothing changes for the role that
    // actually runs these screens.
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);
    $staff = User::factory()->role(SystemRole::ClientManager)->create(['name' => 'Sam Staff']);

    $props = $this->actingAs($this->admin)->get('/clients')->assertOk()->viewData('page')['props'];

    expect(collect($props['reassign_candidates'])->pluck('name'))
        ->toContain('Acme Ltd')
        ->toContain('Sam Staff')
        ->toContain($this->admin->name);
});
