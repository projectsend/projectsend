<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/**
 * A group belongs to the people in it, and a client-scoped staff member
 * holds only some of them.
 *
 * GroupMembershipScopeTest beside this one covers *reach*: what joining a
 * group would hand somebody. This covers the group object itself — being
 * told it exists, and renaming, deleting or publishing it. The two are
 * different questions and were answered by the same predicate, which only
 * asked the first.
 *
 * Reported as GHSA-r3hg-3fxw-rcmr.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::ManageGroups, Permission::EditGroups, Permission::DeleteGroups, Permission::CreateGroups] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id]);

    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine']);

    // The group at the centre of the report: somebody else's client is its
    // only member, and nothing has been shared with it yet — so every
    // "does this group reach past my library" check answers no, vacuously.
    $this->theirs = Group::query()->create(['name' => 'Theirs Only', 'slug' => 'theirs-only', 'public' => false]);
    $this->theirs->members()->syncWithoutDetaching([$this->stranger->id]);

    $this->ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $this->ours->members()->syncWithoutDetaching([$this->mine->id]);
});

function listedGroupNames(User $viewer): array
{
    $names = [];

    test()->actingAs($viewer)->get('/groups')->assertOk()->assertInertia(
        function (AssertableInertia $page) use (&$names) {
            $names = collect($page->toArray()['props']['groups']['data'] ?? $page->toArray()['props']['groups'])
                ->pluck('name')->all();
        },
    );

    return $names;
}

/*
|--------------------------------------------------------------------------
| Being told it exists
|--------------------------------------------------------------------------
*/

test('the listing hides a group made entirely of other people\'s clients', function () {
    expect(listedGroupNames($this->rep))->not->toContain('Theirs Only');
});

test('the listing still shows a group holding one of their own clients', function () {
    expect(listedGroupNames($this->rep))->toContain('Ours');
});

test('an unscoped administrator still sees every group', function () {
    expect(listedGroupNames($this->admin))->toContain('Theirs Only')->toContain('Ours');
});

test('the API listing hides it too', function () {
    Sanctum::actingAs($this->rep, ['manage_groups']);

    $names = collect($this->getJson('/api/v1/groups')->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->not->toContain('Theirs Only')->toContain('Ours');
});

/*
|--------------------------------------------------------------------------
| Changing it
|--------------------------------------------------------------------------
|
| The higher half of the report. Renaming, deleting or publishing a group
| lands on every member, and none of this group's members are theirs.
*/

test('they cannot open the edit form for it', function () {
    $this->actingAs($this->rep)->get("/groups/{$this->theirs->id}")->assertNotFound();
});

test('they cannot rename it', function () {
    $this->actingAs($this->rep)
        ->patch("/groups/{$this->theirs->id}", ['name' => 'Renamed', 'public' => false])
        ->assertNotFound();

    expect($this->theirs->fresh()->name)->toBe('Theirs Only');
});

test('they cannot publish it', function () {
    // The consequence the report calls the serious one: a public group
    // becomes an anonymous listing for whatever is shared with it later.
    $this->actingAs($this->rep)
        ->patch("/groups/{$this->theirs->id}", ['name' => 'Theirs Only', 'public' => true])
        ->assertNotFound();

    expect($this->theirs->fresh()->public)->toBeFalse();
});

test('they cannot delete it out from under its members', function () {
    $this->actingAs($this->rep)->delete("/groups/{$this->theirs->id}")->assertNotFound();

    expect(Group::query()->whereKey($this->theirs->id)->exists())->toBeTrue();
});

test('the API refuses the same three', function () {
    Sanctum::actingAs($this->rep, ['edit_groups', 'delete_groups']);

    $this->getJson("/api/v1/groups/{$this->theirs->id}")->assertNotFound();
    $this->patchJson("/api/v1/groups/{$this->theirs->id}", ['name' => 'Renamed'])->assertNotFound();
    $this->deleteJson("/api/v1/groups/{$this->theirs->id}")->assertNotFound();

    expect($this->theirs->fresh()->name)->toBe('Theirs Only');
});

/*
|--------------------------------------------------------------------------
| What must keep working
|--------------------------------------------------------------------------
|
| The pins. The fix suggested in the report puts the membership check
| inside groupReachesNoFurther(), which allowsGroupMembership() also
| calls — and that would have broken both of these.
*/

test('they can still rename a group of their own client', function () {
    $this->actingAs($this->rep)
        ->patch("/groups/{$this->ours->id}", ['name' => 'Ours Renamed', 'public' => false])
        ->assertRedirect();

    expect($this->ours->fresh()->name)->toBe('Ours Renamed');
});

test('they can still put the first member into a group they just made', function () {
    // A group nobody has joined belongs to nobody, and this is the case
    // StaffLibraryScope's own docblock says must keep working.
    $fresh = Group::query()->create(['name' => 'Brand New', 'slug' => 'brand-new', 'public' => false]);

    $this->actingAs($this->rep)
        ->post("/groups/{$fresh->id}/members", ['user_id' => $this->mine->id])
        ->assertRedirect();

    expect($fresh->members()->pluck('users.id')->all())->toContain($this->mine->id);
});

test('they can still rename an empty group', function () {
    $fresh = Group::query()->create(['name' => 'Brand New', 'slug' => 'brand-new', 'public' => false]);

    $this->actingAs($this->rep)
        ->patch("/groups/{$fresh->id}", ['name' => 'Named At Last', 'public' => false])
        ->assertRedirect();

    expect($fresh->fresh()->name)->toBe('Named At Last');
});

test('a mixed group stays changeable, with its stranger unnamed', function () {
    // The boundary between this report and GHSA-whmp-p9hv-r7j7. "Every
    // member must be mine" is the obvious reading of the fix and it is
    // wrong: it turns that advisory's narrowing back into a 404. A group
    // holding one of their clients is theirs to work with; what protects
    // the stranger in it is that they are never named, and that anything
    // shared with the group beyond this viewer's library still refuses
    // the change.
    $mixed = Group::query()->create(['name' => 'Mixed', 'slug' => 'mixed', 'public' => false]);
    $mixed->members()->syncWithoutDetaching([$this->mine->id, $this->stranger->id]);

    $this->actingAs($this->rep)
        ->patch("/groups/{$mixed->id}", ['name' => 'Mixed Renamed', 'public' => false])
        ->assertRedirect();

    expect($mixed->fresh()->name)->toBe('Mixed Renamed');
});

test('an unscoped administrator can still change anything', function () {
    $this->actingAs($this->admin)
        ->patch("/groups/{$this->theirs->id}", ['name' => 'Admin Renamed', 'public' => false])
        ->assertRedirect();

    expect($this->theirs->fresh()->name)->toBe('Admin Renamed');
});
