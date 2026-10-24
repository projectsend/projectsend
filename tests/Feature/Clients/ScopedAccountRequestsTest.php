<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;

/*
|--------------------------------------------------------------------------
| The account-request queue follows the reviewer's client scope
|--------------------------------------------------------------------------
|
| approve_account_requests on a client-scoped role reaches only that role's
| clients. A self-registered account is assigned to nobody, so for a
| scoped reviewer the queue is empty and every unrelated request is a 404.
*/

beforeEach(function () {
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Scoped reviewer', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => Permission::ApproveAccountRequests->value],
    ]);
    $this->reviewer = User::factory()->create(['role_id' => $role->id]);

    $this->theirs = User::factory()->pendingClient()->create(['name' => 'Assigned Applicant']);
    $this->reviewer->assignedClients()->attach($this->theirs->id);

    $this->stranger = User::factory()->pendingClient()->create(['name' => 'Unrelated Applicant']);
});

test('a scoped reviewer sees only their own clients in the queue', function () {
    $props = $this->actingAs($this->reviewer)->get('/account-requests')->assertOk()->viewData('page')['props'];

    expect(collect($props['requests'])->pluck('name')->all())->toBe(['Assigned Applicant'])
        ->and($props['pending']['account_requests'])->toBe(1);
});

test('a search does not reach past the scope', function () {
    $props = $this->actingAs($this->reviewer)->get('/account-requests?search=Unrelated')->assertOk()->viewData('page')['props'];

    expect($props['requests'])->toBe([]);
});

test('a scoped reviewer cannot approve an unrelated request', function () {
    $this->actingAs($this->reviewer)->post("/account-requests/{$this->stranger->id}/approve")->assertNotFound();

    $this->stranger->refresh();
    expect($this->stranger->active)->toBeFalse()
        ->and($this->stranger->account_requested)->toBeTrue();
});

test('a scoped reviewer cannot deny an unrelated request', function () {
    $this->actingAs($this->reviewer)->delete("/account-requests/{$this->stranger->id}")->assertNotFound();

    expect(User::query()->find($this->stranger->id))->not->toBeNull();
});

test('a scoped reviewer can still approve and deny their own clients', function () {
    $this->actingAs($this->reviewer)->post("/account-requests/{$this->theirs->id}/approve")->assertRedirect();
    expect($this->theirs->refresh()->active)->toBeTrue();

    $another = User::factory()->pendingClient()->create();
    $this->reviewer->assignedClients()->attach($another->id);

    $this->actingAs($this->reviewer)->delete("/account-requests/{$another->id}")->assertRedirect();
    expect(User::query()->find($another->id))->toBeNull();
});

test('an unscoped administrator still sees and acts on every request', function () {
    $props = $this->actingAs($this->admin)->get('/account-requests')->assertOk()->viewData('page')['props'];

    expect(collect($props['requests'])->pluck('name')->sort()->values()->all())
        ->toBe(['Assigned Applicant', 'Unrelated Applicant'])
        ->and($props['pending']['account_requests'])->toBe(2);

    $this->actingAs($this->admin)->post("/account-requests/{$this->stranger->id}/approve")->assertRedirect();
    expect($this->stranger->refresh()->active)->toBeTrue();
});
