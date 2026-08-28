<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Ldap\LdapAuthenticator;
use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapSettings;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeLdapDirectory;

beforeEach(function () {
    // Every HTTP test needs a staff account or EnsureSetupIsComplete
    // redirects the whole app to /setup.
    User::factory()->create();
});

/**
 * @param  array<string, array{password: string, name?: string, dn?: string}>  $entries
 */
function fakeDirectory(array $entries = []): FakeLdapDirectory
{
    $fake = new FakeLdapDirectory($entries);
    test()->swap(LdapDirectory::class, $fake);

    return $fake;
}

function enableLdap(bool $autoProvision = false, bool $autoApprove = false): LdapSettings
{
    $settings = LdapSettings::current();
    $settings->forceFill([
        'active' => true,
        'host' => 'ldap.example.test',
        'base_dn' => 'dc=example,dc=test',
        'auto_provision' => $autoProvision,
        'auto_approve' => $autoApprove,
    ])->save();

    return $settings;
}

/*
|--------------------------------------------------------------------------
| When the directory is not consulted at all
|--------------------------------------------------------------------------
*/

test('a login is unchanged when LDAP is switched off', function () {
    $fake = fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);

    $this->post('/login', ['email' => $client->email, 'password' => 'directory-pass'])
        ->assertSessionHasErrors('email');

    expect($fake->calls)->toBe(0);
    $this->assertGuest();
});

// A directory should never see a password that already worked locally.
test('a valid local password costs no directory traffic', function () {
    enableLdap();
    $fake = fakeDirectory();
    $client = User::factory()->client()->create();

    $this->post('/login', ['email' => $client->email, 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($client);
    expect($fake->calls)->toBe(0);
});

// LDAP is client-only, enforced on the account rather than on a setting,
// so no misconfiguration can make the directory a route to staff authority.
test('a staff account never reaches the directory, even with a matching entry', function () {
    enableLdap();
    $staff = User::factory()->role(SystemRole::Uploader)->create(['email' => 'boss@example.test']);
    $fake = fakeDirectory(['boss@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => $staff->email, 'password' => 'directory-pass'])
        ->assertSessionHasErrors('email');

    expect($fake->calls)->toBe(0);
    $this->assertGuest();
});

// RFC 4513: a simple bind with an empty password against a valid DN is an
// *unauthenticated* bind and succeeds — the classic LDAP auth bypass. Three
// layers stop it here, and this asserts the outcome they share rather than
// which one fired: no bind is attempted. (In practice the outermost wins —
// TrimStrings collapses "   " to "" and `required` rejects it on the
// password field — but LdapAuthenticator trims again, because a route that
// ever opted out of TrimStrings must not silently become an auth bypass.)
test('a whitespace-only password never reaches the directory', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    $fake = fakeDirectory(['someone@example.test' => ['password' => '   ']]);

    $this->post('/login', ['email' => $client->email, 'password' => '   '])
        ->assertSessionHasErrors('password');

    expect($fake->calls)->toBe(0);
    $this->assertGuest();
});

// The guard on its own, with the middleware out of the picture — the layer
// that would matter if the outer one were ever removed.
test('the authenticator itself refuses a whitespace password', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    $fake = fakeDirectory(['someone@example.test' => ['password' => '   ']]);

    $identity = app(LdapAuthenticator::class)
        ->attempt('someone@example.test', '   ', $client);

    expect($identity)->toBeNull()
        ->and($fake->calls)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Authenticating an existing client
|--------------------------------------------------------------------------
*/

test('a client whose local password fails is checked against the directory', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => $client->email, 'password' => 'directory-pass'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($client);

    // The entry it matched is recorded, so an administrator can see which
    // directory object the account corresponds to.
    expect($client->refresh()->ldap_dn)->toContain('someone@example.test')
        ->and($client->ldap_synced_at)->not->toBeNull();
});

test('a wrong directory password is refused', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => $client->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

// An account whose credentials live in the directory has a local hash
// nobody holds, so it must not be consulted.
test('a directory-sourced account skips the local password entirely', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    $client->forceFill(['auth_source' => AuthSource::Ldap])->save();
    $fake = fakeDirectory();

    // 'password' is the factory's local password and would work for a
    // local account.
    $this->post('/login', ['email' => $client->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect($fake->calls)->toBe(1);
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Account state still decides
|--------------------------------------------------------------------------
*/

test('a deactivated client with a valid directory password is told so', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test', 'active' => false]);
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => $client->email, 'password' => 'directory-pass'])
        ->assertSessionHasErrors(['email' => 'Your account has been deactivated.']);
});

// The state must not leak to somebody without the password — the property
// the phase ordering exists to preserve.
test('a deactivated client with a wrong directory password gets the generic failure', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test', 'active' => false]);
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    $response = $this->post('/login', ['email' => $client->email, 'password' => 'wrong']);

    expect(session('errors')->first('email'))->not->toBe('Your account has been deactivated.');
});

test('two-factor still challenges a directory-authenticated client', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    $client->forceFill([
        'two_factor_secret' => 'secret',
        'two_factor_confirmed_at' => now(),
    ])->save();
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => $client->email, 'password' => 'directory-pass'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session('two_factor.login_id'))->toBe($client->id);
});

// Every failure, from any credential source, funnels through one refusal —
// the property most likely to be lost by bolting LDAP onto the end.
test('failed directory attempts are rate limited like any other', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => $client->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $client->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many login attempts');
});

/*
|--------------------------------------------------------------------------
| Provisioning
|--------------------------------------------------------------------------
*/

test('an unknown directory identity is refused when auto-provisioning is off', function () {
    enableLdap(autoProvision: false);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass'])
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'newcomer@example.test')->exists())->toBeFalse();
});

test('auto-provisioning creates a client, never staff, and signs them in', function () {
    enableLdap(autoProvision: true, autoApprove: true);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass', 'name' => 'New Comer']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass'])
        ->assertRedirect(route('dashboard', absolute: false));

    $created = User::query()->where('email', 'newcomer@example.test')->sole();

    expect($created->isClient())->toBeTrue()
        ->and($created->name)->toBe('New Comer')
        ->and($created->active)->toBeTrue()
        ->and($created->auth_source)->toBe(AuthSource::Ldap)
        ->and($created->ldap_dn)->not->toBeNull();

    $this->assertAuthenticatedAs($created);

    expect(ActivityLog::query()->where('action', Action::LdapClientProvisioned)->exists())->toBeTrue();
});

// A deleted account keeps its address: the unique index spans trashed
// rows, which is what AvailableEmailRule is built on. Provisioning over
// one used to raise a QueryException in the middle of the sign-in.
test('a directory identity whose address belongs to a deleted account is refused, not crashed', function () {
    enableLdap(autoProvision: true, autoApprove: true);
    fakeDirectory(['gone@example.test' => ['password' => 'directory-pass', 'name' => 'Gone Again']]);

    $gone = User::factory()->client()->create(['email' => 'gone@example.test']);
    $gone->delete();

    $this->post('/login', ['email' => 'gone@example.test', 'password' => 'directory-pass'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    // Nothing was created, and the deleted account was not resurrected.
    expect(User::query()->where('email', 'gone@example.test')->exists())->toBeFalse()
        ->and(User::withTrashed()->where('email', 'gone@example.test')->count())->toBe(1)
        ->and(User::withTrashed()->where('email', 'gone@example.test')->sole()->trashed())->toBeTrue();
});

// The directory proves who you are; the installation still decides whether
// it wants you. This falls out of the phase ordering with no special case.
test('auto-provisioning honours the directory approval setting', function () {
    enableLdap(autoProvision: true, autoApprove: false);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass'])
        ->assertSessionHasErrors(['email' => 'Your account request has not been approved yet.']);

    $created = User::query()->where('email', 'newcomer@example.test')->sole();

    expect($created->active)->toBeFalse()
        ->and($created->account_requested)->toBeTrue();

    $this->assertGuest();
});

// The two approval settings answer different questions — strangers at a
// public form versus people the directory has already authenticated — so
// neither may quietly follow the other. Both are set explicitly here: the
// Settings cache outlives a RefreshDatabase rollback, so an assertion about
// a "default" value is not trustworthy.
test('directory accounts are approved on their own setting, not the registration one', function () {
    enableLdap(autoProvision: true, autoApprove: true);
    app(Settings::class)->set(Setting::ClientsAutoApprove, false);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass'])
        ->assertRedirect(route('dashboard', absolute: false));

    $created = User::query()->where('email', 'newcomer@example.test')->sole();

    expect($created->active)->toBeTrue()
        ->and($created->account_requested)->toBeFalse();
});

test('directory accounts can wait for approval while registrations do not', function () {
    enableLdap(autoProvision: true, autoApprove: false);
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass'])
        ->assertSessionHasErrors(['email' => 'Your account request has not been approved yet.']);

    $created = User::query()->where('email', 'newcomer@example.test')->sole();

    expect($created->active)->toBeFalse()
        ->and($created->account_requested)->toBeTrue();

    $this->assertGuest();
});

test('auto-provisioning honours the auto-join group', function () {
    enableLdap(autoProvision: true, autoApprove: true);
    $group = Group::query()->create(['name' => 'Directory folk']);
    app(Settings::class)->set(Setting::ClientsAutoGroup, $group->id);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass']);

    expect($group->fresh()->members)->toHaveCount(1);
});

// Closing the public registration form must not silently break directory
// sign-in — they have separate switches on purpose.
test('provisioning does not depend on the public registration setting', function () {
    enableLdap(autoProvision: true, autoApprove: true);
    app(Settings::class)->set(Setting::ClientsCanRegister, false);
    fakeDirectory(['newcomer@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => 'newcomer@example.test', 'password' => 'directory-pass'])
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'newcomer@example.test')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Editions
|--------------------------------------------------------------------------
*/

// LDAP is an admin setting, not an edition difference — it works in both.
test('the directory is consulted in the cloud edition too', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    $this->post('/login', ['email' => $client->email, 'password' => 'directory-pass'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($client);
});

/*
|--------------------------------------------------------------------------
| Availability
|--------------------------------------------------------------------------
*/

// An unreachable directory must not become an outage on the login page for
// everyone, including local accounts.
test('the circuit breaker skips the directory while it is open', function () {
    enableLdap();
    $client = User::factory()->client()->create(['email' => 'someone@example.test']);
    $fake = fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);

    Cache::put('identity.ldap.unreachable', true, 60);

    $this->post('/login', ['email' => $client->email, 'password' => 'directory-pass'])
        ->assertSessionHasErrors('email');

    expect($fake->calls)->toBe(0);
});

test('settings with no host are treated as switched off', function () {
    $settings = LdapSettings::current();
    $settings->forceFill(['active' => true, 'host' => null, 'base_dn' => null])->save();

    $fake = fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);
    User::factory()->client()->create(['email' => 'someone@example.test']);

    $this->post('/login', ['email' => 'someone@example.test', 'password' => 'directory-pass'])
        ->assertSessionHasErrors('email');

    expect($fake->calls)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Re-proving the password, once signed in
|--------------------------------------------------------------------------
| The confirm-password screen asked the local hash and nothing else. A
| directory account's local hash is a Str::password(64) nobody has ever
| seen, so it refused them the only password they have -- and that screen
| stands in front of enrolling in two-factor.
*/

test('a directory account can confirm its password with its directory password', function () {
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);
    enableLdap();

    $client = User::factory()->client()->create([
        'email' => 'someone@example.test',
        'auth_source' => AuthSource::Ldap,
    ]);

    $this->actingAs($client)
        ->post('/confirm-password', ['password' => 'directory-pass'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(session()->has('auth.password_confirmed_at'))->toBeTrue();
});

test('and can therefore enrol in two-factor at all', function () {
    // The consequence that matters. Under TwoFactorEnforcement this is not
    // a missing convenience -- EnforceTwoFactor redirects every request to
    // two-factor.show, and the enrolment behind password.confirm could
    // never be reached, so the account had nowhere to go.
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);
    enableLdap();

    $client = User::factory()->client()->create([
        'email' => 'someone@example.test',
        'auth_source' => AuthSource::Ldap,
    ]);

    $this->actingAs($client)->post('/confirm-password', ['password' => 'directory-pass']);
    $this->actingAs($client)->post('/settings/two-factor')->assertRedirect();

    expect($client->refresh()->two_factor_secret)->not->toBeNull();
});

test('the wrong directory password is still refused', function () {
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);
    enableLdap();

    $client = User::factory()->client()->create([
        'email' => 'someone@example.test',
        'auth_source' => AuthSource::Ldap,
    ]);

    $this->actingAs($client)
        ->post('/confirm-password', ['password' => 'not-the-directory-pass'])
        ->assertSessionHasErrors('password');

    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
});

test('the local hash of a directory account is still not a way in', function () {
    // The placeholder must stay unusable: if somebody knew it, confirming
    // with it would be a second door that keeps working after LDAP is
    // switched off.
    fakeDirectory(['someone@example.test' => ['password' => 'directory-pass']]);
    enableLdap();

    $client = User::factory()->client()->create([
        'email' => 'someone@example.test',
        'auth_source' => AuthSource::Ldap,
        'password' => Hash::make('the-local-placeholder'),
    ]);

    $this->actingAs($client)
        ->post('/confirm-password', ['password' => 'the-local-placeholder'])
        ->assertSessionHasErrors('password');
});

test('a local account still confirms against its own hash, with LDAP on', function () {
    fakeDirectory([]);
    enableLdap();

    $staff = User::factory()->create();

    $this->actingAs($staff)
        ->post('/confirm-password', ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});
