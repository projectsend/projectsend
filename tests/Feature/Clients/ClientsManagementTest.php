<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Capabilities\Edition;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| A scoped creator keeps what they create
|--------------------------------------------------------------------------
*/

test('a client-scoped creator can open the client they just made', function () {
    // guardTarget() answers 404 for anything off the roster, and
    // StaffLibraryScope::clients() leaves it out of the list -- so without
    // the roster entry the record exists, is welcomed by email, and is
    // invisible to the person who created it.
    $role = Role::query()->create(['name' => 'Scoped creator', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => Permission::ManageClients->value],
        ['role_id' => $role->id, 'permission' => Permission::CreateClients->value],
        ['role_id' => $role->id, 'permission' => Permission::EditClients->value],
    ]);
    $creator = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($creator)->post('/clients', [
        'name' => 'Brand New',
        'email' => 'brand-new@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect();

    $client = User::query()->where('email', 'brand-new@example.com')->sole();

    $this->actingAs($creator)->get("/clients/{$client->id}")->assertOk();

    $props = $this->actingAs($creator)->get('/clients')->assertOk()->viewData('page')['props'];
    expect(collect($props['clients'])->pluck('name')->all())->toContain('Brand New');
});

test('an unscoped creator gains no roster entry from creating a client', function () {
    // Nothing to add to: an unscoped staff member sees every client
    // already, and a roster entry would change what assignedClients means
    // for them.
    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Also New',
        'email' => 'also-new@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect();

    expect($this->admin->refresh()->assignedClients()->count())->toBe(0);
});

test('the index lists clients only — staff never appear', function () {
    User::factory()->client()->create(['name' => 'A Client']);
    User::factory()->role(SystemRole::Uploader)->create(['name' => 'A Staffer']);

    $this->actingAs($this->admin)->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('clients/index')
            ->has('clients', 1)
            ->where('clients.0.name', 'A Client'),
    );
});

test('a staff-created client is active with the Client role', function () {
    $response = $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Handmade Client',
        'email' => 'made@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $client = User::query()->where('email', 'made@example.com')->sole();
    $response->assertRedirect(route('clients.edit', $client));
    expect($client->type)->toBe(UserType::Client)
        ->and($client->active)->toBeTrue()
        ->and($client->role?->name)->toBe('Client');
});

test('staff accounts are unreachable through client screens', function () {
    $staffer = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($this->admin);
    $this->get("/clients/{$staffer->id}")->assertNotFound();
    $this->patch("/clients/{$staffer->id}", [])->assertNotFound();
    $this->delete("/clients/{$staffer->id}")->assertNotFound();
});

test('activating a pending client through the edit screen clears the request flag', function () {
    $pending = User::factory()->pendingClient()->create();

    $this->actingAs($this->admin)->patch("/clients/{$pending->id}", [
        'name' => $pending->name,
        'email' => $pending->email,
        'active' => true,
    ])->assertRedirect();

    $pending->refresh();
    expect($pending->active)->toBeTrue()
        ->and($pending->account_requested)->toBeFalse();
});

test('an account manager can manage clients but an uploader cannot', function () {
    // Account Manager holds create/edit/delete_clients + approve_account_requests.
    $manager = User::factory()->role(SystemRole::AccountManager)->create();
    $this->actingAs($manager);
    $this->get('/account-requests')->assertOk();

    // manage_clients is NOT in the Account Manager default set (v1 parity):
    // the list needs it, so the index is refused while the queue works.
    $this->get('/clients')->assertForbidden();

    $uploader = User::factory()->role(SystemRole::Uploader)->create();
    $this->actingAs($uploader);
    $this->get('/clients')->assertForbidden();
    $this->get('/account-requests')->assertForbidden();
});

test('client management exists in the cloud edition too', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs($this->admin)->get('/clients')->assertOk();
    $this->actingAs($this->admin)->get('/account-requests')->assertOk();
});

test('staff can update client settings and they take effect', function () {
    $this->actingAs($this->admin);

    $this->get('/system/settings/clients')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/clients')
            ->where('clients_can_register', false),
    );

    $this->patch('/system/settings/clients', [
        'clients_can_register' => true,
        'clients_auto_approve' => false,
        'clients_auto_group' => 0,
        'clients_can_select_group' => 'none',
        'clients_membership_deny_cooldown_days' => 30,
        'default_client_storage_quota_mb' => 0,
        'clients_can_preview_files' => true,
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    Auth::logout();
    $this->flushSession();
    $this->get('/register')->assertOk();
});

test('a failure while disposing of a deleted client\'s content rolls the deletion back', function () {
    $client = User::factory()->client()->create();
    $reassignTarget = User::factory()->create();

    failAccountContentDisposal();

    $this->actingAs($this->admin)->delete("/clients/{$client->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $reassignTarget->id,
    ])->assertStatus(500);

    // The soft-delete and its log share a transaction with the content step,
    // so a failure there leaves the client intact rather than
    // deleted-but-still-owning-files.
    expect(User::query()->find($client->id))->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::UserDeleted)->exists())->toBeFalse();
});
