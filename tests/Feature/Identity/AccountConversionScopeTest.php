<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\UserType;
use Illuminate\Support\Str;

/**
 * Promoting a client to staff binds a client account, so it is inside
 * the same boundary as every other route that binds one: the clients
 * assigned to this staff member. guardToStaff() deliberately skips
 * StaffAccounts::guardTarget, and the reason it gives is about the role
 * being granted -- which leaves the target unasked about.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $this->role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::ManageUsers, Permission::EditUsers, Permission::EditClients, Permission::CreateUsers] as $permission) {
        RolePermission::query()->create(['role_id' => $this->role->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $this->role->id]);
    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->rep->assignedClients()->sync([$this->mine->id]);

    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine']);
});

test('a scoped staff member cannot promote a client outside their roster', function () {
    $this->actingAs($this->rep)->post("/users/convert/{$this->stranger->id}", [
        'direction' => 'to_staff',
        'role_id' => $this->role->id,
        'assigned_clients' => [],
    ])->assertNotFound();

    $after = $this->stranger->fresh();

    expect($after->type)->toBe(UserType::Client)
        ->and($after->role_id)->not->toBe($this->role->id);
});

test('everything a promotion would have done to a stranger client is left alone', function () {
    $colleague = User::factory()->create(['role_id' => $this->role->id]);
    $colleague->assignedClients()->sync([$this->stranger->id]);

    $group = Group::query()->create(['name' => 'Theirs', 'slug' => 'theirs', 'public' => false]);
    $group->members()->syncWithoutDetaching([$this->stranger->id]);

    $this->actingAs($this->rep)->post("/users/convert/{$this->stranger->id}", [
        'direction' => 'to_staff',
        'role_id' => $this->role->id,
        'assigned_clients' => [],
    ])->assertNotFound();

    // A promotion clears every roster row pointing at the account and
    // leaves its group memberships inert. Neither happened, and nothing
    // was written to the log.
    expect($colleague->assignedClients()->pluck('users.id')->all())->toBe([$this->stranger->id])
        ->and($group->members()->pluck('users.id')->all())->toBe([$this->stranger->id])
        ->and(ActivityLog::query()->where('action', Action::AccountConvertedToStaff->value)->count())->toBe(0);
});

test('the refusal does not distinguish a stranger client from one that is not there', function () {
    $this->actingAs($this->rep)->post('/users/convert/999999', [
        'direction' => 'to_staff',
        'role_id' => $this->role->id,
    ])->assertNotFound();
});

test('a scoped staff member may still promote a client of their own', function () {
    $this->actingAs($this->rep)->post("/users/convert/{$this->mine->id}", [
        'direction' => 'to_staff',
        'role_id' => $this->role->id,
        'assigned_clients' => [],
    ])->assertSessionHasNoErrors();

    $after = $this->mine->fresh();

    expect($after->type)->toBe(UserType::Staff)
        ->and($after->role_id)->toBe($this->role->id);
});

test('unscoped staff promote any client, as before', function () {
    $this->actingAs($this->admin)->post("/users/convert/{$this->stranger->id}", [
        'direction' => 'to_staff',
        'role_id' => $this->role->id,
        'assigned_clients' => [],
    ])->assertSessionHasNoErrors();

    expect($this->stranger->fresh()->type)->toBe(UserType::Staff);
});

test('the demotion direction keeps answering through guardTarget', function () {
    $colleague = User::factory()->create();

    $this->actingAs($this->rep)->post("/users/convert/{$colleague->id}", [
        'direction' => 'to_client',
    ])->assertForbidden();

    expect($colleague->fresh()->type)->toBe(UserType::Staff);
});
