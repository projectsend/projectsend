<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('a fresh install redirects every page to setup', function () {
    $this->get('/login')->assertRedirect(route('setup'));
    $this->get('/')->assertRedirect(route('setup'));
});

test('the setup screen renders while no staff user exists', function () {
    $this->get('/setup')->assertInertia(
        fn (AssertableInertia $page) => $page->component('setup'),
    );
});

test('client accounts do not count as setup being complete', function () {
    User::factory()->client()->create();

    $this->get('/login')->assertRedirect(route('setup'));
});

test('setup creates the first staff administrator without logging them in', function () {
    $response = $this->post('/setup', [
        'site_name' => 'ProjectSend',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $response->assertRedirect(route('setup.success'));
    $this->assertGuest();

    $user = User::query()->sole();
    expect($user->type)->toBe(UserType::Staff)
        ->and($user->email)->toBe('admin@example.com')
        // Written with forceFill, because email_verified_at is not in
        // User::$fillable and the create() array it used to sit in threw
        // it away without a word. This administrator typed their own
        // address into the form in front of them.
        ->and($user->email_verified_at)->not->toBeNull();
});

test('setup seeds the admin notification recipient with the new administrator email', function () {
    $this->post('/setup', [
        'site_name' => 'ProjectSend',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    expect(app(Settings::class)->get(Setting::AdminNotificationEmails))->toBe(['admin@example.com']);
});

test('the success page is shown right after setup and links to login', function () {
    $this->post('/setup', [
        'site_name' => 'ProjectSend',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $this->get('/setup/success')->assertInertia(
        fn (AssertableInertia $page) => $page->component('setup-success'),
    );
});

test('the success page redirects to login when visited outside the setup flow', function () {
    User::factory()->create();

    $this->get('/setup/success')->assertRedirect(route('login'));
});

test('the success page redirects to setup while no staff user exists', function () {
    $this->get('/setup/success')->assertRedirect(route('setup'));
});

test('once a staff user exists the app behaves normally and setup bounces home', function () {
    User::factory()->create();

    $this->get('/login')->assertOk();
    $this->get('/setup')->assertRedirect(route('home'));
    $this->post('/setup', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'irrelevant-password',
        'password_confirmation' => 'irrelevant-password',
    ])->assertRedirect(route('home'));

    expect(User::query()->count())->toBe(1);
});

test('registration is client-only and hidden until enabled', function () {
    // Fresh install: everything, including /register, goes to setup.
    $this->get('/register')->assertRedirect(route('setup'));

    // Installed with the setting off (default): hidden entirely.
    User::factory()->create();
    $this->get('/register')->assertNotFound();
});

test('the projectsend:admin command creates a staff administrator', function () {
    $this->artisan('projectsend:admin', [
        '--name' => 'CLI Admin',
        '--email' => 'cli@example.com',
        '--password' => 'super-secret-password',
    ])->assertSuccessful();

    $user = User::query()->sole();
    expect($user->type)->toBe(UserType::Staff)
        // Same silent drop as the setup screen had: whoever provisioned
        // this container supplied the address themselves.
        ->and($user->email_verified_at)->not->toBeNull();
});

test('the projectsend:admin command rejects invalid input', function () {
    $this->artisan('projectsend:admin', [
        '--name' => 'CLI Admin',
        '--email' => 'not-an-email',
        '--password' => 'super-secret-password',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('projectsend:admin --if-none is a no-op when a staff user exists', function () {
    User::factory()->create();

    $this->artisan('projectsend:admin', [
        '--if-none' => true,
        '--name' => 'Second Admin',
        '--email' => 'second@example.com',
        '--password' => 'super-secret-password',
    ])->assertSuccessful();

    expect(User::query()->count())->toBe(1);
});

test('projectsend:admin --if-none still creates when only clients exist', function () {
    User::factory()->client()->create();

    $this->artisan('projectsend:admin', [
        '--if-none' => true,
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'super-secret-password',
    ])->assertSuccessful();

    expect(User::query()->where('type', UserType::Staff)->count())->toBe(1);
});

test('projectsend:admin seeds the admin notification recipient when unset', function () {
    $this->artisan('projectsend:admin', [
        '--name' => 'CLI Admin',
        '--email' => 'cli@example.com',
        '--password' => 'super-secret-password',
    ])->assertSuccessful();

    expect(app(Settings::class)->get(Setting::AdminNotificationEmails))->toBe(['cli@example.com']);
});

test('projectsend:admin does not overwrite an already-configured recipient list', function () {
    app(Settings::class)->set(Setting::AdminNotificationEmails, ['existing@example.com']);

    $this->artisan('projectsend:admin', [
        '--if-none' => true,
        '--name' => 'Second Admin',
        '--email' => 'second@example.com',
        '--password' => 'super-secret-password',
    ])->assertSuccessful();

    expect(app(Settings::class)->get(Setting::AdminNotificationEmails))->toBe(['existing@example.com']);
});

/**
 * GHSA-w3w9-prpw-qx77. Two setup requests arriving together both read "no
 * staff user" and both insert a System Administrator, so a stranger racing
 * the operator's own submission ends up with a permanent account while the
 * operator's install looks perfectly normal.
 *
 * Real concurrency is not available inside one test, so the interleaving is
 * staged instead: the winning administrator appears from a query listener,
 * after this request has already made its "is setup complete" check and
 * before it inserts anything. That is precisely the window, and the fix is
 * the only thing that closes it — asking the question again with the claim
 * held.
 */
function interruptWithAnAdministrator(string $email): void
{
    $done = false;

    DB::listen(function (QueryExecuted $query) use (&$done, $email): void {
        if ($done || ! str_contains($query->sql, 'roles')) {
            return;
        }

        $done = true;

        User::factory()->create(['email' => $email]);
    });
}

test('a setup request that loses the race creates no second administrator', function () {
    app(Settings::class)->set(Setting::SiteName, 'The Operator Site');

    interruptWithAnAdministrator('winner@example.com');

    $this->post('/setup', [
        'site_name' => 'Intruder Site',
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect(route('home'));

    expect(User::query()->where('type', UserType::Staff)->count())->toBe(1)
        ->and(User::query()->sole()->email)->toBe('winner@example.com')
        // The loser writes nothing at all, so it cannot rename the
        // installation on its way out either.
        ->and(app(Settings::class)->get(Setting::SiteName))->toBe('The Operator Site');
});

test('projectsend:admin --if-none creates nothing when an administrator appears mid-run', function () {
    interruptWithAnAdministrator('winner@example.com');

    $this->artisan('projectsend:admin', [
        '--if-none' => true,
        '--name' => 'Second Admin',
        '--email' => 'second@example.com',
        '--password' => 'super-secret-password',
    ])->assertSuccessful();

    expect(User::query()->where('type', UserType::Staff)->count())->toBe(1)
        ->and(User::query()->sole()->email)->toBe('winner@example.com');
});
