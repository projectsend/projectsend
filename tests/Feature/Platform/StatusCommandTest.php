<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * The probe a reconciler reads instead of being given a shell one-liner
 * to run. Its contract is the JSON shape, so that is what these pin.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

function statusJson(): array
{
    // Capturing the command's own output rather than asserting on lines,
    // because the contract here is the document and not the wording.
    Artisan::call('projectsend:status', ['--json' => true]);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}

test('it reports the version, the edition and the capabilities that edition grants', function () {
    config(['projectsend.edition' => Edition::Cloud]);

    $status = statusJson();

    expect($status['version'])->toBe(config('projectsend.version'))
        ->and($status['edition'])->toBe('cloud')
        ->and($status['capabilities'])->toContain('storage.managed', 'platform.managed', 'users.manage');
});

test('the counts are the ones the cap enforces on', function () {
    config([
        'projectsend.platform.max_staff_users' => 3,
        'projectsend.platform.max_clients' => 25,
    ]);

    User::factory()->client()->create();
    User::factory()->client()->create(['account_requested' => true, 'active' => false]);
    User::factory()->create(['active' => false]);

    $status = statusJson();

    // Two staff — the admin and the inactive one, which occupies a seat.
    // One client — the pending request does not.
    expect($status['seats']['staff'])->toBe(['used' => 2, 'limit' => 3])
        ->and($status['seats']['clients'])->toBe(['used' => 1, 'limit' => 25]);
});

test('unlimited is null rather than zero or a missing key', function () {
    // A reader that mistook one for the other would report an installation
    // selling unlimited accounts as one that may hold none.
    config([
        'projectsend.platform.max_staff_users' => null,
        'projectsend.platform.max_clients' => null,
    ]);

    $status = statusJson();

    expect($status['seats']['staff'])->toHaveKey('limit')
        ->and($status['seats']['staff']['limit'])->toBeNull()
        ->and($status['seats']['clients']['limit'])->toBeNull();
});

test('the human form says unlimited in words', function () {
    config(['projectsend.platform.max_staff_users' => null]);

    $this->artisan('projectsend:status')
        ->expectsOutputToContain('of unlimited')
        ->assertSuccessful();
});

// -------------------------------------------------------- is anybody there

/**
 * The one question a platform cannot answer from outside the container.
 *
 * Written against the real sign-in path rather than by inserting log rows:
 * what makes this trustworthy is that a login writes the entry, and a test
 * that writes its own entry would keep passing after the listener stopped.
 */
test('it reports when a staff account last signed in', function () {
    $this->travelTo('2026-08-24 21:13:32');
    Auth::login($this->admin);

    expect(statusJson()['activity']['last_staff_login_at'])->toBe('2026-08-24T21:13:32+00:00');
});

test('it reports the most recent sign-in, not the first', function () {
    $this->travelTo('2026-08-01 09:00:00');
    Auth::login($this->admin);

    $this->travelTo('2026-08-24 21:13:32');
    Auth::login(User::factory()->create());

    expect(statusJson()['activity']['last_staff_login_at'])->toBe('2026-08-24T21:13:32+00:00');
});

test('a client signing in is not a staff sign-in', function () {
    // Clients using an installation says nothing about whether anybody is
    // still administering it, which is the question being asked.
    Auth::login(User::factory()->client()->create());

    expect(statusJson()['activity']['last_staff_login_at'])->toBeNull();
});

test('never is null, and the key is there to say so', function () {
    // "Nobody has ever signed in" and "we got no answer from the probe"
    // have to stay distinguishable, and a missing key collapses them.
    $status = statusJson();

    expect($status['activity'])->toHaveKey('last_staff_login_at')
        ->and($status['activity']['last_staff_login_at'])->toBeNull();
});

test('an API token is not somebody signing in', function () {
    // An hourly integration must not make a dormant installation look
    // busy. Only Laravel's Login event writes the entry this reads, and
    // token authentication does not fire it.
    Laravel\Sanctum\Sanctum::actingAs($this->admin, ['manage_users']);
    $this->getJson('/api/v1/users')->assertOk();

    expect(statusJson()['activity']['last_staff_login_at'])->toBeNull();
});

test('the human form says never rather than nothing', function () {
    $this->artisan('projectsend:status')
        ->expectsOutputToContain('Last staff login: never')
        ->assertSuccessful();
});
