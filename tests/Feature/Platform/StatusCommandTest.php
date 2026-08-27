<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\Artisan;

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
