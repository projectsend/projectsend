<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Jobs\SyncLdapUsersJob;
use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapSettings;
use App\Modules\Identity\Ldap\LdapSync;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeLdapDirectory;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * @param  array<string, array{password: string, name?: string, dn?: string}>  $entries
 */
function syncDirectory(array $entries = []): FakeLdapDirectory
{
    $fake = new FakeLdapDirectory($entries);
    test()->swap(LdapDirectory::class, $fake);

    return $fake;
}

function enableSync(bool $autoProvision = true, bool $deactivateMissing = false): void
{
    LdapSettings::current()->forceFill([
        'active' => true,
        'host' => 'ldap.example.test',
        'base_dn' => 'dc=example,dc=test',
        'auto_provision' => $autoProvision,
        'auto_approve' => true,
        'sync_deactivates_missing' => $deactivateMissing,
    ])->save();
}

function directoryClient(string $email, string $name = 'Directory Person'): User
{
    $client = User::factory()->client()->create(['email' => $email, 'name' => $name]);
    $client->forceFill(['auth_source' => AuthSource::Ldap, 'ldap_dn' => "uid={$email},ou=people,dc=example,dc=test"])->save();

    return $client;
}

test('a dry run reports what a sync would do and changes nothing', function () {
    enableSync();
    syncDirectory([
        'new@example.test' => ['password' => 'x', 'name' => 'New Person'],
        'renamed@example.test' => ['password' => 'x', 'name' => 'New Name'],
    ]);
    $renamed = directoryClient('renamed@example.test', 'Old Name');
    $users = User::query()->count();

    $report = app(LdapSync::class)->run(dryRun: true);

    expect($report['found'])->toBe(2)
        ->and($report['created'])->toBe(1)
        ->and($report['updated'])->toBe(1)
        ->and($report['dry_run'])->toBeTrue()
        ->and(User::query()->count())->toBe($users)
        ->and($renamed->fresh()->name)->toBe('Old Name');
});

test('a sync creates the directory people who have no account yet, quietly, as clients', function () {
    Notification::fake();
    Mail::fake();
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x', 'name' => 'New Person']]);

    $report = app(LdapSync::class)->run();

    $created = User::query()->where('email', 'new@example.test')->sole();
    expect($report['created'])->toBe(1)
        ->and($created->isClient())->toBeTrue()
        ->and($created->auth_source)->toBe(AuthSource::Ldap)
        ->and($created->name)->toBe('New Person')
        ->and(ActivityLog::query()->where('action', Action::LdapClientImported)->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', Action::LdapSyncRun)->count())->toBe(1);

    // One summary in the log, rather than an email and a bell per account.
    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('nothing is created while auto-provisioning is off', function () {
    enableSync(autoProvision: false);
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    $report = app(LdapSync::class)->run();

    expect($report['created'])->toBe(0)
        ->and($report['skipped']['provisioning_off'])->toBe(1)
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

test('a directory client is brought up to date, and an unchanged one is left alone', function () {
    enableSync();
    syncDirectory([
        'renamed@example.test' => ['password' => 'x', 'name' => 'New Name', 'dn' => 'uid=renamed,ou=moved,dc=example,dc=test'],
        'same@example.test' => ['password' => 'x', 'name' => 'Same Name', 'dn' => 'uid=same@example.test,ou=people,dc=example,dc=test'],
    ]);
    $renamed = directoryClient('renamed@example.test', 'Old Name');
    directoryClient('same@example.test', 'Same Name');

    $report = app(LdapSync::class)->run();

    expect($report['updated'])->toBe(1)
        ->and($report['unchanged'])->toBe(1)
        ->and($renamed->fresh()->name)->toBe('New Name')
        ->and($renamed->fresh()->ldap_dn)->toBe('uid=renamed,ou=moved,dc=example,dc=test');
});

test('staff, local clients and deleted accounts are skipped, never taken over', function () {
    enableSync();
    syncDirectory([
        'staff@example.test' => ['password' => 'x', 'name' => 'Directory Name'],
        'local@example.test' => ['password' => 'x', 'name' => 'Directory Name'],
        'deleted@example.test' => ['password' => 'x'],
    ]);
    $staff = User::factory()->create(['email' => 'staff@example.test', 'name' => 'Staff Name']);
    $local = User::factory()->client()->create(['email' => 'local@example.test', 'name' => 'Local Name']);
    User::factory()->client()->create(['email' => 'deleted@example.test'])->delete();

    $report = app(LdapSync::class)->run();

    expect($report['skipped'])->toMatchArray(['staff' => 1, 'local' => 1, 'deleted' => 1])
        ->and($staff->fresh()->name)->toBe('Staff Name')
        ->and($staff->fresh()->auth_source)->toBe(AuthSource::Local)
        ->and($local->fresh()->name)->toBe('Local Name')
        ->and($local->fresh()->auth_source)->toBe(AuthSource::Local);
});

test('directory clients missing from the directory are only counted by default', function () {
    enableSync();
    syncDirectory(['present@example.test' => ['password' => 'x']]);
    directoryClient('present@example.test');
    $gone = directoryClient('gone@example.test');

    $report = app(LdapSync::class)->run();

    expect($report['missing'])->toBe(1)
        ->and($report['deactivated'])->toBe(0)
        ->and($gone->fresh()->active)->toBeTrue();
});

test('with deactivation on, missing directory clients are deactivated and nobody else is', function () {
    enableSync(deactivateMissing: true);
    syncDirectory(['present@example.test' => ['password' => 'x']]);
    directoryClient('present@example.test');
    $gone = directoryClient('gone@example.test');
    $local = User::factory()->client()->create(['email' => 'local-only@example.test']);

    $report = app(LdapSync::class)->run();

    expect($report['deactivated'])->toBe(1)
        ->and($gone->fresh()->active)->toBeFalse()
        ->and($local->fresh()->active)->toBeTrue()
        ->and($this->admin->fresh()->active)->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::LdapClientDeactivated)->count())->toBe(1);
});

test('a dry run never deactivates anybody', function () {
    enableSync(deactivateMissing: true);
    syncDirectory(['present@example.test' => ['password' => 'x']]);
    $gone = directoryClient('gone@example.test');

    $report = app(LdapSync::class)->run(dryRun: true);

    expect($report['deactivated'])->toBe(1)
        ->and($gone->fresh()->active)->toBeTrue();
});

test('an empty listing deactivates nobody, whatever the setting says', function () {
    enableSync(deactivateMissing: true);
    syncDirectory();
    $client = directoryClient('client@example.test');

    $report = app(LdapSync::class)->run();

    expect($report['missing'])->toBe(1)
        ->and($report['deactivated'])->toBe(0)
        ->and($client->fresh()->active)->toBeTrue();
});

test('a directory that cannot be listed fails the run and changes nothing', function () {
    enableSync(deactivateMissing: true);
    $fake = syncDirectory(['new@example.test' => ['password' => 'x']]);
    $fake->listingFails = true;
    $client = directoryClient('client@example.test');

    $report = app(LdapSync::class)->run();

    expect($report['status'])->toBe('failed')
        ->and($report['error'])->not->toBeNull()
        ->and($client->fresh()->active)->toBeTrue()
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

test('a sync needs LDAP to be usable', function () {
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    $report = app(LdapSync::class)->run();

    expect($report['status'])->toBe('failed')
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

test('the background job works through the directory in as many chunks as it takes', function () {
    enableSync();
    $entries = [];
    foreach (range(1, 5) as $i) {
        $entries["person{$i}@example.test"] = ['password' => 'x', 'name' => "Person {$i}"];
    }
    syncDirectory($entries);

    // A zero budget stops every chunk after one entry.
    SyncLdapUsersJob::dispatch(budgetSeconds: 0);

    $last = app(LdapSync::class)->last();
    expect(User::query()->where('email', 'like', 'person%')->count())->toBe(5)
        ->and($last['status'])->toBe('finished')
        ->and($last['created'])->toBe(5)
        ->and($last['found'])->toBe(5);
});

test('the command syncs, and its scheduled run only happens when daily sync is on', function () {
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    $this->artisan('projectsend:ldap-sync', ['--scheduled' => true])->assertSuccessful();
    expect(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();

    LdapSettings::current()->forceFill(['sync_daily' => true])->save();
    $this->artisan('projectsend:ldap-sync', ['--scheduled' => true])->assertSuccessful();
    expect(User::query()->where('email', 'new@example.test')->exists())->toBeTrue();
});

test('the command can preview without changing anything', function () {
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    $this->artisan('projectsend:ldap-sync', ['--dry-run' => true])
        ->expectsOutputToContain('1 to create')
        ->assertSuccessful();

    expect(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

test('the settings screen previews, starts a background sync and saves the sync options', function () {
    Queue::fake();
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    $this->actingAs($this->admin)->post('/system/settings/ldap/sync', ['dry_run' => true])
        ->assertRedirect()
        ->assertSessionHas('ldap_sync_preview', fn (array $report): bool => $report['created'] === 1);
    expect(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();

    $this->actingAs($this->admin)->post('/system/settings/ldap/sync')->assertRedirect();
    Queue::assertPushed(SyncLdapUsersJob::class, 1);

    $this->actingAs($this->admin)->get('/system/settings/ldap')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ldap.sync_daily', false)
            ->where('ldap.sync_deactivates_missing', false)
            ->where('sync.status', 'running'));
});

test('only staff who may edit settings can run a sync', function () {
    Queue::fake();

    $this->actingAs(staffWithPermissions(['upload']))->post('/system/settings/ldap/sync')->assertForbidden();
    $this->actingAs(User::factory()->client()->create())->post('/system/settings/ldap/sync')->assertForbidden();

    Queue::assertNothingPushed();
});
