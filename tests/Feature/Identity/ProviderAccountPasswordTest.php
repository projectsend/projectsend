<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AuthSource;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

/**
 * An account provisioned by a provider has a generated password nobody
 * has ever seen. Until this, that meant it could never have one: the only
 * screen that sets a password asked for the current one first.
 *
 * That was not a cosmetic gap. Enrolling in two-factor is behind a
 * password confirmation, so an installation that makes two-factor
 * compulsory locked out everybody who signs in through a provider — the
 * enforcement middleware sent them to enrol, enrolling sent them to
 * confirm a password they do not have, and every other screen, including
 * the one that would have given them one, redirected back. Reported by
 * Ricardo Cazati.
 */
function providerAccount(array $overrides = []): User
{
    return User::factory()->create(array_merge(['auth_source' => AuthSource::Social], $overrides));
}

test('an account that signs in through a provider can set its first password', function () {
    $user = providerAccount();

    $this->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'password' => 'a-password-of-my-own',
            'password_confirmation' => 'a-password-of-my-own',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    $user->refresh();

    expect(Hash::check('a-password-of-my-own', $user->password))->toBeTrue()
        // The hash is now what signs this account in, and the settings
        // screens read that off this column.
        ->and($user->auth_source)->toBe(AuthSource::Local);
});

test('an ordinary account still has to prove the password it is replacing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'password' => 'a-password-of-my-own',
            'password_confirmation' => 'a-password-of-my-own',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('a-password-of-my-own', $user->refresh()->password))->toBeFalse();
});

test('a directory account is refused rather than given a password that signs nothing in', function () {
    $user = providerAccount(['auth_source' => AuthSource::Ldap]);

    $this->actingAs($user)
        ->put('/settings/password', [
            'password' => 'a-password-of-my-own',
            'password_confirmation' => 'a-password-of-my-own',
        ])
        ->assertForbidden();

    expect(Hash::check('a-password-of-my-own', $user->refresh()->password))->toBeFalse();
});

test('the screen says which of the two it is', function () {
    $this->actingAs(providerAccount())->get('/settings/password')->assertInertia(
        fn (AssertableInertia $page) => $page->where('has_local_password', false)->where('managed_elsewhere', false),
    );

    $this->actingAs(User::factory()->create())->get('/settings/password')->assertInertia(
        fn (AssertableInertia $page) => $page->where('has_local_password', true),
    );
});

test('the confirm-password screen offers to set one instead of asking for it', function () {
    $this->actingAs(providerAccount())->get('/confirm-password')->assertInertia(
        fn (AssertableInertia $page) => $page->component('auth/confirm-password')->where('has_password', false),
    );
});

/*
|--------------------------------------------------------------------------
| The lockout
|--------------------------------------------------------------------------
*/

test('compulsory two-factor leaves a provider account a way to get a password', function () {
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'staff');
    $user = providerAccount();

    // Enforcement holds everywhere else, as before.
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('two-factor.show'));

    // But not on the one screen that can end the loop.
    $this->actingAs($user)->get('/settings/password')->assertOk();

    $this->actingAs($user)
        ->put('/settings/password', [
            'password' => 'a-password-of-my-own',
            'password_confirmation' => 'a-password-of-my-own',
        ])
        ->assertSessionHasNoErrors();

    // And with one, the password confirmation in front of enrolling is
    // answerable, so two-factor can actually be turned on.
    $this->actingAs($user->refresh())
        ->post('/confirm-password', ['password' => 'a-password-of-my-own'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->post('/settings/two-factor')->assertSessionHasNoErrors();

    expect($user->refresh()->two_factor_secret)->not->toBeNull();
});
