<?php

use App\Models\User;

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
