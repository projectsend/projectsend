<?php

use App\Models\User;
use App\Modules\Identity\AuthSource;

/*
 * password.confirm used to answer every request with a redirect to the
 * confirm-password screen. For a write that throws the submission away:
 * the redirect cannot carry a POST body, so the user came back to an
 * empty form and the action never ran. An Inertia request now gets a 423
 * the browser turns into a dialog over the page, and the same request is
 * sent again once the password is proved.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('an inertia write without a fresh confirmation is refused in place, not redirected', function () {
    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423)
        ->assertHeader('X-Password-Confirmation', 'required')
        ->assertJson(['has_password' => true]);

    // Refused, not half-done: the action did not run.
    expect($this->user->refresh()->two_factor_secret)->toBeNull();
});

test('the refusal tells the dialog when there is no password to type', function () {
    $user = User::factory()->create(['auth_source' => AuthSource::Social]);

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423)
        ->assertJson(['has_password' => false]);
});

// A directory account's password is the directory's, and the confirmation
// accepts it (LdapAuthenticationTest). Asking "is this account Local?"
// told it to set a password instead, which it is not allowed to do, so it
// could not get past the confirmation at all -- on the screen or here.
test('a directory account is asked for its password, not told to set one', function () {
    $user = User::factory()->create(['auth_source' => AuthSource::Ldap]);

    // The screen first: withHeaders() sticks to every later request in a
    // test, and this GET must not be sent as an Inertia visit.
    $this->actingAs($user)
        ->get('/confirm-password')
        ->assertInertia(fn ($page) => $page->component('auth/confirm-password')->where('has_password', true));

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423)
        ->assertJson(['has_password' => true]);
});

test('a plain form post is still redirected to the confirm-password screen', function () {
    $this->actingAs($this->user)
        ->post('/settings/two-factor')
        ->assertRedirect(route('password.confirm'));
});

test('the dialog confirms over json, and the replayed request goes through', function () {
    $this->actingAs($this->user)
        ->postJson('/confirm-password', ['password' => 'password'])
        ->assertNoContent();

    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertRedirect();

    expect($this->user->refresh()->two_factor_secret)->not->toBeNull();
});

test('a wrong password in the dialog is a validation error, and confirms nothing', function () {
    $this->actingAs($this->user)
        ->postJson('/confirm-password', ['password' => 'wrong-password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423);
});
