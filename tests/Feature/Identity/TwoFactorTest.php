<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

// confirmPassword() and enableTwoFactor() live in tests/Helpers.php —
// more than one file needs them, and a helper used by more than one file
// has to be loaded from there or a --filter run cannot find it.

test('a user can enroll in two-factor authentication', function () {
    $user = User::factory()->create();

    confirmPassword($user);
    $this->actingAs($user)->post('/settings/two-factor')->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->two_factor_secret);

    $this->actingAs($user)
        ->post('/settings/two-factor/confirm', ['code' => $code])
        ->assertRedirect()
        ->assertSessionHas('two_factor_recovery_codes');

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});

test('confirming with a wrong code fails and leaves 2fa disabled', function () {
    $user = User::factory()->create();

    confirmPassword($user);
    $this->actingAs($user)->post('/settings/two-factor');

    $this->actingAs($user)
        ->post('/settings/two-factor/confirm', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('login with 2fa enabled requires the challenge instead of creating a session', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    Auth::logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->refresh()->two_factor_secret);

    $this->post('/two-factor-challenge', ['code' => $code])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('a totp code cannot be replayed', function () {
    $user = User::factory()->create();
    $secret = enableTwoFactor($user);

    Auth::logout();
    $this->flushSession();

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $code]);

    $this->assertAuthenticatedAs($user);

    Auth::logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a recovery code logs in and is consumed', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    /** @var list<string> $codes */
    $codes = $user->refresh()->two_factor_recovery_codes;
    $recovery = $codes[0];

    Auth::logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $recovery])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->two_factor_recovery_codes)->toHaveCount(7)
        ->and($user->two_factor_recovery_codes)->not->toContain($recovery);

    // The same code again is rejected.
    Auth::logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $recovery])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('the challenge is not reachable without a pending login', function () {
    User::factory()->create();

    $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect(route('login'));
});

test('wrong credentials never reach the challenge even with 2fa enabled', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    Auth::logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session('two_factor.login_id'))->toBeNull();
});

test('disabling 2fa clears all two-factor state', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    confirmPassword($user);
    $this->actingAs($user)->delete('/settings/two-factor')->assertRedirect();

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();
});

test('regenerating recovery codes replaces the set', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    /** @var list<string> $before */
    $before = $user->refresh()->two_factor_recovery_codes;

    confirmPassword($user);
    $this->actingAs($user)->post('/settings/two-factor/recovery-codes')->assertRedirect();

    /** @var list<string> $after */
    $after = $user->refresh()->two_factor_recovery_codes;

    expect($after)->toHaveCount(8)->and(array_intersect($before, $after))->toBeEmpty();
});

// A stolen session is exactly the situation 2FA exists to survive, so the
// second factor must not be removable with nothing but that session.
test('the two-factor mutation routes require a fresh password confirmation', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    // enableTwoFactor() confirmed the password; drop that back out of the
    // session to model a session that never proved the first factor.
    $this->withSession(['auth.password_confirmed_at' => null]);

    $this->actingAs($user)->post('/settings/two-factor')->assertRedirect(route('password.confirm'));
    $this->actingAs($user)->post('/settings/two-factor/recovery-codes')->assertRedirect(route('password.confirm'));
    $this->actingAs($user)->delete('/settings/two-factor')->assertRedirect(route('password.confirm'));

    expect($user->refresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_secret)->not->toBeNull();

    // With the password proved, the same request goes through.
    confirmPassword($user);
    $this->actingAs($user)->delete('/settings/two-factor')->assertRedirect();
    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});
