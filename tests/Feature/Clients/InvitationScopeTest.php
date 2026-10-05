<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Models\Invitation;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Inertia\Testing\AssertableInertia;

/*
 * A staff member limited to some clients may only invite into the groups
 * those clients are in (InvitationController::create and store). The list
 * of invitations and revoking one must stop at the same line: otherwise the
 * list hands them every invitee's name and address, and revoking takes back
 * invitations other people sent (GHSA-phv7-54fm-qh4r).
 *
 * Theirs are the invitations they sent, and the ones into a group within
 * their reach. An unscoped staff member still sees and manages every one.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Scoped inviter', 'client_scoped' => true]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'create_clients']);
    $this->scoped = User::factory()->create(['role_id' => $role->id]);

    $mine = User::factory()->client()->create();
    $this->scoped->assignedClients()->sync([$mine->id]);

    $this->myGroup = Group::query()->create(['name' => 'My clients']);
    $this->myGroup->members()->attach($mine->id);
    $this->otherGroup = Group::query()->create(['name' => 'Board']);

    $this->sentByMe = Invitation::issue('mine@example.test', 'Mine', null, $this->scoped, now()->addDay());
    $this->intoMyGroup = Invitation::issue('colleague@example.test', 'Into my group', $this->myGroup, $this->admin, now()->addDay());
    $this->notMine = Invitation::issue('board@example.test', 'Board member', $this->otherGroup, $this->admin, now()->addDay());
    $this->noGroup = Invitation::issue('nogroup@example.test', 'No group', null, $this->admin, now()->addDay());
});

test('a client-scoped staff member lists only the invitations within their reach', function () {
    $this->actingAs($this->scoped)->get('/clients/invitations')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where(
            'invitations',
            fn ($rows) => collect($rows)->pluck('email')->sort()->values()->all() === ['colleague@example.test', 'mine@example.test'],
        ));
});

test('a client-scoped staff member cannot revoke an invitation outside their reach', function () {
    $this->actingAs($this->scoped)->delete("/clients/invitations/{$this->notMine->id}")->assertNotFound();
    $this->actingAs($this->scoped)->delete("/clients/invitations/{$this->noGroup->id}")->assertNotFound();

    expect($this->notMine->fresh()->status)->toBe(Invitation::STATUS_PENDING)
        ->and($this->noGroup->fresh()->status)->toBe(Invitation::STATUS_PENDING);
});

test('a client-scoped staff member can still revoke their own, and one into their group', function () {
    $this->actingAs($this->scoped)->delete("/clients/invitations/{$this->sentByMe->id}")->assertRedirect();
    $this->actingAs($this->scoped)->delete("/clients/invitations/{$this->intoMyGroup->id}")->assertRedirect();

    expect($this->sentByMe->fresh()->status)->toBe(Invitation::STATUS_REVOKED)
        ->and($this->intoMyGroup->fresh()->status)->toBe(Invitation::STATUS_REVOKED);
});

test('an unscoped staff member still sees and revokes every invitation', function () {
    $this->actingAs($this->admin)->get('/clients/invitations')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('invitations', 4));

    $this->actingAs($this->admin)->delete("/clients/invitations/{$this->notMine->id}")->assertRedirect();

    expect($this->notMine->fresh()->status)->toBe(Invitation::STATUS_REVOKED);
});
