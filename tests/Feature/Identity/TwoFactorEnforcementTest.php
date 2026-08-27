<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

test('by default nobody is forced into two-factor setup', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('staff enforcement walks un-enrolled staff to the 2fa setup screen', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'staff');

    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertRedirect(route('two-factor.show'));
});

test('staff enforcement leaves clients alone', function () {
    User::factory()->create();
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'staff');

    $this->actingAs(User::factory()->client()->create());

    $this->get('/dashboard')->assertOk();
});

test('client enforcement walks un-enrolled clients to the 2fa setup screen', function () {
    User::factory()->create();
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'clients');

    $this->actingAs(User::factory()->client()->create());

    $this->get('/dashboard')->assertRedirect(route('two-factor.show'));
});

test('everyone enforcement covers both types', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');

    $this->actingAs(User::factory()->create());
    $this->get('/dashboard')->assertRedirect(route('two-factor.show'));

    $this->actingAs(User::factory()->client()->create());
    $this->get('/dashboard')->assertRedirect(route('two-factor.show'));
});

test('the 2fa setup screen itself and logout stay reachable under enforcement', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');

    $this->actingAs(User::factory()->create());

    $this->get('/settings/two-factor')->assertOk();
    // Named rather than bare: enabling is behind password.confirm, so the
    // redirect it answers with is the confirm-password screen. A bare
    // assertRedirect() passes on any target, including this middleware
    // bouncing the request back to two-factor.show, which is the shape
    // this file exists to refuse.
    $this->post('/settings/two-factor')->assertRedirect(route('password.confirm'));
    $this->post('/logout')->assertRedirect('/');
});

test('enrolled users are not redirected under enforcement', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

    $this->actingAs($user);

    $this->get('/dashboard')->assertOk();
});

test('staff can change the enforcement setting', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/security')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/security')
            ->where('two_factor_enforcement', 'none'),
    );

    $this->patch('/system/settings/security', [
        'two_factor_enforcement' => 'clients',
        'password_min_length' => 12,
        'password_reject_breached' => true,
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::TwoFactorEnforcement))->toBe('clients');
});

test('an invalid enforcement value is rejected', function () {
    $this->actingAs(User::factory()->create());

    $this->patch('/system/settings/security', ['two_factor_enforcement' => 'sometimes'])
        ->assertSessionHasErrors('two_factor_enforcement');
});

test('clients cannot access security settings', function () {
    User::factory()->create();

    $this->actingAs(User::factory()->client()->create());

    $this->get('/system/settings/security')->assertRedirect(route('dashboard'));
    $this->patch('/system/settings/security', ['two_factor_enforcement' => 'all'])->assertForbidden();
});

/**
 * The exemption list matches on route names, and only the GET half of the
 * confirm-password screen had one. So the form rendered and its submission
 * did not: enforcement sent the POST back to two-factor.show, the password
 * was never confirmed, and enrolment -- the one exit enforcement leaves
 * open -- could not be started by anybody.
 */
test('an enforced user can confirm their password, which is what enrolling needs', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');

    $this->actingAs(User::factory()->create());

    $this->post('/settings/two-factor')->assertRedirect(route('password.confirm'));
    $this->get('/confirm-password')->assertOk();

    $this->post('/confirm-password', ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(session()->has('auth.password_confirmed_at'))->toBeTrue();
});

test('enrolment can actually be started under enforcement', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/confirm-password', ['password' => 'password']);

    // store() answers back(), so the target is the referer rather than a
    // fixed route -- what matters is that it ran at all instead of being
    // bounced to the confirm-password screen it can no longer get past.
    $this->post('/settings/two-factor')->assertSessionHasNoErrors();

    // The secret is what enabling writes; without it the enrolment screen
    // has no QR code to show and there is nothing to confirm against.
    expect($user->refresh()->two_factor_secret)->not->toBeNull();

    $this->get('/settings/two-factor')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('pending', true)
    );
});
