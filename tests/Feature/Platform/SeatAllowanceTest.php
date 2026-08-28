<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

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

test('door: approving a pending client through the edit screen', function () {
    seatLimits(clients: 0);

    $pending = User::factory()->client()->create(['account_requested' => true, 'active' => false]);

    $this->actingAs($this->admin)->patch("/clients/{$pending->id}", [
        'name' => $pending->name,
        'email' => $pending->email,
        'active' => true,
    ])->assertSessionHasErrors('active');

    // Nothing is written: the guard throws before save(), so a refused
    // approval does not leave the name or the flag half-applied.
    expect($pending->refresh()->account_requested)->toBeTrue()
        ->and($pending->active)->toBeFalse();
});

test('door: approving a pending client through the API', function () {
    seatLimits(clients: 0);

    $pending = User::factory()->client()->create(['account_requested' => true, 'active' => false]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->patchJson("/api/v1/clients/{$pending->id}", ['active' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors('active');

    expect($pending->refresh()->account_requested)->toBeTrue();
});

test('the cap does not block editing a client the installation already holds', function () {
    // The guard sits inside the approval branch. Above it, an installation
    // sitting at its cap could not rename anybody.
    seatLimits(clients: 0);

    $client = User::factory()->client()->create(['account_requested' => false, 'active' => true]);

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => 'Renamed Ltd',
        'email' => $client->email,
        'active' => true,
    ])->assertSessionHasNoErrors();

    expect($client->refresh()->name)->toBe('Renamed Ltd');
});

test('door: demoting a staff account to client', function () {
    seatLimits(clients: 0);

    $staffer = User::factory()->create();

    $this->actingAs($this->admin)->post("/users/convert/{$staffer->id}", [
        'direction' => 'to_client',
    ])->assertSessionHasErrors('email');

    expect($staffer->refresh()->isStaff())->toBeTrue();
});

// ------------------------------------------- saying so before anything is typed

/**
 * A full installation is an ordinary state on a managed plan, not a fault.
 *
 * It used to read as one: the create screen opened, you invented a
 * password, submitted, and the plan limit came back as a validation error
 * under the email field — which looks like a complaint about the address.
 * The guard stays where it is; these cases are about the screen in front
 * of it.
 */
test('the create screen turns you away instead of taking a form it cannot accept', function () {
    seatLimits(staff: 1); // the admin already fills it

    $this->actingAs($this->admin)->get('/users/create')
        ->assertRedirect('/users')
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'limited to 1.'));
});

test('the client create screen does the same', function () {
    seatLimits(clients: 0);

    $this->actingAs($this->admin)->get('/clients/create')
        ->assertRedirect('/clients')
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'limited to 0.'));
});

test('room under the cap still opens the create screen', function () {
    seatLimits(staff: 2, clients: 2);

    $this->actingAs($this->admin)->get('/users/create')->assertOk();
    $this->actingAs($this->admin)->get('/clients/create')->assertOk();
});

test('the list says where the installation stands, so the button can go dead with a reason', function () {
    seatLimits(staff: 2);

    $this->actingAs($this->admin)->get('/users')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seats.limit', 2)
            ->where('seats.used', 1)
            ->where('seats.full', false)
            // Nothing to explain while there is room.
            ->where('seats.message', null));
});

test('the list carries the refusal in the guard\'s own words once it is full', function () {
    // One wording for one limit: two is how somebody ends up believing
    // there are two limits.
    seatLimits(clients: 1);

    User::factory()->client()->create();

    $this->actingAs($this->admin)->get('/clients')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seats.full', true)
            ->where('seats.message', fn (string $message): bool => str_contains($message, 'limited to 1.')));
});

test('a self-hosted install is told nothing about seats at all', function () {
    // No limit, so no counter, no dead button, and no invitation to
    // wonder which plan it is on.
    seatLimits();

    $this->actingAs($this->admin)->get('/users')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('seats', null));

    $this->actingAs($this->admin)->get('/clients')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('seats', null));
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
