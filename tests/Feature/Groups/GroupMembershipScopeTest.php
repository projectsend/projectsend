<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * Group membership decides what a client can reach, and through
 * File::scopeVisibleToClient it decides what the staff member holding
 * that client can reach too. The routes that edit it were gated on
 * edit_groups and nothing else.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::EditGroups, Permission::CreateGroups, Permission::Upload, Permission::EditFiles] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id]);

    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine']);

    $this->strangerGroup = Group::query()->create(['name' => 'Theirs', 'slug' => 'theirs', 'public' => false]);
    $this->strangerGroup->members()->syncWithoutDetaching([$this->stranger->id]);

    $this->secret = uploadNamedFile($this->admin, 'stranger-secret');
    shareFileWithGroup($this->secret, $this->strangerGroup);
});

function libraryHolds(User $rep, int $fileId): bool
{
    return in_array($fileId, app(StaffLibraryScope::class)->files($rep)->pluck('id')->all(), true);
}

test('a scoped staff member cannot widen their own library through a group', function () {
    expect(libraryHolds($this->rep, $this->secret->id))->toBeFalse();
    $this->actingAs($this->rep)->get("/files/{$this->secret->id}/download")->assertForbidden();

    $this->actingAs($this->rep)
        ->post("/groups/{$this->strangerGroup->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();

    expect($this->strangerGroup->members()->count())->toBe(1)
        ->and(libraryHolds($this->rep, $this->secret->id))->toBeFalse();

    $this->actingAs($this->rep)->get("/files/{$this->secret->id}/download")->assertForbidden();
});

test('the API twin refuses it too', function () {
    $token = $this->rep->createToken('t', [Permission::EditGroups->value])->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/groups/{$this->strangerGroup->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();

    expect($this->strangerGroup->members()->count())->toBe(1);
});

test('a scoped staff member cannot hand somebody else client the files of their own', function () {
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->mine->id]);

    $this->actingAs($this->rep)
        ->post("/groups/{$ours->id}/members", ['user_id' => $this->stranger->id])
        ->assertForbidden();

    expect($ours->members()->pluck('users.id')->all())->toBe([$this->mine->id]);
});

test('a scoped staff member cannot pull somebody else client out of a group', function () {
    $this->actingAs($this->rep)
        ->delete("/groups/{$this->strangerGroup->id}/members/{$this->stranger->id}")
        ->assertForbidden();

    $token = $this->rep->createToken('t', [Permission::EditGroups->value])->plainTextToken;
    $this->withToken($token)
        ->deleteJson("/api/v1/groups/{$this->strangerGroup->id}/members/{$this->stranger->id}")
        ->assertForbidden();

    expect($this->strangerGroup->members()->count())->toBe(1);
});

test('approving a membership request is held to the same boundary', function () {
    $role = $this->rep->role;
    RolePermission::query()->create([
        'role_id' => $role->id,
        'permission' => Permission::ApproveGroupsMembershipsRequests->value,
    ]);

    $request = MembershipRequest::query()->create([
        'group_id' => $this->strangerGroup->id,
        'user_id' => $this->mine->id,
        'status' => MembershipRequest::STATUS_PENDING,
    ]);

    $this->actingAs($this->rep)->post("/membership-requests/{$request->id}/approve")->assertNotFound();

    expect($this->strangerGroup->members()->count())->toBe(1)
        ->and(libraryHolds($this->rep, $this->secret->id))->toBeFalse();
});

test('denying somebody else client request is held to the same boundary', function () {
    RolePermission::query()->create([
        'role_id' => $this->rep->role_id,
        'permission' => Permission::ApproveGroupsMembershipsRequests->value,
    ]);

    $request = MembershipRequest::query()->create([
        'group_id' => $this->strangerGroup->id,
        'user_id' => $this->stranger->id,
        'status' => MembershipRequest::STATUS_PENDING,
    ]);

    $this->actingAs($this->rep)->delete("/membership-requests/{$request->id}")->assertNotFound();

    expect($request->fresh()->status)->toBe(MembershipRequest::STATUS_PENDING);
});

test('the queue stops naming clients this viewer has no business hearing about', function () {
    RolePermission::query()->create([
        'role_id' => $this->rep->role_id,
        'permission' => Permission::ApproveGroupsMembershipsRequests->value,
    ]);

    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => true]);
    MembershipRequest::query()->create(['group_id' => $ours->id, 'user_id' => $this->mine->id, 'status' => MembershipRequest::STATUS_PENDING]);
    MembershipRequest::query()->create(['group_id' => $ours->id, 'user_id' => $this->stranger->id, 'status' => MembershipRequest::STATUS_PENDING]);

    $body = $this->actingAs($this->rep)->get('/membership-requests')->getContent();

    // The row carries client_name and client_email, so an unnarrowed
    // queue hands over both for a client outside the roster.
    expect(str_contains($body, 'Not Mine'))->toBeFalse()
        ->and(str_contains($body, $this->stranger->email))->toBeFalse()
        ->and(str_contains($body, 'Mine'))->toBeTrue();
});

test('the sidebar badge counts what the queue lists', function () {
    RolePermission::query()->create([
        'role_id' => $this->rep->role_id,
        'permission' => Permission::ApproveGroupsMembershipsRequests->value,
    ]);

    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => true]);
    MembershipRequest::query()->create(['group_id' => $ours->id, 'user_id' => $this->mine->id, 'status' => MembershipRequest::STATUS_PENDING]);
    MembershipRequest::query()->create(['group_id' => $ours->id, 'user_id' => $this->stranger->id, 'status' => MembershipRequest::STATUS_PENDING]);

    $this->actingAs($this->rep)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('pending.membership_requests', 1),
    );

    // Unscoped staff are told about both, and see both.
    $wide = Role::query()->create(['name' => 'Wide '.Str::random(6), 'client_scoped' => false]);
    RolePermission::query()->create(['role_id' => $wide->id, 'permission' => Permission::ApproveGroupsMembershipsRequests->value]);
    $manager = User::factory()->create(['role_id' => $wide->id]);

    $this->actingAs($manager)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('pending.membership_requests', 2),
    );
});

test('a group nobody has shared anything with can still be populated', function () {
    $fresh = Group::query()->create(['name' => 'Brand New', 'slug' => 'brand-new', 'public' => false]);

    $this->actingAs($this->rep)
        ->post("/groups/{$fresh->id}/members", ['user_id' => $this->mine->id])
        ->assertRedirect();

    expect($fresh->members()->pluck('users.id')->all())->toBe([$this->mine->id]);
});

test('a group already holding the actor own client stays editable', function () {
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->mine->id]);

    $ourFile = uploadNamedFile($this->admin, 'our-brochure');
    shareFileWithGroup($ourFile, $ours);

    $second = User::factory()->client()->create(['name' => 'Also Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id, $second->id]);

    $this->actingAs($this->rep)
        ->post("/groups/{$ours->id}/members", ['user_id' => $second->id])
        ->assertRedirect();

    $this->actingAs($this->rep)
        ->delete("/groups/{$ours->id}/members/{$second->id}")
        ->assertRedirect();

    expect($ours->members()->pluck('users.id')->all())->toBe([$this->mine->id]);
});

test('unscoped staff manage membership exactly as before', function () {
    $role = Role::query()->create(['name' => 'Wide '.Str::random(6), 'client_scoped' => false]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => Permission::EditGroups->value]);
    $manager = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($manager)
        ->post("/groups/{$this->strangerGroup->id}/members", ['user_id' => $this->mine->id])
        ->assertRedirect();

    expect($this->strangerGroup->members()->count())->toBe(2);

    $this->actingAs($manager)
        ->delete("/groups/{$this->strangerGroup->id}/members/{$this->stranger->id}")
        ->assertRedirect();

    expect($this->strangerGroup->members()->pluck('users.id')->all())->toBe([$this->mine->id]);
});

// A folder shared with a group hands over its whole subtree, so the guard
// has to ask about the contents and not only about the folder named in
// the assignment. The docblock on groupReachesNoFurther() already claims
// it does ("the folders whose subtrees it can browse").
test('a subfolder outside the library counts as reach as well', function () {
    // The rep's own folder, so it is in their library by "own uploads".
    $parent = app(FolderService::class)->create('Rep Folder', null);
    $parent->forceFill(['created_by' => $this->rep->id])->save();

    // Somebody else's subfolder inside it, which is not.
    $child = app(FolderService::class)->create('Admin Subfolder', $parent);
    $child->forceFill(['created_by' => $this->admin->id])->save();

    $group = Group::query()->create(['name' => 'Newsletter', 'slug' => 'newsletter', 'public' => false]);
    FolderAssignment::query()->create([
        'folder_id' => $parent->id,
        'assignable_type' => $group->getMorphClass(),
        'assignable_id' => $group->id,
    ]);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();

    expect($group->members()->count())->toBe(0);
});

test('a stranger file inside the rep own folder counts as reach', function () {
    // Every folder in the subtree is theirs, and the file in it is not:
    // files() is own uploads plus what an assigned client may see, and
    // somebody else's upload into a folder this rep happens to own is
    // neither. Adding their own client would have handed it over -- and
    // handed it to the rep too, since files() then includes it.
    $parent = app(FolderService::class)->create('Rep Folder', null);
    $parent->forceFill(['created_by' => $this->rep->id])->save();

    $secret = uploadNamedFile($this->admin, 'stranger-secret');
    $secret->forceFill(['folder_id' => $parent->id])->save();

    expect(libraryHolds($this->rep, $secret->id))->toBeFalse();

    $group = Group::query()->create(['name' => 'Bulletin', 'slug' => 'bulletin', 'public' => false]);
    FolderAssignment::query()->create([
        'folder_id' => $parent->id,
        'assignable_type' => $group->getMorphClass(),
        'assignable_id' => $group->id,
    ]);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();

    expect($group->members()->count())->toBe(0);
});

test('a subtree that is wholly inside the library stays manageable', function () {
    // The half that must not harden: everything shared with this group is
    // reachable by the rep, subfolder and file alike, so the group is
    // theirs to populate.
    $parent = app(FolderService::class)->create('Ours', null);
    $parent->forceFill(['created_by' => $this->rep->id])->save();

    $child = app(FolderService::class)->create('Ours Too', $parent);
    $child->forceFill(['created_by' => $this->rep->id])->save();

    $own = uploadNamedFile($this->rep, 'our-own-file');
    $own->forceFill(['folder_id' => $child->id])->save();

    $group = Group::query()->create(['name' => 'Ours', 'slug' => 'ours-group', 'public' => false]);
    FolderAssignment::query()->create([
        'folder_id' => $parent->id,
        'assignable_type' => $group->getMorphClass(),
        'assignable_id' => $group->id,
    ]);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $this->mine->id])
        ->assertRedirect();

    expect($group->members()->count())->toBe(1);
});

test('a folder shared with a group counts as reach too', function () {
    $folder = app(FolderService::class)->create('Their Folder', null);
    FolderAssignment::query()->create([
        'folder_id' => $folder->id,
        'assignable_type' => $this->strangerGroup->getMorphClass(),
        'assignable_id' => $this->strangerGroup->id,
    ]);

    $bare = Group::query()->create(['name' => 'Folder Only', 'slug' => 'folder-only', 'public' => false]);
    FolderAssignment::query()->create([
        'folder_id' => $folder->id,
        'assignable_type' => $bare->getMorphClass(),
        'assignable_id' => $bare->id,
    ]);

    $this->actingAs($this->rep)
        ->post("/groups/{$bare->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();

    expect($bare->members()->count())->toBe(0);
});

// An assignment row outlives the file it points at — nothing clears them
// on delete — and a trashed file can never appear in files(). Asking
// "is anything outside my library" from the live row rather than counting
// assignment rows is what keeps a group usable after somebody deletes a
// file that was once shared with it.
test('a group is not locked shut by a file that has since been deleted', function () {
    $group = Group::query()->create(['name' => 'Newsletter', 'slug' => 'newsletter', 'public' => false]);
    $group->members()->syncWithoutDetaching([$this->mine->id]);

    // Shared with the group, and reachable by this rep because their own
    // client is a member — so the group is theirs to manage.
    $file = uploadNamedFile($this->admin, 'seasonal-offer');
    shareFileWithGroup($file, $group);

    $second = User::factory()->client()->create(['name' => 'Also Mine']);
    $this->rep->assignedClients()->attach($second->id);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $second->id])
        ->assertRedirect();

    // The uploader deletes it. The assignment row stays behind.
    $file->delete();

    $third = User::factory()->client()->create(['name' => 'Mine Too']);
    $this->rep->assignedClients()->attach($third->id);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $third->id])
        ->assertRedirect();

    expect($group->members()->count())->toBe(3);

    // And taking somebody out again still works, which the count form
    // also blocked.
    $this->actingAs($this->rep)
        ->delete("/groups/{$group->id}/members/{$third->id}")
        ->assertRedirect();

    expect($group->members()->count())->toBe(2);
});

test('a deleted folder assignment does not lock a group either', function () {
    $group = Group::query()->create(['name' => 'Bulletin', 'slug' => 'bulletin', 'public' => false]);
    $group->members()->syncWithoutDetaching([$this->mine->id]);

    $folder = app(FolderService::class)->create('Seasonal', null);
    FolderAssignment::query()->create([
        'folder_id' => $folder->id,
        'assignable_type' => $group->getMorphClass(),
        'assignable_id' => $group->id,
    ]);

    $folder->delete();

    $second = User::factory()->client()->create(['name' => 'Second']);
    $this->rep->assignedClients()->attach($second->id);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $second->id])
        ->assertRedirect();

    expect($group->members()->count())->toBe(2);
});

// The half that must not soften: a live file outside the library is still
// reach, deleted siblings or not.
test('a deleted file does not excuse a live one that is still out of reach', function () {
    $dead = uploadNamedFile($this->admin, 'was-shared');
    shareFileWithGroup($dead, $this->strangerGroup);
    $dead->delete();

    $this->actingAs($this->rep)
        ->post("/groups/{$this->strangerGroup->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();
});

// Expiry is the same answer for the same reason: membership grants nobody
// access to an expired file, because scopeVisibleToClient ends in
// notExpired(). It is only invisible to files(), which read as reach.
test('a group is not locked shut by a file that has since expired', function () {
    $group = Group::query()->create(['name' => 'Seasonal', 'slug' => 'seasonal', 'public' => false]);
    $group->members()->syncWithoutDetaching([$this->mine->id]);

    $file = uploadNamedFile($this->admin, 'summer-offer');
    shareFileWithGroup($file, $group);

    $second = User::factory()->client()->create(['name' => 'Also Mine']);
    $this->rep->assignedClients()->attach($second->id);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $second->id])
        ->assertRedirect();

    $file->update(['expires_at' => now()->subDay()]);

    // Nobody in the group can reach it any more — the premise of the rule.
    $this->actingAs($this->mine)->get("/files/{$file->id}/download")->assertForbidden();

    $third = User::factory()->client()->create(['name' => 'Mine Too']);
    $this->rep->assignedClients()->attach($third->id);

    $this->actingAs($this->rep)
        ->post("/groups/{$group->id}/members", ['user_id' => $third->id])
        ->assertRedirect();

    expect($group->members()->count())->toBe(3);

    // And taking their own client out again, which is the part that reads
    // worst: the rep could not undo their own membership change.
    $this->actingAs($this->rep)
        ->delete("/groups/{$group->id}/members/{$third->id}")
        ->assertRedirect();

    expect($group->members()->count())->toBe(2);
});

test('an expired file does not excuse a live one that is still out of reach', function () {
    $expired = uploadNamedFile($this->admin, 'was-current');
    shareFileWithGroup($expired, $this->strangerGroup);
    $expired->update(['expires_at' => now()->subDay()]);

    // $this->secret is live, shared with the same group and outside this
    // rep's library — the guard still has to answer no.
    $this->actingAs($this->rep)
        ->post("/groups/{$this->strangerGroup->id}/members", ['user_id' => $this->mine->id])
        ->assertForbidden();
});


/**
 * The rep role ships without delete_groups, and these cases are about the
 * boundary rather than the permission: grant it so the route lets the
 * request through and the guard is what answers.
 */
function grantGroupDeletion(User $rep): void
{
    RolePermission::query()->firstOrCreate([
        'role_id' => $rep->role_id,
        'permission' => Permission::DeleteGroups->value,
    ]);
}

// #1701 drew the line for membership and left the group object
// installation-wide. Deleting one is the sharper end of the same
// question: an assignment to a group is how its members reach a file, so
// removing the group takes that access away from every member — measured
// before this guard, a scoped role deleted a stranger's group and the
// stranger's client stopped seeing the file it carried.
test('a scoped staff member cannot rename or delete a group out of their reach', function () {
    grantGroupDeletion($this->rep);

    $this->actingAs($this->rep)
        ->patch("/groups/{$this->strangerGroup->id}", [
            'name' => 'Renamed By Somebody Else',
            'slug' => 'theirs',
            'description' => null,
            'public' => false,
        ])
        ->assertNotFound();

    $this->actingAs($this->rep)
        ->delete("/groups/{$this->strangerGroup->id}")
        ->assertNotFound();

    expect($this->strangerGroup->fresh()->name)->toBe('Theirs')
        ->and(Group::query()->whereKey($this->strangerGroup->id)->exists())->toBeTrue();
});

test('deleting a stranger group would have cost its members their access', function () {
    grantGroupDeletion($this->rep);

    $stranger = $this->strangerGroup->members()->first();

    expect(File::query()->visibleToClient($stranger)->whereKey($this->secret->id)->exists())->toBeTrue();

    $this->actingAs($this->rep)->delete("/groups/{$this->strangerGroup->id}")->assertNotFound();

    // Still theirs to read, because the group is still there.
    expect(File::query()->visibleToClient($stranger)->whereKey($this->secret->id)->exists())->toBeTrue();
});

test('the API refuses the same two', function () {
    grantGroupDeletion($this->rep);

    Sanctum::actingAs($this->rep, ['edit_groups', 'delete_groups']);

    $this->patchJson("/api/v1/groups/{$this->strangerGroup->id}", ['name' => 'Nope'])->assertNotFound();
    $this->deleteJson("/api/v1/groups/{$this->strangerGroup->id}")->assertNotFound();

    expect(Group::query()->whereKey($this->strangerGroup->id)->exists())->toBeTrue();
});

test('a group inside their reach stays theirs to rename and delete', function () {
    grantGroupDeletion($this->rep);

    $mine = Group::query()->create(['name' => 'Mine', 'slug' => 'mine', 'public' => false]);
    $mine->members()->syncWithoutDetaching([$this->mine->id]);

    $this->actingAs($this->rep)
        ->patch("/groups/{$mine->id}", ['name' => 'Mine, Renamed', 'slug' => 'mine', 'description' => null, 'public' => false])
        ->assertRedirect();

    expect($mine->fresh()->name)->toBe('Mine, Renamed');

    $this->actingAs($this->rep)->delete("/groups/{$mine->id}")->assertRedirect();

    expect(Group::query()->whereKey($mine->id)->exists())->toBeFalse();
});

test('an unscoped administrator manages every group exactly as before', function () {
    $this->actingAs($this->admin)
        ->patch("/groups/{$this->strangerGroup->id}", [
            'name' => 'Renamed By An Admin',
            'slug' => 'theirs',
            'description' => null,
            'public' => false,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)->delete("/groups/{$this->strangerGroup->id}")->assertRedirect();

    expect(Group::query()->whereKey($this->strangerGroup->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The screen that edits a group, and its API twin
|--------------------------------------------------------------------------
*/

test('a group reaching past the library cannot even be opened', function () {
    // update() and destroy() already refuse this group. Reading it was the
    // one group route that did not.
    $this->actingAs($this->rep)->get("/groups/{$this->strangerGroup->id}")->assertNotFound();

    $token = $this->rep->createToken('t', [Permission::EditGroups->value])->plainTextToken;
    $this->withToken($token)->getJson("/api/v1/groups/{$this->strangerGroup->id}")->assertNotFound();
});

test('the edit screen stops naming clients this viewer has no business hearing about', function () {
    // Nothing is shared with this group, so it reaches nowhere and stays
    // open to the rep — which is exactly the case a reach guard alone
    // would leave holding a stranger's address.
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->mine->id, $this->stranger->id]);

    $props = $this->actingAs($this->rep)->get("/groups/{$ours->id}")->assertOk()->viewData('page')['props'];

    expect(array_column($props['members'], 'name'))->toBe(['Mine'])
        ->and(array_column($props['members'], 'email'))->toBe([$this->mine->email])
        // Every client the rep may reach is already in the group, so there
        // is nobody left to add — rather than the stranger, whom they could
        // not have added anyway.
        ->and($props['available_clients'])->toBe([]);
});

test('the API twin narrows the membership it hands back', function () {
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->mine->id, $this->stranger->id]);

    $token = $this->rep->createToken('t', [Permission::EditGroups->value])->plainTextToken;
    $data = $this->withToken($token)->getJson("/api/v1/groups/{$ours->id}")->assertOk()->json('data');

    expect(array_column($data['members'], 'name'))->toBe(['Mine'])
        // members_count is left whole on purpose: a size is not an
        // identity, and it is the same number the group listing reports.
        ->and($data['members_count'])->toBe(2);
});

test('the API narrows the membership a member write hands back, the same way', function () {
    // The read above is narrowed; changing the membership went through the
    // same resource with the relation loaded whole, so the write handed
    // back what the read refuses to.
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->stranger->id]);

    $token = $this->rep->createToken('t', [Permission::EditGroups->value])->plainTextToken;

    $data = $this->withToken($token)
        ->postJson("/api/v1/groups/{$ours->id}/members", ['user_id' => $this->mine->id])
        ->assertOk()
        ->json('data');

    expect(array_column($data['members'], 'name'))->toBe(['Mine'])
        ->and($data['members_count'])->toBe(2);
});

test('and on the way back out again', function () {
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $second = User::factory()->client()->create(['name' => 'Also Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id, $second->id]);
    $ours->members()->syncWithoutDetaching([$this->mine->id, $second->id, $this->stranger->id]);

    $token = $this->rep->createToken('t', [Permission::EditGroups->value])->plainTextToken;

    $data = $this->withToken($token)
        ->deleteJson("/api/v1/groups/{$ours->id}/members/{$second->id}")
        ->assertOk()
        ->json('data');

    expect(array_column($data['members'], 'name'))->toBe(['Mine'])
        ->and($data['members_count'])->toBe(2);
});

test('an unscoped token keeps every member in the write response', function () {
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->stranger->id]);

    $token = $this->admin->createToken('t', [Permission::EditGroups->value])->plainTextToken;

    $data = $this->withToken($token)
        ->postJson("/api/v1/groups/{$ours->id}/members", ['user_id' => $this->mine->id])
        ->assertOk()
        ->json('data');

    expect(array_column($data['members'], 'name'))->toBe(['Mine', 'Not Mine']);
});

test('an unscoped viewer keeps the whole roster and every member', function () {
    $ours = Group::query()->create(['name' => 'Ours', 'slug' => 'ours', 'public' => false]);
    $ours->members()->syncWithoutDetaching([$this->mine->id]);

    $props = $this->actingAs($this->admin)->get("/groups/{$ours->id}")->assertOk()->viewData('page')['props'];

    expect(array_column($props['members'], 'name'))->toBe(['Mine'])
        ->and(array_column($props['available_clients'], 'name'))->toBe(['Not Mine']);

    $this->actingAs($this->admin)->get("/groups/{$this->strangerGroup->id}")->assertOk();
});
