<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Installation\Events\ResolvingInstallationStatus;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * The probe a reconciler reads instead of being given a shell one-liner
 * to run. Its contract is the JSON shape, so that is what these pin.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

function statusJson(bool $assoc = true): array
{
    // Capturing the command's own output rather than asserting on lines,
    // because the contract here is the document and not the wording.
    Artisan::call('projectsend:status', ['--json' => true]);

    // Decoded as objects where the shape itself is under test: {} and []
    // are the same array once an associative decode has flattened them,
    // and telling them apart is the point of those cases.
    return (array) json_decode(Artisan::output(), $assoc, flags: JSON_THROW_ON_ERROR);
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

// ------------------------------------------------- what the disk cannot say

/**
 * Storage is summed from the rows, not measured on the volume.
 *
 * Measuring the directory was right until external storage went live and
 * silently stopped being: an upload that resolves to a bucket leaves
 * nothing on the volume, so a figure taken from the filesystem freezes
 * while the account keeps filling.
 */
test('storage is what the installation holds, wherever the bytes went', function () {
    File::factory()->create(['size' => 100, 'disk' => 'files']);
    File::factory()->create(['size' => 250, 'disk' => 'files']);
    File::factory()->create(['size' => 1000, 'disk' => 'files_external']);

    $storage = statusJson()['storage'];

    expect($storage['bytes'])->toBe(1350)
        ->and($storage['files'])->toBe(3)
        // Split by disk, which is the only way to see what is still
        // sitting locally from before a cutover.
        ->and($storage['by_disk'])->toBe([
            'files' => ['bytes' => 350, 'files' => 2],
            'files_external' => ['bytes' => 1000, 'files' => 1],
        ]);
});

test('a trashed file is not still costing anything', function () {
    // File's `deleted` hook takes the bytes off disk, so a soft-deleted
    // row records something that is gone rather than something held.
    $file = File::factory()->create(['size' => 500, 'disk' => 'files']);
    File::factory()->create(['size' => 100, 'disk' => 'files']);

    $file->delete();

    expect(statusJson()['storage']['bytes'])->toBe(100);
});

test('an installation holding nothing still answers with a map', function () {
    // An empty PHP array encodes as [], and a reader unmarshalling a map
    // breaks on the day it happens to be empty rather than the day it is
    // written.
    expect(json_encode(statusJson(false)['storage']->by_disk))->toBe('{}');
});

test('it reports the health a container cannot show from outside', function () {
    // A queue worker dying is invisible to anything watching the
    // container: it is still up, and zips quietly stop building.
    $health = statusJson()['health'];

    expect($health)->toHaveKeys(['pending_migrations', 'failed_jobs', 'queues'])
        ->and($health['pending_migrations'])->toBe(0)
        ->and($health['failed_jobs'])->toBe(0)
        ->and($health['queues'])->toHaveKeys(['default', 'zips']);
});

test('the enforcement setting is echoed back as applied', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');

    expect(statusJson()['settings']['two_factor_enforcement'])->toBe('all');
});

test('an unreadable enforcement value reports none, not something stricter', function () {
    // Read the way EnforceTwoFactor reads it: reporting a stricter answer
    // than the middleware actually enforces is worse than reporting none.
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'everybody-ish');

    expect(statusJson()['settings']['two_factor_enforcement'])->toBe('none');
});

// --------------------------------------------- what core cannot answer alone

test('a package can report what core has no way to know', function () {
    // The managed storage backend and the version of the package that
    // provides it live outside this repository. A platform knows what it
    // asked for; only the installation knows what loaded.
    Event::listen(ResolvingInstallationStatus::class, function (ResolvingInstallationStatus $event): void {
        $event->report('cloud_modules', '1.1.0');
        $event->report('managed_storage', 's3 bucket "psc-rebels"');
    });

    expect(statusJson()['modules'])->toBe([
        'cloud_modules' => '1.1.0',
        'managed_storage' => 's3 bucket "psc-rebels"',
    ]);
});

test('an installation running no packages answers with a map, not a list', function () {
    expect(json_encode(statusJson(false)['modules']))->toBe('{}');
});

// ----------------------------------------------------- which build is this

/**
 * A version is a decision somebody made; a commit is a fact.
 *
 * Two images can carry the same version and different code — one built
 * from the tag, one from the branch that tag sits on — and a fleet spent a
 * day reporting "2.2.0" from images that were not the released 2.2.0. This
 * is what lets an installation say which one it is.
 */
test('a source checkout says it is not a build, in every field', function () {
    // config/build.php is written by build-release.sh and gitignored, so a
    // checkout has none. Null is the honest answer: "I was not built" and
    // "I will not say" are different, and only the first is true here.
    expect(statusJson()['build'])->toBe([
        'commit' => null,
        'ref' => null,
        'channel' => null,
        'built_at' => null,
    ]);
});

test('a built artifact reports the commit it came from', function () {
    config([
        'build.commit' => '2029309aa1b2c3d4e5f6',
        'build.ref' => 'v2.2.1',
        'build.channel' => 'release',
        'build.built_at' => '2026-08-28T04:00:00Z',
    ]);

    expect(statusJson()['build'])->toBe([
        'commit' => '2029309aa1b2c3d4e5f6',
        'ref' => 'v2.2.1',
        'channel' => 'release',
        'built_at' => '2026-08-28T04:00:00Z',
    ]);
});

test('an internal build says so, so nobody reads it as a release', function () {
    config(['build.channel' => 'dev', 'build.ref' => '2.2.2-dev.15.g06c364d']);

    expect(statusJson()['build']['channel'])->toBe('dev');
});

test('an empty string is not an answer', function () {
    // A build step that ran and produced nothing must not be reported as a
    // build identity — that would read as "answered" to anything checking
    // for presence, which is the distinction this whole file protects.
    config(['build.commit' => '']);

    expect(statusJson()['build']['commit'])->toBeNull();
});
