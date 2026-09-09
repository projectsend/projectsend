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
| exists". This screen must not undo that, and the first version of it did:
| a real address answered "expired" and an unknown one drew the form, so
| the difference between the two answers was the account.
*/

test('an address nobody has gets the same answer as one that exists', function () {
    // The oracle, pinned. Not "both are false" or "both are true" — both
    // are *the same*, which is the property, and it survives somebody
    // later changing which answer that is.
    User::factory()->create(['email' => 'known@example.com']);

    $expiredFor = function (string $email): bool {
        $seen = null;
        test()->get("/reset-password/not-a-real-token?email={$email}")->assertOk()->assertInertia(
            function (AssertableInertia $page) use (&$seen) {
                $seen = $page->toArray()['props']['expired'];
            },
        );

        return (bool) $seen;
    };

    expect($expiredFor('known@example.com'))->toBe($expiredFor('nobody@example.com'));
});

test('a missing address reads as expired rather than as a form', function () {
    // Same rule seen from the other side. The form needs an address to
    // post, so drawing it here would ask for a password it cannot use.
    $this->get('/reset-password/not-a-real-token')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('expired', true));
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
