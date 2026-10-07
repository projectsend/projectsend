<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
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

function enableSync(bool $autoProvision = true, bool $deactivateMissing = false, bool $restoreDeleted = false): void
{
    LdapSettings::current()->forceFill([
        'sync_restores_deleted' => $restoreDeleted,
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

    $this->actingAs($this->admin)->post('/system/settings/ldap/sync')->assertRedirect()->assertSessionHasNoErrors();
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

test('a preview lists every person with what would happen to them', function () {
    enableSync(deactivateMissing: true);
    syncDirectory([
        'new@example.test' => ['password' => 'x', 'name' => 'New Person'],
        'renamed@example.test' => ['password' => 'x', 'name' => 'New Name'],
        'staff@example.test' => ['password' => 'x'],
    ]);
    directoryClient('renamed@example.test', 'Old Name');
    directoryClient('gone@example.test', 'Gone Person');
    User::factory()->create(['email' => 'staff@example.test']);

    $people = collect(app(LdapSync::class)->run(dryRun: true)['people'])->keyBy('email');

    expect($people['new@example.test'])->toMatchArray(['action' => 'create', 'name' => 'New Person'])
        ->and($people['renamed@example.test'])->toMatchArray(['action' => 'update', 'previous_name' => 'Old Name'])
        ->and($people['staff@example.test'])->toMatchArray(['action' => 'skip', 'reason' => 'staff'])
        ->and($people['gone@example.test'])->toMatchArray(['action' => 'deactivate', 'name' => 'Gone Person']);
});

test('people the directory dropped are listed as kept while deactivation is off', function () {
    enableSync();
    syncDirectory(['present@example.test' => ['password' => 'x']]);
    directoryClient('gone@example.test');

    $people = collect(app(LdapSync::class)->run(dryRun: true)['people'])->keyBy('email');

    expect($people['gone@example.test']['action'])->toBe('keep');
});

test('a real run keeps only the counts, not the list of people', function () {
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    expect(app(LdapSync::class)->run()['people'])->toBe([]);
});

test('sync now needs a fresh preview of the saved settings', function () {
    Queue::fake();
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    // Never previewed.
    $this->actingAs($this->admin)->post('/system/settings/ldap/sync')->assertSessionHasErrors('sync');

    // Previewed, then the settings changed underneath the preview.
    $this->actingAs($this->admin)->post('/system/settings/ldap/sync', ['dry_run' => true]);
    $this->travel(1)->seconds();
    LdapSettings::current()->forceFill(['sync_deactivates_missing' => true])->save();
    $this->actingAs($this->admin)->post('/system/settings/ldap/sync')->assertSessionHasErrors('sync');

    // Previewed too long ago.
    $this->actingAs($this->admin)->post('/system/settings/ldap/sync', ['dry_run' => true]);
    $this->travel(11)->minutes();
    $this->actingAs($this->admin)->post('/system/settings/ldap/sync')->assertSessionHasErrors('sync');

    Queue::assertNothingPushed();
});

/**
 * A client account deleted the way an administrator deletes one: logged
 * with the administrator as the actor, erasure scheduled.
 */
function adminDeletedClient(User $admin, string $email, string $name = 'Deleted Person'): User
{
    $client = directoryClient($email, $name);
    $client->forceFill(['erase_after' => now()->addDays(30)])->save();
    $client->delete();
    app(ActivityLogger::class)->log(Action::UserDeleted, $admin, context: ['name' => $name]);

    return $client;
}

test('deleted accounts are skipped while restoring is off', function () {
    enableSync();
    syncDirectory(['back@example.test' => ['password' => 'x']]);
    $deleted = adminDeletedClient($this->admin, 'back@example.test');

    $report = app(LdapSync::class)->run();

    expect($report['skipped']['deleted'])->toBe(1)
        ->and($report['restored'])->toBe(0)
        ->and($deleted->fresh()->trashed())->toBeTrue();
});

test('with restoring on, a client an administrator deleted comes back as it was', function () {
    enableSync(restoreDeleted: true);
    syncDirectory(['back@example.test' => ['password' => 'x', 'name' => 'Back Again']]);
    $deleted = adminDeletedClient($this->admin, 'back@example.test', 'Old Name');
    $deleted->forceFill(['active' => false])->save();

    $report = app(LdapSync::class)->run();

    $restored = User::query()->findOrFail($deleted->id);
    expect($report['restored'])->toBe(1)
        ->and($restored->erase_after)->toBeNull()
        ->and($restored->active)->toBeFalse()
        ->and($restored->name)->toBe('Back Again')
        ->and(ActivityLog::query()->where('action', Action::LdapClientRestored)->count())->toBe(1);
});

test('an account its owner deleted is never restored', function () {
    enableSync(restoreDeleted: true);
    syncDirectory(['left@example.test' => ['password' => 'x']]);
    $client = directoryClient('left@example.test');
    $client->delete();
    app(ActivityLogger::class)->log(Action::UserDeleted, $client, context: ['name' => $client->name]);

    $report = app(LdapSync::class)->run();

    expect($report['skipped']['deleted_by_owner'])->toBe(1)
        ->and($report['restored'])->toBe(0)
        ->and($client->fresh()->trashed())->toBeTrue();
});

test('a deleted staff account is never restored', function () {
    enableSync(restoreDeleted: true);
    syncDirectory(['staff-gone@example.test' => ['password' => 'x']]);
    $staff = User::factory()->create(['email' => 'staff-gone@example.test']);
    $staff->delete();
    app(ActivityLogger::class)->log(Action::UserDeleted, $this->admin, context: ['name' => $staff->name]);

    $report = app(LdapSync::class)->run();

    expect($report['restored'])->toBe(0)
        ->and($staff->fresh()->trashed())->toBeTrue();
});

test('a preview lists who would be restored, and restores nobody', function () {
    enableSync(restoreDeleted: true);
    syncDirectory(['back@example.test' => ['password' => 'x']]);
    $deleted = adminDeletedClient($this->admin, 'back@example.test');

    $report = app(LdapSync::class)->run(dryRun: true);

    expect(collect($report['people'])->firstWhere('email', 'back@example.test')['action'])->toBe('restore')
        ->and($report['restored'])->toBe(1)
        ->and($deleted->fresh()->trashed())->toBeTrue();
});

test('an unreviewed run that would deactivate too many clients stops before changing anything', function () {
    enableSync(deactivateMissing: true);
    LdapSettings::current()->forceFill(['sync_daily' => true])->save();
    syncDirectory([
        'present@example.test' => ['password' => 'x'],
        'new@example.test' => ['password' => 'x'],
    ]);
    directoryClient('present@example.test');
    $gone = collect(range(1, 3))->map(fn (int $i): User => directoryClient("gone{$i}@example.test"));

    $this->artisan('projectsend:ldap-sync', ['--scheduled' => true])->assertFailed();

    $last = app(LdapSync::class)->last();
    expect($last['status'])->toBe('stopped')
        ->and($last['error'])->toContain('3 of the 4')
        ->and($gone->every(fn (User $client): bool => $client->fresh()->active))->toBeTrue()
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('action', Action::LdapSyncStopped)->count())->toBe(1);
});

test('sync now carries out what the preview showed, without reading the directory again', function () {
    enableSync(deactivateMissing: true);
    $fake = syncDirectory(['new@example.test' => ['password' => 'x', 'name' => 'New Person']]);
    directoryClient('gone@example.test');
    $preview = app(LdapSync::class)->run(dryRun: true);

    // The directory changes, or breaks, after the preview: neither matters.
    $fake->listingFails = true;

    $report = app(LdapSync::class)->run(plan: $preview['plan']);

    expect($report['status'])->toBe('finished')
        ->and($report['created'])->toBe(1)
        ->and($report['deactivated'])->toBe(1)
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeTrue();
});

test('somebody whose account changed after the preview is skipped, not acted on', function () {
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);
    $preview = app(LdapSync::class)->run(dryRun: true);

    $local = User::factory()->client()->create(['email' => 'new@example.test', 'name' => 'Local Name']);

    $report = app(LdapSync::class)->run(plan: $preview['plan']);

    expect($report['created'])->toBe(0)
        ->and($report['skipped'])->toBe(['local' => 1])
        ->and($local->fresh()->name)->toBe('Local Name');
});

test('a sync whose preview has expired does nothing', function () {
    enableSync();
    syncDirectory(['new@example.test' => ['password' => 'x']]);

    $report = app(LdapSync::class)->run(plan: 'no-such-plan');

    expect($report['status'])->toBe('failed')
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

test('the clients a sync deactivated can be reactivated afterwards', function () {
    enableSync(deactivateMissing: true);
    syncDirectory(['present@example.test' => ['password' => 'x']]);
    directoryClient('present@example.test');
    $gone = directoryClient('gone@example.test');
    $preview = app(LdapSync::class)->run(dryRun: true);
    app(LdapSync::class)->run(plan: $preview['plan']);
    expect($gone->fresh()->active)->toBeFalse();

    $this->actingAs($this->admin)->post('/system/settings/ldap/sync/reactivate')->assertRedirect();

    $last = app(LdapSync::class)->last();
    expect($gone->fresh()->active)->toBeTrue()
        ->and($last['reactivated'])->toBe(1)
        ->and($last['deactivated_ids'])->toBe([])
        ->and(ActivityLog::query()->where('action', Action::LdapClientReactivated)->where('actor_id', $this->admin->id)->count())->toBe(1);
});

test('a running sync can be stopped, and stops before its next entry', function () {
    enableSync();
    syncDirectory([
        'one@example.test' => ['password' => 'x'],
        'two@example.test' => ['password' => 'x'],
    ]);
    $sync = app(LdapSync::class);
    $sync->markQueued();

    $this->actingAs($this->admin)->post('/system/settings/ldap/sync/cancel')->assertRedirect();
    expect($sync->last()['status'])->toBe('cancelled');

    // The queued job starts after the stop, finds the flag, and does nothing.
    SyncLdapUsersJob::dispatch();

    expect($sync->last()['status'])->toBe('cancelled')
        ->and(User::query()->whereIn('email', ['one@example.test', 'two@example.test'])->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', Action::LdapSyncCancelled)->count())->toBe(1);
});

test('only staff who may edit settings can stop a sync or reactivate its clients', function () {
    $staff = staffWithPermissions(['upload']);

    $this->actingAs($staff)->post('/system/settings/ldap/sync/cancel')->assertForbidden();
    $this->actingAs($staff)->post('/system/settings/ldap/sync/reactivate')->assertForbidden();
});

test('a preview marks the deleted clients that could be restored, even with restoring off', function () {
    enableSync();
    syncDirectory([
        'admin-deleted@example.test' => ['password' => 'x'],
        'self-deleted@example.test' => ['password' => 'x'],
    ]);
    adminDeletedClient($this->admin, 'admin-deleted@example.test');
    $self = directoryClient('self-deleted@example.test');
    $self->delete();
    app(ActivityLogger::class)->log(Action::UserDeleted, $self);

    $people = collect(app(LdapSync::class)->run(dryRun: true)['people'])->keyBy('email');

    expect($people['admin-deleted@example.test'])->toMatchArray(['action' => 'skip', 'reason' => 'deleted', 'restorable' => true])
        ->and($people['self-deleted@example.test'])->toMatchArray(['action' => 'skip', 'reason' => 'deleted_by_owner'])
        ->and($people['self-deleted@example.test'])->not->toHaveKey('restorable');
});

test('sync now restores exactly the deleted clients ticked in the preview', function () {
    enableSync();
    syncDirectory([
        'ticked@example.test' => ['password' => 'x'],
        'unticked@example.test' => ['password' => 'x'],
    ]);
    $ticked = adminDeletedClient($this->admin, 'ticked@example.test');
    $unticked = adminDeletedClient($this->admin, 'unticked@example.test');

    $this->actingAs($this->admin)->post('/system/settings/ldap/sync', ['dry_run' => true]);
    $this->actingAs($this->admin)->post('/system/settings/ldap/sync', ['restore' => ['Ticked@example.test']])->assertSessionHasNoErrors();

    expect($ticked->fresh()->trashed())->toBeFalse()
        ->and($unticked->fresh()->trashed())->toBeTrue()
        ->and(app(LdapSync::class)->last()['restored'])->toBe(1);
});

test('unticking every deleted client restores nobody, even with restoring on', function () {
    enableSync(restoreDeleted: true);
    syncDirectory(['deleted@example.test' => ['password' => 'x']]);
    $deleted = adminDeletedClient($this->admin, 'deleted@example.test');
    $preview = app(LdapSync::class)->run(dryRun: true);

    app(LdapSync::class)->run(plan: $preview['plan'], restore: []);

    expect($deleted->fresh()->trashed())->toBeTrue();
});

test('a ticked address the preview could not restore stays deleted', function () {
    enableSync();
    syncDirectory(['self-deleted@example.test' => ['password' => 'x']]);
    $self = directoryClient('self-deleted@example.test');
    $self->delete();
    app(ActivityLogger::class)->log(Action::UserDeleted, $self);
    $preview = app(LdapSync::class)->run(dryRun: true);

    app(LdapSync::class)->run(plan: $preview['plan'], restore: ['self-deleted@example.test']);

    expect($self->fresh()->trashed())->toBeTrue();
});
