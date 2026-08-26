<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
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
