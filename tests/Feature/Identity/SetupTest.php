<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
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
        ->and($user->email)->toBe('admin@example.com');
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

    expect(User::query()->sole()->type)->toBe(UserType::Staff);
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
