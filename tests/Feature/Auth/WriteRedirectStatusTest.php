<?php

use App\Models\User;
use App\Modules\Identity\TwoFactor\TwoFactorEnforcement;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

// A redirect answered to a PUT/PATCH/DELETE must be a 303, not a 302:
// browsers follow a 302 by replaying the same method on the redirect
// target (only POST is downgraded to GET), so "your session expired,
// please sign in" turns into PUT /login — and /login only takes GET and
// POST, so the user sees an unexplainable 405 instead of the login page.
// Redirects born in exception handling (the guest redirect above all)
// never pass back through Inertia's middleware, which normally does this
// upgrade — bootstrap/app.php repeats it for them. See issue #1673.

it('answers an unauthenticated write with 303 so the browser lands on the login page', function () {
    $this->put(route('dashboard.widgets.update'), [])
        ->assertStatus(303)
        ->assertRedirect(route('login'));
});

it('answers an unauthenticated delete with 303 as well', function () {
    $user = User::factory()->create();

    $this->delete(route('users.destroy', $user))
        ->assertStatus(303)
        ->assertRedirect(route('login'));
});

it('keeps the plain 302 for unauthenticated reads', function () {
    $this->get(route('dashboard'))
        ->assertStatus(302)
        ->assertRedirect(route('login'));
});

// The three middleware below answer *before* HandleInertiaRequests, so a
// response they return never unwinds through Inertia's 302→303 upgrade
// either — the same 405 as above, reached a different way. Flagged in
// #1680 as deliberately out of scope there; these cover it.

it('answers a write with 303 when the installation has no administrator yet', function () {
    // Deliberately no staff user: that is what EnsureSetupIsComplete
    // reacts to, and every other test in the suite creates one.
    User::query()->delete();

    // PUT /timezone rather than a dashboard route: it is one of only two
    // writes a guest can reach, and the only one EnsureSetupIsComplete
    // does not exempt. Anything behind `auth` is answered by the guest
    // redirect first, which is the case #1680 already covers.
    $this->put(route('timezone.update'), ['timezone' => 'UTC'])
        ->assertStatus(303)
        ->assertRedirect(route('setup'));
});

it('answers a write with 303 when the account was deactivated mid-session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['active' => false]);

    $this->put(route('dashboard.widgets.update'), [])
        ->assertStatus(303)
        ->assertRedirect(route('login'));
});

it('answers a write with 303 when two-factor enrolment is being enforced', function () {
    $user = User::factory()->create();

    // Set explicitly rather than relying on the default: the settings
    // cache outlives a database rollback in this suite.
    app(Settings::class)->set(Setting::TwoFactorEnforcement, TwoFactorEnforcement::All->value);

    $this->actingAs($user)
        ->put(route('dashboard.widgets.update'), [])
        ->assertStatus(303)
        ->assertRedirect(route('two-factor.show'));
});

it('leaves a read alone in every one of those cases', function () {
    $user = User::factory()->create();
    $user->update(['active' => false]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertStatus(302)
        ->assertRedirect(route('login'));
});
