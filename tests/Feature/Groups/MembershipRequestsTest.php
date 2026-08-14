<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);
});

function register(array $groups = []): void
{
    test()->post('/register', [
        'name' => 'Joiner',
        'email' => 'joiner@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'groups' => $groups,
    ]);
}

test('the registration form offers groups per the setting', function () {
    Group::query()->create(['name' => 'Open Group', 'public' => true]);
    Group::query()->create(['name' => 'Closed Group', 'public' => false]);

    // Default: none.
    $this->get('/register')->assertInertia(
        fn (AssertableInertia $page) => $page->has('selectable_groups', 0),
    );

    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $this->get('/register')->assertInertia(
        fn (AssertableInertia $page) => $page->has('selectable_groups', 1)
            ->where('selectable_groups.0.name', 'Open Group'),
    );
});

test('requesting a group outside the allowed set is rejected', function () {
    $closed = Group::query()->create(['name' => 'Closed', 'public' => false]);
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');

    register([$closed->id]);

    $this->assertGuest();
    expect(User::query()->where('email', 'joiner@example.com')->exists())->toBeFalse();
});

test('registration creates membership requests and honors the auto group', function () {
    $auto = Group::query()->create(['name' => 'Everyone', 'public' => true]);
    $wanted = Group::query()->create(['name' => 'Wanted', 'public' => true]);

    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    app(Settings::class)->set(Setting::ClientsAutoGroup, $auto->id);

    register([$auto->id, $wanted->id]);

    $client = User::query()->where('email', 'joiner@example.com')->sole();

    // Auto group: direct membership, no request (even though selected).
    expect($auto->members()->pluck('users.id')->all())->toBe([$client->id]);

    // The other selection waits as a request.
    $request = MembershipRequest::query()->sole();
    expect($request->group_id)->toBe($wanted->id)
        ->and($request->user_id)->toBe($client->id)
        ->and($wanted->members()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', Action::GroupMembershipRequested)->exists())->toBeTrue();
});

test('approving a request joins the client and clears the queue', function () {
    $group = Group::query()->create(['name' => 'Target']);
    $client = User::factory()->client()->create(['name' => 'Hopeful']);
    $request = MembershipRequest::query()->create(['group_id' => $group->id, 'user_id' => $client->id]);

    $this->actingAs($this->admin)->get('/membership-requests')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('groups/membership-requests')
            ->has('requests', 1)
            ->where('requests.0.group_name', 'Target'),
    );

    $this->actingAs($this->admin)->post("/membership-requests/{$request->id}/approve")->assertRedirect();

    expect($group->members()->pluck('users.id')->all())->toBe([$client->id])
        ->and(MembershipRequest::query()->count())->toBe(0);

    $entry = ActivityLog::query()->where('action', Action::GroupMembershipApproved)->sole();
    expect($entry->context)->toBe(['member' => 'Hopeful'])
        ->and($entry->subject_name)->toBe('Target');
});

test('denying keeps the request as denied, out of the queue and badge', function () {
    $group = Group::query()->create(['name' => 'Fort']);
    $client = User::factory()->client()->create();
    $request = MembershipRequest::query()->create(['group_id' => $group->id, 'user_id' => $client->id]);

    $this->actingAs($this->admin)->delete("/membership-requests/{$request->id}")->assertRedirect();

    $request->refresh();
    expect($group->members()->count())->toBe(0)
        ->and($request->status)->toBe(MembershipRequest::STATUS_DENIED)
        ->and($request->denied_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::GroupMembershipDenied)->exists())->toBeTrue();

    // Gone from the queue, badge count zero, and no longer approvable.
    $this->actingAs($this->admin)->get('/membership-requests')->assertInertia(
        fn (AssertableInertia $page) => $page->has('requests', 0)
            ->where('pending.membership_requests', 0),
    );
    $this->actingAs($this->admin)->post("/membership-requests/{$request->id}/approve")->assertNotFound();
});

test('denying a client account removes their pending membership requests', function () {
    app(Settings::class)->set(Setting::ClientsAutoApprove, false);
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $group = Group::query()->create(['name' => 'Cascade', 'public' => true]);

    register([$group->id]);

    $pending = User::query()->where('email', 'joiner@example.com')->sole();
    expect(MembershipRequest::query()->count())->toBe(1);

    $this->actingAs($this->admin)->delete("/account-requests/{$pending->id}");

    expect(MembershipRequest::query()->count())->toBe(0);
});

test('the queue requires the approval permission', function () {
    // Uploader lacks it; Account Manager has it (v1 defaults).
    $this->actingAs(User::factory()->role(SystemRole::Uploader)->create());
    $this->get('/membership-requests')->assertForbidden();

    $this->actingAs(User::factory()->role(SystemRole::AccountManager)->create());
    $this->get('/membership-requests')->assertOk();
});

test('the client settings screen validates the group options', function () {
    $this->actingAs($this->admin);

    $this->patch('/system/settings/clients', [
        'clients_can_register' => true,
        'clients_auto_approve' => true,
        'clients_auto_group' => 999,
        'clients_can_select_group' => 'public',
        'clients_membership_deny_cooldown_days' => 30,
    ])->assertSessionHasErrors('clients_auto_group');

    $group = Group::query()->create(['name' => 'Default Group']);

    $this->patch('/system/settings/clients', [
        'clients_can_register' => true,
        'clients_auto_approve' => true,
        'clients_auto_group' => $group->id,
        'clients_can_select_group' => 'sometimes',
        'clients_membership_deny_cooldown_days' => 30,
    ])->assertSessionHasErrors('clients_can_select_group');

    $this->patch('/system/settings/clients', [
        'clients_can_register' => true,
        'clients_auto_approve' => true,
        'clients_auto_group' => $group->id,
        'clients_can_select_group' => 'public',
        'clients_membership_deny_cooldown_days' => 500,
    ])->assertSessionHasErrors('clients_membership_deny_cooldown_days');

    $this->patch('/system/settings/clients', [
        'clients_can_register' => true,
        'clients_auto_approve' => true,
        'clients_auto_group' => $group->id,
        'clients_can_select_group' => 'public',
        'clients_membership_deny_cooldown_days' => 0,
        'default_client_storage_quota_mb' => 0,
    ])->assertSessionDoesntHaveErrors();

    expect(app(Settings::class)->get(Setting::ClientsAutoGroup))->toBe($group->id);
});
