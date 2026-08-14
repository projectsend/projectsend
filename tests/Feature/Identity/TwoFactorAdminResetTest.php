<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Notifications\TwoFactorResetNotification;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Removing somebody else's second factor
|--------------------------------------------------------------------------
|
| An authenticator app that is gone takes the account with it: the holder
| cannot sign in, and no administrator can open it for them either. These
| cover the remedy on both populations — staff (/users) and clients
| (/clients) — and the guards that stop it becoming a takeover route.
|
| enableTwoFactor() lives in TwoFactorTest.php and is loaded with it.
|
*/

test('an administrator removes a locked-out staff account\'s second factor', function () {
    $admin = User::factory()->create();
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);

    confirmPassword($admin);
    $this->actingAs($admin)
        ->delete("/users/{$locked->id}/two-factor")
        ->assertRedirect();

    $locked->refresh();

    expect($locked->hasTwoFactorEnabled())->toBeFalse()
        // Not just the confirmation timestamp: leaving the secret behind
        // would let the old authenticator app keep working the moment
        // anything re-confirmed it.
        ->and($locked->two_factor_secret)->toBeNull()
        ->and($locked->two_factor_recovery_codes)->toBeNull();
});

test('the account holder can sign in again with their password alone', function () {
    $admin = User::factory()->create();
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);

    confirmPassword($admin);
    $this->actingAs($admin)->delete("/users/{$locked->id}/two-factor");

    // The whole point of the feature, asserted end to end rather than
    // through the columns: sign-in no longer diverts to the challenge.
    $this->post('/logout');
    forgetRequestState();

    $this->post('/login', ['email' => $locked->email, 'password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($locked->fresh());
});

test('it is recorded against the administrator who did it, not the account', function () {
    $admin = User::factory()->create();
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);

    confirmPassword($admin);
    $this->actingAs($admin)->delete("/users/{$locked->id}/two-factor");

    $entry = ActivityLog::query()->where('action', Action::TwoFactorReset)->sole();

    expect($entry->actor_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe($locked->id);
});

test('the account holder is emailed that it happened', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $admin = User::factory()->create();
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);

    confirmPassword($admin);
    $this->actingAs($admin)->delete("/users/{$locked->id}/two-factor");

    Notification::assertSentTo($locked, TwoFactorResetNotification::class);
});

test('an account that never enrolled produces no audit entry and no email', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $admin = User::factory()->create();
    $other = User::factory()->role(SystemRole::Uploader)->create();

    confirmPassword($admin);
    $this->actingAs($admin)->delete("/users/{$other->id}/two-factor")->assertRedirect();

    Notification::assertNothingSent();
    expect(ActivityLog::query()->where('action', Action::TwoFactorReset)->exists())->toBeFalse();
});

test('a half-finished enrolment is cleared too', function () {
    $admin = User::factory()->create();
    $enrolling = User::factory()->role(SystemRole::Uploader)->create();

    confirmPassword($enrolling);
    $this->actingAs($enrolling)->post('/settings/two-factor');
    expect($enrolling->refresh()->two_factor_secret)->not->toBeNull();

    forgetRequestState();
    confirmPassword($admin);
    $this->actingAs($admin)->delete("/users/{$enrolling->id}/two-factor");

    // Otherwise the pending secret survives and the next confirm call
    // completes an enrolment nobody remembers starting.
    expect($enrolling->refresh()->two_factor_secret)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Guards
|--------------------------------------------------------------------------
*/

test('a stolen session is not enough — the password is re-proved first', function () {
    $admin = User::factory()->create();
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);

    // enableTwoFactor() confirmed a password to get there, and the test
    // session is one session — drop it back out, or this asserts nothing.
    $this->withSession(['auth.password_confirmed_at' => null]);

    $this->actingAs($admin)
        ->delete("/users/{$locked->id}/two-factor")
        ->assertRedirect(route('password.confirm'));

    expect($locked->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('staff without edit_users cannot reach it', function () {
    $actor = staffWithPermissions(['manage_users']);
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);

    confirmPassword($actor);
    $this->actingAs($actor)->delete("/users/{$locked->id}/two-factor")->assertForbidden();

    expect($locked->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a non-administrator cannot strip an administrator\'s second factor', function () {
    // Same rule as editing and deleting: authority you could not have
    // granted is authority you may not touch. Without it, manage_users
    // would be a route to weakening the accounts above you.
    $actor = staffWithPermissions(['manage_users', 'edit_users']);
    $admin = User::factory()->create();
    enableTwoFactor($admin);

    confirmPassword($actor);
    forgetRequestState();
    $this->actingAs($actor)->delete("/users/{$admin->id}/two-factor")->assertForbidden();

    expect($admin->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a client is not addressable through the staff route', function () {
    $admin = User::factory()->create();
    $client = User::factory()->client()->create();

    confirmPassword($admin);
    $this->actingAs($admin)->delete("/users/{$client->id}/two-factor")->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Clients
|--------------------------------------------------------------------------
*/

test('an administrator removes a locked-out client\'s second factor', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $admin = User::factory()->create();
    $client = User::factory()->client()->create();
    enableTwoFactor($client);

    confirmPassword($admin);
    $this->actingAs($admin)
        ->delete("/clients/{$client->id}/two-factor")
        ->assertRedirect();

    expect($client->refresh()->hasTwoFactorEnabled())->toBeFalse();
    Notification::assertSentTo($client, TwoFactorResetNotification::class);
});

test('staff without edit_clients cannot reach the client route', function () {
    $actor = staffWithPermissions(['manage_clients']);
    $client = User::factory()->client()->create();
    enableTwoFactor($client);

    confirmPassword($actor);
    $this->actingAs($actor)->delete("/clients/{$client->id}/two-factor")->assertForbidden();

    expect($client->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a staff account is not addressable through the client route', function () {
    $admin = User::factory()->create();
    $staff = User::factory()->role(SystemRole::Uploader)->create();

    confirmPassword($admin);
    $this->actingAs($admin)->delete("/clients/{$staff->id}/two-factor")->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The screens
|--------------------------------------------------------------------------
|
| The button has to know whether there is anything to remove, so both edit
| screens carry the flag.
|
*/

test('both edit screens report whether the account has a second factor', function () {
    $admin = User::factory()->create();
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($staff);
    $client = User::factory()->client()->create();

    $this->actingAs($admin)->get("/users/{$staff->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('user.two_factor_enabled', true),
    );

    $this->actingAs($admin)->get("/clients/{$client->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('client.two_factor_enabled', false),
    );
});
