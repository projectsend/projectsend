<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    User::factory()->create();
    $this->client = User::factory()->client()->create();
});

test('a client sees memberships, and available groups per the setting', function () {
    $mine = Group::query()->create(['name' => 'Mine', 'public' => true]);
    $mine->members()->attach($this->client->id);
    Group::query()->create(['name' => 'Open', 'public' => true]);
    Group::query()->create(['name' => 'Closed', 'public' => false]);

    $this->actingAs($this->client);

    // Setting off: memberships only, nothing requestable.
    $this->get('/my-groups')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/my-groups')
            ->has('memberships', 1)
            ->where('memberships.0.name', 'Mine')
            ->has('available', 0),
    );

    // Public: only the open group, membership excluded.
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $this->get('/my-groups')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('available', 1)
            ->where('available.0.name', 'Open')
            ->where('available.0.requested', false),
    );
});

test('private groups never leak to the portal, not even own memberships', function () {
    $secret = Group::query()->create(['name' => 'Internal Sorting', 'public' => false]);
    $secret->members()->attach($this->client->id);

    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');

    $this->actingAs($this->client)->get('/my-groups')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('memberships', 0)
            ->has('available', 0),
    );

    // And a private group cannot be requested from the portal.
    $this->actingAs($this->client)
        ->post('/my-groups', ['group_id' => $secret->id])
        ->assertSessionHasErrors('group_id');
});

test('a client can request membership, once, and sees the pending badge', function () {
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $group = Group::query()->create(['name' => 'Wanted', 'public' => true]);

    $this->actingAs($this->client);

    $this->post('/my-groups', ['group_id' => $group->id])->assertRedirect();

    $request = MembershipRequest::query()->sole();
    expect($request->user_id)->toBe($this->client->id)
        ->and($request->group_id)->toBe($group->id)
        ->and(ActivityLog::query()->where('action', Action::GroupMembershipRequested)->count())->toBe(1);

    // Duplicate request: silently ignored, still one row.
    $this->post('/my-groups', ['group_id' => $group->id])->assertRedirect();
    expect(MembershipRequest::query()->count())->toBe(1);

    $this->get('/my-groups')->assertInertia(
        fn (AssertableInertia $page) => $page->where('available.0.requested', true),
    );

    // The staff queue can approve it end to end.
    $admin = User::query()->where('type', 'staff')->first();
    $this->actingAs($admin)->post('/membership-requests/'.$request->id.'/approve');
    expect($group->members()->pluck('users.id')->all())->toBe([$this->client->id]);
});

test('sidebar pending counts are shared with approvers only', function () {
    $admin = User::query()->where('type', 'staff')->first();
    User::factory()->pendingClient()->create();
    User::factory()->pendingClient()->create();
    $group = Group::query()->create(['name' => 'Counted', 'public' => true]);
    MembershipRequest::query()->create([
        'group_id' => $group->id,
        'user_id' => $this->client->id,
    ]);

    $this->actingAs($admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('pending.account_requests', 2)
            ->where('pending.membership_requests', 1),
    );

    // No approval permissions: no approval-queue counts computed at all —
    // notifications_unread is the one count every authenticated user
    // always gets, regardless of permissions.
    $this->actingAs($this->client)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('pending', ['notifications_unread' => 0]),
    );
});

test('requesting a group you already belong to is a no-op', function () {
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $group = Group::query()->create(['name' => 'Home', 'public' => true]);
    $group->members()->attach($this->client->id);

    $this->actingAs($this->client)->post('/my-groups', ['group_id' => $group->id])->assertRedirect();

    expect(MembershipRequest::query()->count())->toBe(0);
});

test('a client can leave a public group, audited', function () {
    $group = Group::query()->create(['name' => 'Leavable', 'public' => true]);
    $group->members()->attach($this->client->id);

    $this->actingAs($this->client)->delete("/my-groups/{$group->id}")->assertRedirect();

    expect($group->members()->count())->toBe(0);

    $entry = ActivityLog::query()->where('action', Action::GroupMembershipLeft)->sole();
    expect($entry->actor_id)->toBe($this->client->id)
        ->and($entry->subject_name)->toBe('Leavable');
});

test('leaving a private group is refused without revealing it exists', function () {
    $secret = Group::query()->create(['name' => 'Secret', 'public' => false]);
    $secret->members()->attach($this->client->id);
    $stranger = Group::query()->create(['name' => 'Not Mine', 'public' => true]);

    $this->actingAs($this->client);

    // Private membership: same 404 as a group you are not in.
    $this->delete("/my-groups/{$secret->id}")->assertNotFound();
    $this->delete("/my-groups/{$stranger->id}")->assertNotFound();
    $this->delete('/my-groups/9999')->assertNotFound();

    expect($secret->members()->count())->toBe(1);
});

test('a denied request shows as declined and blocks re-requesting during the cooldown', function () {
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $group = Group::query()->create(['name' => 'Guarded', 'public' => true]);
    $admin = User::query()->where('type', 'staff')->first();

    // Request, then staff denies.
    $this->actingAs($this->client)->post('/my-groups', ['group_id' => $group->id]);
    $request = MembershipRequest::query()->sole();
    $this->actingAs($admin)->delete("/membership-requests/{$request->id}");

    // Client sees the declined state, no button behavior.
    $this->actingAs($this->client)->get('/my-groups')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('available.0.requested', false)
            ->where('available.0.denied', true),
    );

    // Re-request during the cooldown is refused.
    $this->actingAs($this->client)
        ->post('/my-groups', ['group_id' => $group->id])
        ->assertSessionHasErrors('group_id');

    // After the cooldown the same row goes back to pending and the
    // staff queue sees it again; approval works end to end.
    $this->travel(31)->days();

    $this->actingAs($this->client)->get('/my-groups')->assertInertia(
        fn (AssertableInertia $page) => $page->where('available.0.denied', false),
    );

    $this->actingAs($this->client)->post('/my-groups', ['group_id' => $group->id])->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(MembershipRequest::STATUS_PENDING)
        ->and(MembershipRequest::query()->count())->toBe(1);

    $this->actingAs($admin)->get('/membership-requests')->assertInertia(
        fn (AssertableInertia $page) => $page->has('requests', 1),
    );
    $this->actingAs($admin)->post("/membership-requests/{$request->id}/approve");
    expect($group->members()->pluck('users.id')->all())->toBe([$this->client->id]);
});

test('a cooldown of zero allows immediate re-requesting', function () {
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    app(Settings::class)->set(Setting::ClientsMembershipDenyCooldownDays, 0);
    $group = Group::query()->create(['name' => 'Lax', 'public' => true]);
    $admin = User::query()->where('type', 'staff')->first();

    $this->actingAs($this->client)->post('/my-groups', ['group_id' => $group->id]);
    $request = MembershipRequest::query()->sole();
    $this->actingAs($admin)->delete("/membership-requests/{$request->id}");

    $this->actingAs($this->client)->post('/my-groups', ['group_id' => $group->id])->assertSessionDoesntHaveErrors();
    expect($request->refresh()->status)->toBe(MembershipRequest::STATUS_PENDING);
});

test('the page is clients-only', function () {
    $staff = User::query()->where('type', 'staff')->first();

    $this->actingAs($staff)->get('/my-groups')->assertNotFound();
    $this->actingAs($staff)->post('/my-groups', ['group_id' => 1])->assertNotFound();

    $group = Group::query()->create(['name' => 'Any', 'public' => true]);
    $this->actingAs($staff)->delete("/my-groups/{$group->id}")->assertNotFound();
});
