<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;

/**
 * An expired reset link should say so before asking for the work, not
 * after it.
 *
 * The scaffolding renders the form without looking at the token, so
 * somebody opening a link an hour late typed a password, typed it again to
 * confirm, and was then told "this password reset token is invalid" — a
 * word nobody outside the code knows, at the end rather than the start.
 *
 * store() still validates and is still the rule. This is only the screen
 * being honest a minute earlier.
 */
beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'owner@example.com']);
});

test('a live link still shows the form', function () {
    $token = Password::broker()->createToken($this->user);

    $this->get("/reset-password/{$token}?email=owner@example.com")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/reset-password')
            ->where('expired', false));
});

test('a spent link says so instead of asking for a password', function () {
    $this->get('/reset-password/not-a-real-token?email=owner@example.com')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('expired', true));
});

test('a link whose token has been used is spent', function () {
    $token = Password::broker()->createToken($this->user);
    Password::broker()->deleteToken($this->user);

    $this->get("/reset-password/{$token}?email=owner@example.com")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('expired', true));
});

/*
|--------------------------------------------------------------------------
| It must not answer whether an account exists
|--------------------------------------------------------------------------
|
| /forgot-password deliberately says "a link will be sent if the account
| exists". A screen that said "expired" for a real address and something
| else for an unknown one would give that away to anybody typing guesses.
*/

test('an address nobody has is not called expired', function () {
    $this->get('/reset-password/not-a-real-token?email=nobody@example.com')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('expired', false));
});

test('a missing address is not called expired either', function () {
    $this->get('/reset-password/not-a-real-token')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('expired', false));
});

test('the real check still happens on the write', function () {
    // The screen is a courtesy; store() is the rule. A spent token is
    // refused there whatever the page decided to draw.
    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'owner@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('a-brand-new-password', $this->user->fresh()->password))->toBeFalse();
});
