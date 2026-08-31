<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Audit\Action;
use App\Modules\Platform\Installation\Events\ResolvingInstallationStatus;
use App\Modules\Platform\Scheduling\ScheduledTaskRun;
use App\Modules\Platform\Scheduling\TaskRunStatus;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

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

    expect($health)->toHaveKeys(['pending_migrations', 'failed_jobs', 'failed_jobs_latest_at', 'queues'])
        ->and($health['pending_migrations'])->toBe(0)
        ->and($health['failed_jobs'])->toBe(0)
        ->and($health['queues'])->toHaveKeys(['default', 'zips']);
});

test('it says when the most recent job failed, not just how many have', function () {
    // The count is a history over a retention window the installation
    // chooses, so it cannot say whether anything is wrong now. A count of
    // two whose newest entry is three weeks old is an installation that
    // has been healthy for three weeks and has not been swept yet.
    DB::table('failed_jobs')->insert([
        [
            'uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Connection refused',
            'failed_at' => '2026-08-07 12:59:19',
        ],
        [
            'uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Connection refused',
            'failed_at' => '2026-08-24 02:43:50',
        ],
    ]);

    $health = statusJson()['health'];

    expect($health['failed_jobs'])->toBe(2)
        ->and($health['failed_jobs_latest_at'])->toBe('2026-08-24T02:43:50+00:00');
});

test('nothing has ever failed is null, with the key there to say so', function () {
    // Distinguishable from "we got no answer", the same way every other
    // null in this document is.
    $health = statusJson()['health'];

    expect($health)->toHaveKey('failed_jobs_latest_at')
        ->and($health['failed_jobs_latest_at'])->toBeNull();
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

// ------------------------------------------------------------ the scheduler

/**
 * A dead queue worker is caught by `health.queues`. Nothing caught a dead
 * scheduler, and the first symptom of one is not a stalled feature: expired
 * files stop being purged, so content that was meant to become unreachable
 * stays reachable while the installation looks perfectly healthy.
 */
test('it reports when the scheduler last ran and how much of it is failing', function () {
    ScheduledTaskRun::create([
        'command' => 'projectsend:purge-expired-files',
        'status' => TaskRunStatus::Success,
        'ran_at' => '2026-08-30 03:00:00',
    ]);
    ScheduledTaskRun::create([
        'command' => 'projectsend:purge-orphan-files',
        'status' => TaskRunStatus::Failed,
        'ran_at' => '2026-08-31 03:00:00',
    ]);

    expect(statusJson()['health']['scheduler'])->toBe([
        'last_run_at' => '2026-08-31T03:00:00+00:00',
        'failing' => 1,
    ]);
});

test('a scheduler that has never run says so with null, not with a zero timestamp', function () {
    // "Never wired up" and "ran, a long time ago" are different facts and
    // the reader acts differently on them. Nothing has run here.
    $status = statusJson();

    expect($status['health']['scheduler'])->toHaveKey('last_run_at')
        ->and($status['health']['scheduler']['last_run_at'])->toBeNull()
        ->and($status['health']['scheduler']['failing'])->toBe(0);
});

test('a task that failed last night and succeeded this morning is not failing', function () {
    // The row is upserted per command, so this counts commands whose most
    // recent run failed — not failures over time.
    $run = ScheduledTaskRun::create([
        'command' => 'projectsend:purge-expired-files',
        'status' => TaskRunStatus::Failed,
        'ran_at' => '2026-08-30 03:00:00',
    ]);

    $run->update(['status' => TaskRunStatus::Success, 'ran_at' => '2026-08-31 03:00:00']);

    expect(statusJson()['health']['scheduler']['failing'])->toBe(0);
});

// ----------------------------------------------------------------- usage

/**
 * What has been happening here lately.
 *
 * Written through ActivityLogger rather than by inserting rows, because
 * the thing under test is a split by `actor_type` and that column is the
 * logger's decision, not the caller's. A test that wrote its own rows
 * would keep passing after the logger stopped stamping them.
 */
function logDownload(App\Modules\Audit\Action $action, ?User $actor, App\Modules\Files\Models\File $file): void
{
    app(App\Modules\Audit\ActivityLogger::class)->log($action, $actor, $file);
}

test('it splits downloads the way the installation own dashboard splits them', function () {
    $file = File::factory()->create();
    $client = User::factory()->client()->create();

    logDownload(Action::FileDownloaded, $this->admin, $file);
    logDownload(Action::FileDownloaded, $client, $file);
    logDownload(Action::FileDownloaded, $client, $file);
    // No actor at all: a share link or the public listing, served to
    // somebody with no account.
    logDownload(Action::ShareLinkDownloaded, null, $file);
    logDownload(Action::PublicFileDownloaded, null, $file);

    expect(statusJson()['usage']['downloads'])->toBe([
        'staff' => 1,
        'clients' => 2,
        'anonymous' => 2,
    ]);
});

test('the three download buckets are actually filtered, not three copies of the total', function () {
    // The shape invites a bug where the actor filter is skipped and every
    // bucket reports the installation's whole download total. Asserting
    // each is smaller than the sum is what catches that.
    $file = File::factory()->create();

    logDownload(Action::FileDownloaded, $this->admin, $file);
    logDownload(Action::ShareLinkDownloaded, null, $file);

    $downloads = statusJson()['usage']['downloads'];

    expect($downloads['staff'])->toBe(1)
        ->and($downloads['clients'])->toBe(0)
        ->and($downloads['anonymous'])->toBe(1)
        ->and(array_sum($downloads))->toBe(2);
});

test('it counts uploads and the handful of actions it was asked for', function () {
    $file = File::factory()->create();
    $logger = app(App\Modules\Audit\ActivityLogger::class);

    $logger->log(Action::FileUploaded, $this->admin, $file);
    $logger->log(Action::FileUploaded, $this->admin, $file);
    $logger->log(Action::UserCreated, $this->admin);
    $logger->log(Action::ShareLinkCreated, $this->admin, $file);

    $usage = statusJson()['usage'];

    expect($usage['uploads'])->toBe(2)
        ->and($usage['actions']['user.created'])->toBe(1)
        ->and($usage['actions']['share_link.created'])->toBe(1)
        ->and($usage['actions']['group.created'])->toBe(0);
});

test('it reports only the allowlisted actions, never whatever happens to be in the log', function () {
    // This document leaves the installation. Action gains cases most
    // weeks and some of them are somebody's compliance event rather than
    // a business metric, so the block emits keys that were chosen and
    // nothing else.
    $logger = app(App\Modules\Audit\ActivityLogger::class);

    $logger->log(Action::AccountErased, $this->admin);
    $logger->log(Action::TwoFactorReset, $this->admin, $this->admin);
    $logger->log(Action::PasswordUpdated, $this->admin);

    $actions = statusJson()['usage']['actions'];

    expect($actions)->not->toHaveKey('account.erased')
        ->and($actions)->not->toHaveKey('two_factor.reset')
        ->and($actions)->not->toHaveKey('password.updated')
        ->and(array_keys($actions))->toBe([
            'user.created',
            'client.self_registered',
            'file.assigned',
            'share_link.created',
            'group.created',
        ]);
});

test('it counts the window and not the whole history', function () {
    $file = File::factory()->create();

    $this->travelTo(now()->subDays(45));
    logDownload(Action::FileDownloaded, $this->admin, $file);

    $this->travelBack();
    logDownload(Action::FileDownloaded, $this->admin, $file);

    $usage = statusJson()['usage'];

    // The older one is outside the window. Reporting it would make the
    // figure a lifetime total that only looks like a rate.
    expect($usage['window_days'])->toBe(30)
        ->and($usage['downloads']['staff'])->toBe(1);
});

test('it says how long the window is, rather than leaving the reader to assume', function () {
    // A number that is charted and a number that is assumed diverge
    // exactly once, silently, on the day the window changes.
    expect(statusJson()['usage'])->toHaveKey('window_days');
});

test('an installation where nothing has happened reports zeroes, not a missing block', function () {
    $usage = statusJson()['usage'];

    expect($usage['downloads'])->toBe(['staff' => 0, 'clients' => 0, 'anonymous' => 0])
        ->and($usage['uploads'])->toBe(0)
        ->and($usage['actions']['user.created'])->toBe(0);
});

test('it reports when a client last signed in, separately from staff', function () {
    // Staff says the administrator still shows up; this says their
    // customers do, which is the more interesting half.
    $this->travelTo('2026-08-24 21:13:32');
    Auth::login(User::factory()->client()->create());

    $status = statusJson();

    expect($status['activity']['last_client_login_at'])->toBe('2026-08-24T21:13:32+00:00')
        ->and($status['activity']['last_staff_login_at'])->toBeNull();
});

test('no client has ever signed in is null with the key present', function () {
    $status = statusJson();

    expect($status['activity'])->toHaveKey('last_client_login_at')
        ->and($status['activity']['last_client_login_at'])->toBeNull();
});

test('the actions block stays a map when it is emitted as JSON', function () {
    // Same reason `modules` is cast: a reader unmarshalling a map must
    // not break on the day the allowlist is empty.
    $status = statusJson(assoc: false);

    expect($status['usage']->actions)->toBeObject();
});
