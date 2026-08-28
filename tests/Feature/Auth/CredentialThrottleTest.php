<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Every endpoint that checks the account's own password has a bucket.
 *
 * routes/auth.php says so at the top -- "Every `throttle:` below names its
 * own bucket, and must" -- and POST login is the documented exception,
 * because LoginRequest limits it per email *and* IP, which is stronger.
 * confirm-password had neither, and it is the door that stands in front of
 * disabling two-factor, regenerating recovery codes and minting an API
 * token.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
});

test('confirming a password stops accepting guesses', function () {
    foreach (range(1, 6) as $attempt) {
        $this->actingAs($this->user)
            ->post('/confirm-password', ['password' => "wrong-{$attempt}"])
            ->assertSessionHasErrors('password');
    }

    $this->actingAs($this->user)
        ->post('/confirm-password', ['password' => 'wrong-7'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('changing a password stops accepting guesses at the current one', function () {
    foreach (range(1, 6) as $attempt) {
        $this->actingAs($this->user)->put('/settings/password', [
            'current_password' => "wrong-{$attempt}",
            'password' => 'a-new-password-1',
            'password_confirmation' => 'a-new-password-1',
        ])->assertSessionHasErrors('current_password');
    }

    $this->actingAs($this->user)->put('/settings/password', [
        'current_password' => 'wrong-7',
        'password' => 'a-new-password-1',
        'password_confirmation' => 'a-new-password-1',
    ])->assertStatus(429);
});

test('deleting an account stops accepting guesses at the password', function () {
    foreach (range(1, 6) as $attempt) {
        $this->actingAs($this->user)
            ->delete('/settings/profile', ['password' => "wrong-{$attempt}"])
            ->assertSessionHasErrors('password');
    }

    $this->actingAs($this->user)
        ->delete('/settings/profile', ['password' => 'wrong-7'])
        ->assertStatus(429);

    expect(User::query()->whereKey($this->user->id)->exists())->toBeTrue();
});

test('the three buckets are separate, and separate from the rest', function () {
    // Named buckets, so exhausting one does not spend another's budget --
    // the failure the note at the top of routes/auth.php describes, where
    // opening six share links locked the visitor out of the two-factor
    // challenge.
    foreach (range(1, 7) as $attempt) {
        $this->actingAs($this->user)->post('/confirm-password', ['password' => "wrong-{$attempt}"]);
    }

    $this->actingAs($this->user)->put('/settings/password', [
        'current_password' => 'wrong-again',
        'password' => 'a-new-password-1',
        'password_confirmation' => 'a-new-password-1',
    ])->assertSessionHasErrors('current_password');
});

test('somebody who knows the password is not locked out by somebody who does not', function () {
    // The limiter is keyed on the account, not on the installation: a
    // second account's guesses must not cost this one its own attempts.
    $other = User::factory()->create();

    foreach (range(1, 7) as $attempt) {
        $this->actingAs($other)->post('/confirm-password', ['password' => "wrong-{$attempt}"]);
    }

    $this->actingAs($this->user)
        ->post('/confirm-password', ['password' => 'password'])
        ->assertSessionHasNoErrors();
});
