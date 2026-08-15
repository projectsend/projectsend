<?php

declare(strict_types=1);

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\Models\Role;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Updates\UpdateInstallation;
use Illuminate\Console\OutputStyle;

/**
 * The ordering constraints inside UpdateInstallation are invisible in its
 * result and expensive when wrong — a queue:restart before a cache clear
 * leaves a worker on old code indefinitely, and config:cache breaks
 * TRUSTED_PROXIES silently. An ordered list of the commands it ran is the
 * only thing that can assert them, so the artisan call is a seam.
 */
class RecordingUpdate extends UpdateInstallation
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, int> */
    public array $exitCodes = [];

    /** @var array{route: bool, event: bool, config: bool} */
    public array $warm = ['route' => false, 'event' => false, 'config' => false];

    /** The test database is always migrated, so this cannot be observed for real. */
    public bool $existingInstall = true;

    protected function artisan(string $command, array $parameters = [], ?OutputStyle $output = null): int
    {
        $this->calls[] = $command;

        return $this->exitCodes[$command] ?? 0;
    }

    protected function warmCaches(): array
    {
        return $this->warm;
    }

    protected function hasRunMigrationsBefore(): bool
    {
        return $this->existingInstall;
    }
}

/**
 * @param  array{route?: bool, event?: bool, config?: bool}  $warm
 * @param  array<string, int>  $exitCodes
 */
function recordingUpdate(array $warm = [], array $exitCodes = []): RecordingUpdate
{
    $fake = new RecordingUpdate(
        app(Illuminate\Contracts\Foundation\Application::class),
        app(App\Modules\Identity\Permissions\EnsureSystemRoles::class),
        app(Settings::class),
        app(App\Modules\Audit\ActivityLogger::class),
    );

    $fake->warm = [...$fake->warm, ...$warm];
    $fake->exitCodes = $exitCodes;

    app()->instance(UpdateInstallation::class, $fake);

    return $fake;
}

test('it migrates, ensures roles, links storage and restarts the queue', function () {
    $fake = recordingUpdate();

    $this->artisan('projectsend:update')->assertSuccessful();

    expect($fake->calls)->toContain('migrate', 'storage:link', 'queue:restart')
        ->and(array_search('migrate', $fake->calls, true))->toBe(0);
});

// queue:restart writes its signal into the cache. Anything that clears the
// cache afterwards deletes it, and the worker keeps running the old code
// with nothing to show for it.
test('queue:restart is the last thing it does', function () {
    $fake = recordingUpdate();

    $this->artisan('projectsend:update')->assertSuccessful();

    expect(array_key_last($fake->calls))->toBe(array_search('queue:restart', $fake->calls, true));
});

// config:cache stops TRUSTED_PROXIES from being read at all — see
// INSTALL.md. Nothing in an update may ever put one in place.
test('it never caches the configuration', function () {
    $fake = recordingUpdate(['config' => true]);

    $this->artisan('projectsend:update')->assertSuccessful();

    expect($fake->calls)->toContain('config:clear')
        ->and($fake->calls)->not->toContain('config:cache');
});

// Laravel's Redis cache store implements cache:clear as FLUSHDB, which on
// a single-database Redis takes the sessions and the queue with it.
test('it does not flush the application cache', function () {
    $fake = recordingUpdate();

    $this->artisan('projectsend:update')->assertSuccessful();

    expect($fake->calls)->not->toContain('cache:clear')
        ->and($fake->calls)->not->toContain('optimize:clear');
});

test('it rebuilds only the caches that were in place beforehand', function (array $warm, array $expected) {
    $fake = recordingUpdate($warm);

    $this->artisan('projectsend:update')->assertSuccessful();

    $rebuilt = array_values(array_filter($fake->calls, fn (string $call): bool => str_ends_with($call, ':cache')));

    expect($rebuilt)->toBe($expected);
})->with([
    'nothing cached — a container, or an install that never optimised' => [[], []],
    'routes only' => [['route' => true], ['route:cache', 'event:cache', 'view:cache']],
    'events only' => [['event' => true], ['route:cache', 'event:cache', 'view:cache']],
    'both' => [['route' => true, 'event' => true], ['route:cache', 'event:cache', 'view:cache']],
]);

test('a failed migration stops everything and records nothing', function () {
    $fake = recordingUpdate([], ['migrate' => 1]);

    $this->artisan('projectsend:update')->assertFailed();

    expect($fake->calls)->toBe(['migrate'])
        ->and(app(Settings::class)->get(Setting::AppliedVersion))->toBe('');
});

// A route file that will not compile leaves a slower site. Failing the
// update over it would leave a broken one.
test('a cache that will not rebuild is a warning, not a failure', function () {
    recordingUpdate(['route' => true], ['route:cache' => 1]);

    $this->artisan('projectsend:update')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::AppliedVersion))->toBe(config('projectsend.version'));
});

test('it records the version it applied', function () {
    $this->artisan('projectsend:update')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::AppliedVersion))->toBe(config('projectsend.version'))
        ->and(app(Settings::class)->get(Setting::AppliedVersionAt))->not->toBe('');
});

// The real thing, not the seam: both entrypoints run this on every boot,
// so a second run has to be as uneventful as the first.
test('running it twice is uneventful', function () {
    $this->artisan('projectsend:update')->assertSuccessful();
    $this->artisan('projectsend:update')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::AppliedVersion))->toBe(config('projectsend.version'));
});

// Not through the seam: this is the one assertion that the real wiring
// runs, and EnsureSystemRoles is the part of an update that a migration
// cannot do for itself. AccountManager rather than Uploader — the latter
// is legacy and deliberately never seeded.
test('it puts back a system role somebody deleted', function () {
    Role::query()->where('name', SystemRole::AccountManager->value)->delete();

    $this->artisan('projectsend:update')->assertSuccessful();

    expect(Role::query()->where('name', SystemRole::AccountManager->value)->exists())->toBeTrue();
});

// The two shell files are the one place the sequence could quietly grow a
// second definition again, and nothing else in the suite would notice.
test('both container entrypoints run the command rather than their own sequence', function () {
    foreach (['docker/production/entrypoint.sh', 'docker/app/entrypoint.sh'] as $entrypoint) {
        $contents = file_get_contents(base_path($entrypoint));

        expect($contents)->toContain('projectsend:update')
            ->and($contents)->not->toContain('projectsend:ensure-roles')
            ->and($contents)->not->toContain('migrate --force');
    }
});

// An update is exactly the kind of thing the activity log exists for: it is
// the one place an administrator looks to find out what changed on this
// installation and when.
test('it writes the update to the activity log, naming both versions', function () {
    app(Settings::class)->set(Setting::AppliedVersion, '2.0.0');
    config()->set('projectsend.version', '2.1.0');

    $this->artisan('projectsend:update')->assertSuccessful();

    $entry = ActivityLog::query()->where('action', Action::ApplicationUpdated)->sole();

    expect($entry->context['from'])->toBe('2.0.0')
        ->and($entry->context['to'])->toBe('2.1.0')
        // Nobody's account did this — a scheduled boot or a shell did.
        ->and($entry->actor_id)->toBeNull();
});

// The container entrypoint runs this on every start. One log entry per
// restart would bury everything else in it.
test('re-running on the same version logs nothing', function () {
    app(Settings::class)->set(Setting::AppliedVersion, config('projectsend.version'));

    $this->artisan('projectsend:update')->assertSuccessful();
    $this->artisan('projectsend:update')->assertSuccessful();

    expect(ActivityLog::query()->where('action', Action::ApplicationUpdated)->count())->toBe(0);
});

// The first update of any installation older than this command finds no
// recorded version — and that update is precisely the one worth logging.
test('an update from a version that was never recorded is still logged', function () {
    expect(app(Settings::class)->get(Setting::AppliedVersion))->toBe('');

    $this->artisan('projectsend:update')->assertSuccessful();

    $entry = ActivityLog::query()->where('action', Action::ApplicationUpdated)->sole();

    // A dash, not a sentence: context values are substituted verbatim and
    // never translated, so anything English here would outlive the locale.
    expect($entry->context['from'])->toBe('—')
        ->and($entry->context['to'])->toBe(config('projectsend.version'));
});

// A first boot is an installation, not an update — SetupCompleted already
// records that, and the container runs this before anybody has installed
// anything.
test('a fresh installation logs no update', function () {
    $fake = recordingUpdate();
    $fake->existingInstall = false;

    $this->artisan('projectsend:update')->assertSuccessful();

    expect(ActivityLog::query()->where('action', Action::ApplicationUpdated)->count())->toBe(0)
        ->and(app(Settings::class)->get(Setting::AppliedVersion))->toBe(config('projectsend.version'));
});
