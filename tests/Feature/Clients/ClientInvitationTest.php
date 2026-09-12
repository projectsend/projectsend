<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Clients\Models\Invitation;
use App\Modules\Clients\Notifications\ClientInvitationNotification;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('staff can send an invitation and it emails the invited address', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post('/clients/invite', [
        'email' => 'invited@example.com',
        'name' => 'Invited Person',
        'group_id' => 0,
    ])->assertRedirect(route('clients.index'));

    $invitation = Invitation::query()->where('email', 'invited@example.com')->sole();
    expect($invitation->name)->toBe('Invited Person')
        ->and($invitation->status)->toBe(Invitation::STATUS_PENDING)
        ->and($invitation->invited_by_id)->toBe($this->admin->id)
        ->and(ActivityLog::query()->where('action', Action::ClientInvited)->exists())->toBeTrue();

    Notification::assertSentOnDemand(
        ClientInvitationNotification::class,
        fn (ClientInvitationNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'invited@example.com',
    );
});

test('a storage quota set on the invitation carries through to the account it creates', function () {
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);

    // A string, deliberately: a real form field arrives as one, and
    // $this->post() otherwise preserves whatever PHP type the test itself
    // wrote — hiding exactly the mismatch a browser's actual POST would
    // hit against a strictly-typed collaborator.
    $this->actingAs($this->admin)->post('/clients/invite', [
        'email' => 'invited@example.com',
        'group_id' => 0,
        'storage_quota_mb' => '500',
    ]);

    $invitation = Invitation::query()->where('email', 'invited@example.com')->sole();
    expect($invitation->storage_quota_mb)->toBe(500);

    $this->post('/logout');

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $client = User::query()->where('email', 'invited@example.com')->sole();
    expect($client->storage_quota_mb)->toBe(500);
});

test('leaving the storage quota blank inherits the site default, same as self-registration', function () {
    $this->actingAs($this->admin)->post('/clients/invite', [
        'email' => 'invited@example.com',
        'group_id' => 0,
    ]);

    $invitation = Invitation::query()->where('email', 'invited@example.com')->sole();
    expect($invitation->storage_quota_mb)->toBe(0);
});

test('inviting an already-invited address supersedes the earlier invitation instead of leaving two live tokens', function () {
    $first = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    $this->actingAs($this->admin)->post('/clients/invite', [
        'email' => 'invited@example.com',
        'group_id' => 0,
    ])->assertRedirect(route('clients.index'));

    expect($first->fresh()->status)->toBe(Invitation::STATUS_SUPERSEDED)
        ->and(Invitation::query()->pending()->where('email', 'invited@example.com')->count())->toBe(1);

    $this->post('/logout');

    $this->get("/invite/{$first->token}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('expired', true),
    );
});

test('an invitation cannot be sent to an address that already has an account', function () {
    $existing = User::factory()->client()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->admin)->post('/clients/invite', [
        'email' => 'taken@example.com',
        'group_id' => 0,
    ])->assertSessionHasErrors('email');

    expect(Invitation::query()->where('email', 'taken@example.com')->exists())->toBeFalse();

    $existing->delete();
});

test('clients cannot send invitations', function () {
    $this->actingAs(User::factory()->client()->create());

    $this->get('/clients/invite')->assertRedirect(route('dashboard'));
    $this->post('/clients/invite', ['email' => 'x@example.com', 'group_id' => 0])->assertForbidden();
});

test('a valid invitation link shows the redemption form with the email locked', function () {
    $invitation = Invitation::issue('invited@example.com', 'Invited Person', null, $this->admin, now()->addDay());

    $this->get("/invite/{$invitation->token}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('auth/invite')
            ->where('email', 'invited@example.com')
            ->where('name', 'Invited Person')
            ->where('expired', false),
    );
});

test('an unknown token reads as expired rather than a 404', function () {
    $this->get('/invite/not-a-real-token')->assertInertia(
        fn (AssertableInertia $page) => $page->where('expired', true),
    );
});

test('an expired invitation reads as expired', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->subMinute());

    $this->get("/invite/{$invitation->token}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('expired', true),
    );
});

test('redeeming a valid invitation creates an active client and marks it redeemed, regardless of the self-registration auto-approve setting', function () {
    app(Settings::class)->set(Setting::ClientsAutoApprove, false);

    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect(route('login'));

    $client = User::query()->where('email', 'invited@example.com')->sole();
    expect($client->type)->toBe(UserType::Client)
        ->and($client->active)->toBeTrue()
        ->and($client->account_requested)->toBeFalse()
        ->and(ActivityLog::query()->where('action', Action::ClientInvitationRedeemed)->exists())->toBeTrue();

    expect($invitation->fresh()->status)->toBe(Invitation::STATUS_REDEEMED);

    $this->post('/login', ['email' => 'invited@example.com', 'password' => 'super-secret-password']);
    $this->assertAuthenticated();
});

test('redeeming joins the group the invitation named', function () {
    $group = Group::query()->create(['name' => 'Invited Clients']);

    $invitation = Invitation::issue('invited@example.com', null, $group, $this->admin, now()->addDay());

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $client = User::query()->where('email', 'invited@example.com')->sole();
    expect($group->members()->where('users.id', $client->id)->exists())->toBeTrue();
});

test('a redeemed invitation cannot be used again', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());
    $invitation->forceFill(['status' => Invitation::STATUS_REDEEMED])->save();

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Second Attempt',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('token');

    expect(User::query()->where('email', 'invited@example.com')->exists())->toBeFalse();
});

test('an expired invitation cannot be redeemed even by posting the right token', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->subMinute());

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Too Late',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('token');

    expect(User::query()->where('email', 'invited@example.com')->exists())->toBeFalse();
});

test('resending an expired invitation issues a fresh token and emails it, without exposing whether the old one was real', function () {
    Notification::fake();

    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->subMinute());

    $this->post("/invite/{$invitation->token}/resend")->assertRedirect();

    $fresh = Invitation::query()->pending()->where('email', 'invited@example.com')->sole();
    expect($fresh->token)->not->toBe($invitation->token)
        ->and($fresh->isExpired())->toBeFalse()
        ->and($invitation->fresh()->status)->toBe(Invitation::STATUS_SUPERSEDED);

    // The spent link is retired along with the expired one: resending
    // does not leave two working tokens for the same address.
    $this->get("/invite/{$invitation->token}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('expired', true),
    );

    Notification::assertSentOnDemand(
        ClientInvitationNotification::class,
        fn (ClientInvitationNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'invited@example.com',
    );

    // A token that was never real answers exactly the same way — no
    // notification, but also no error revealing that.
    Notification::fake();
    $this->post('/invite/not-a-real-token/resend')->assertRedirect();
    Notification::assertNothingSent();
});

test('an invitation whose address was taken while the link was live refuses rather than failing on the unique index', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    // Staff got impatient, or the person used the public form instead.
    User::factory()->client()->create(['email' => 'invited@example.com']);

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('token');

    expect(User::query()->where('email', 'invited@example.com')->count())->toBe(1)
        ->and($invitation->fresh()->status)->toBe(Invitation::STATUS_PENDING);
});

test('an address held by a deleted account is still taken, the same as it is everywhere else', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    User::factory()->client()->create(['email' => 'invited@example.com'])->delete();

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('token');

    expect(User::query()->where('email', 'invited@example.com')->exists())->toBeFalse();
});

test('a full installation refuses to send an invitation it could not honour', function () {
    config()->set('projectsend.platform.max_clients', 1);
    User::factory()->client()->create();

    $this->actingAs($this->admin)->post('/clients/invite', [
        'email' => 'invited@example.com',
        'group_id' => 0,
    ])->assertSessionHasErrors('email');

    expect(Invitation::query()->where('email', 'invited@example.com')->exists())->toBeFalse();
});

test('a seat taken between invitation and redemption refuses on a field the form can show', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    // The last seat goes while the link is in somebody's inbox.
    config()->set('projectsend.platform.max_clients', 1);
    User::factory()->client()->create();

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'invited@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->status)->toBe(Invitation::STATUS_PENDING);
});

test('the invite screen lists outstanding invitations, expired ones included', function () {
    $group = Group::query()->create(['name' => 'Invited Clients']);

    Invitation::issue('live@example.com', 'Live Person', $group, $this->admin, now()->addDay());
    Invitation::issue('stale@example.com', null, null, $this->admin, now()->subDay());

    // Neither of these is outstanding any more, and neither should be listed.
    Invitation::issue('spent@example.com', null, null, $this->admin, now()->addDay())
        ->forceFill(['status' => Invitation::STATUS_REDEEMED])->save();
    Invitation::issue('gone@example.com', null, null, $this->admin, now()->addDay())
        ->forceFill(['status' => Invitation::STATUS_REVOKED])->save();

    $this->actingAs($this->admin)->get('/clients/invite')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('clients/invite')
            ->has('invitations', 2)
            ->where('invitations.0.email', 'stale@example.com')
            ->where('invitations.0.expired', true)
            ->where('invitations.1.email', 'live@example.com')
            ->where('invitations.1.expired', false)
            ->where('invitations.1.group', 'Invited Clients')
            ->where('invitations.1.invited_by', $this->admin->name),
    );
});

test('staff can revoke an invitation, and the revoked link is dead for good', function () {
    Notification::fake();

    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    $this->actingAs($this->admin)
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect();

    expect($invitation->fresh()->status)->toBe(Invitation::STATUS_REVOKED)
        ->and(ActivityLog::query()->where('action', Action::ClientInvitationRevoked)->exists())->toBeTrue();

    $this->post('/logout');

    // The three things a live token could do, all refused.
    $this->get("/invite/{$invitation->token}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('expired', true),
    );

    $this->post("/invite/{$invitation->token}", [
        'token' => $invitation->token,
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('token');

    $this->post("/invite/{$invitation->token}/resend")->assertRedirect();
    Notification::assertNothingSent();

    expect(Invitation::query()->pending()->where('email', 'invited@example.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'invited@example.com')->exists())->toBeFalse();
});

test('an invitation that is not outstanding cannot be revoked', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());
    $invitation->forceFill(['status' => Invitation::STATUS_REDEEMED])->save();

    $this->actingAs($this->admin)
        ->delete(route('invitations.destroy', $invitation))
        ->assertNotFound();

    expect($invitation->fresh()->status)->toBe(Invitation::STATUS_REDEEMED);
});

test('clients cannot revoke invitations', function () {
    $invitation = Invitation::issue('invited@example.com', null, null, $this->admin, now()->addDay());

    $this->actingAs(User::factory()->client()->create())
        ->delete(route('invitations.destroy', $invitation))
        ->assertForbidden();

    expect($invitation->fresh()->status)->toBe(Invitation::STATUS_PENDING);
});
