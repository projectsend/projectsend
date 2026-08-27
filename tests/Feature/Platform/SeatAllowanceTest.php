<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Validation\ValidationException;

/**
 * A cap is only a cap if every door asks.
 *
 * There is no single User::create() these funnel through, so there is a
 * test per door rather than a test of the service — the failure mode is
 * one of them quietly not asking, and that is invisible from everywhere
 * except the door that forgot. Same reason
 * DownloadLimitEnforcementTest is written per route.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

function seatLimits(?int $staff = null, ?int $clients = null): void
{
    config([
        'projectsend.platform.max_staff_users' => $staff,
        'projectsend.platform.max_clients' => $clients,
    ]);
}

// ---------------------------------------------------------------- the rule

test('no limit is the default, which is every self-hosted install', function () {
    $seats = app(SeatAllowance::class);

    expect($seats->staffLimit())->toBeNull()
        ->and($seats->clientLimit())->toBeNull();
});

test('a mistyped limit reads as unlimited rather than as zero', function () {
    // An operator who fat-fingers the variable gets the self-hosted
    // behaviour, not an installation that refuses every account.
    seatLimits(staff: null);
    config(['projectsend.platform.max_staff_users' => 'three']);

    expect(app(SeatAllowance::class)->staffLimit())->toBeNull();
});

test('the count the guard reads is the count anyone else should display', function () {
    // The portal shows "2 of 3 used" and the tenant refuses the fourth. If
    // those are two counts they diverge, and it reads as a billing fault.
    seatLimits(staff: 3, clients: 3);

    $seats = app(SeatAllowance::class);

    expect($seats->staffUsed())->toBe(1)   // the admin from beforeEach
        ->and($seats->clientUsed())->toBe(0);
});

test('an inactive staff account still occupies its seat', function () {
    // Otherwise deactivating is a way around the cap rather than a way to
    // revoke access, since reactivating is one click.
    User::factory()->create(['active' => false]);

    expect(app(SeatAllowance::class)->staffUsed())->toBe(2);
});

test('a deleted account frees its seat', function () {
    $extra = User::factory()->create();

    expect(app(SeatAllowance::class)->staffUsed())->toBe(2);

    $extra->delete();

    expect(app(SeatAllowance::class)->staffUsed())->toBe(1);
});

test('a client awaiting approval does not occupy a seat', function () {
    // Self-registration is open to strangers. Counting a pending request
    // would let anyone exhaust a paid limit from outside, which turns a
    // pricing tier into an availability control.
    User::factory()->client()->create(['account_requested' => true, 'active' => false]);

    expect(app(SeatAllowance::class)->clientUsed())->toBe(0);
});

// -------------------------------------------------------------- the doors

test('door: creating a staff account through the web screen', function () {
    seatLimits(staff: 1); // the admin already fills it

    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Second',
        'email' => 'second@example.test',
        'role_id' => Role::query()->where('name', SystemRole::AccountManager->value)->value('id'),
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'second@example.test')->exists())->toBeFalse();
});

test('door: creating a staff account through the API', function () {
    seatLimits(staff: 1);

    Laravel\Sanctum\Sanctum::actingAs($this->admin, ['manage_users', 'create_users']);

    $this->postJson('/api/v1/users', [
        'name' => 'Second',
        'email' => 'second@example.test',
        'role_id' => Role::query()->where('name', SystemRole::AccountManager->value)->value('id'),
        'password' => 'a-strong-password-1',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'second@example.test')->exists())->toBeFalse();
});

test('door: promoting a client to staff', function () {
    // A promotion takes a staff seat, so it has to ask even though it
    // creates no account.
    seatLimits(staff: 1);

    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => Role::query()->where('name', SystemRole::AccountManager->value)->value('id'),
        'password' => 'a-strong-password-1',
    ])->assertSessionHasErrors('email');

    expect($client->refresh()->isClient())->toBeTrue();
});

test('door: creating a client through the web screen', function () {
    seatLimits(clients: 0);

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Nope',
        'email' => 'nope@example.test',
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
        'active' => true,
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'nope@example.test')->exists())->toBeFalse();
});

test('door: creating a client through the API', function () {
    seatLimits(clients: 0);

    Laravel\Sanctum\Sanctum::actingAs($this->admin, ['create_clients']);

    $this->postJson('/api/v1/clients', [
        'name' => 'Nope',
        'email' => 'nope@example.test',
        'password' => 'a-strong-password-1',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'nope@example.test')->exists())->toBeFalse();
});

test('door: self-registration when the installation approves automatically', function () {
    // Auto-approve means the account counts the moment it is made, so
    // provisioning has to ask. Without auto-approve it does not — the next
    // case covers that, and approval is where the seat is spent instead.
    seatLimits(clients: 0);
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);

    $this->post('/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.test',
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
    ])->assertSessionHasErrors();

    expect(User::query()->where('email', 'stranger@example.test')->exists())->toBeFalse();
});

test('door: approving an account request', function () {
    seatLimits(clients: 0);

    $pending = User::factory()->client()->create(['account_requested' => true, 'active' => false]);

    $this->actingAs($this->admin)->post("/account-requests/{$pending->id}/approve")
        ->assertSessionHasErrors('email');

    expect($pending->refresh()->account_requested)->toBeTrue();
});

test('door: demoting a staff account to client', function () {
    seatLimits(clients: 0);

    $staffer = User::factory()->create();

    $this->actingAs($this->admin)->post("/users/convert/{$staffer->id}", [
        'direction' => 'to_client',
    ])->assertSessionHasErrors('email');

    expect($staffer->refresh()->isStaff())->toBeTrue();
});

// --------------------------------------------------------- what must not change

test('the console command is deliberately not capped', function () {
    // It is the recovery path, and anyone who can run it can also edit the
    // environment the cap comes from. Capping it adds friction to getting
    // back into a locked-out installation and closes nothing.
    seatLimits(staff: 1);

    $this->artisan('projectsend:admin', [
        '--name' => 'Rescue',
        '--email' => 'rescue@example.test',
        '--password' => 'a-strong-password-1',
    ])->assertSuccessful();

    expect(User::query()->where('email', 'rescue@example.test')->exists())->toBeTrue();
});

test('room under the cap still lets an account through', function () {
    // Not deny-everything: the cap refuses the one past the limit, not the
    // ones before it.
    seatLimits(staff: 2);

    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Second',
        'email' => 'second@example.test',
        'role_id' => Role::query()->where('name', SystemRole::AccountManager->value)->value('id'),
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'second@example.test')->exists())->toBeTrue();
});

it('names the limit without putting a noun after the number', function () {
    // The refusal used to read "limited to 1 staff accounts" — the number
    // sat directly in front of a countable noun, so no single wording could
    // be right for every value. English needs two forms; Polish, Czech and
    // Russian need three, and inflect the noun by the number in front of
    // it. Putting the number last means no language has to agree with it.
    config()->set('projectsend.platform.max_staff_users', 1);
    config()->set('projectsend.platform.max_clients', 1);

    // Both seats have to be full for either guard to say anything at all.
    User::factory()->client()->create();

    $allowance = app(SeatAllowance::class);

    foreach (['guardStaff', 'guardClient'] as $guard) {
        try {
            $allowance->{$guard}();
            $this->fail($guard.'() should have refused at a limit of 1.');
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first();

            expect($message)->toContain('limited to 1.')
                // A trailing noun is exactly what this is here to stop.
                ->and($message)->not->toContain('1 staff accounts')
                ->and($message)->not->toContain('1 clients');
        }
    }
});
