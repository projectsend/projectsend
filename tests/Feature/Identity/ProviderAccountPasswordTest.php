<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AuthSource;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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
 *
 * The first answer let the signed-in session choose the password with no
 * proof at all, which made a stolen session permanent: the thief set a
 * password, confirmed it, enrolled their own second factor and removed the
 * owner's provider (GHSA-4r8h-mwfm-f5f4). The password now comes from a
 * link emailed to the account's own address, through the same reset flow
 * every account already has. The session asks for the link; the inbox
 * answers it.
 */
function providerAccount(array $overrides = []): User
{
    return User::factory()->create(array_merge(['auth_source' => AuthSource::Social], $overrides));
}

test('a provider account cannot set its first password from the session alone', function () {
    $user = providerAccount();

    $this->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'password' => 'chosen-by-whoever-holds-the-session',
            'password_confirmation' => 'chosen-by-whoever-holds-the-session',
        ])
        ->assertSessionHasErrors('password');

    $user->refresh();

    expect(Hash::check('chosen-by-whoever-holds-the-session', $user->password))->toBeFalse()
        ->and($user->auth_source)->toBe(AuthSource::Social);
});

test('a provider account asks for a link, and it goes to its own address', function () {
    Notification::fake();
    $user = providerAccount();

    $this->actingAs($user)->from('/settings/password')->post('/settings/password/link')
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('the link sets the first password, signed in or not, and makes the account local', function () {
    $user = providerAccount();
    $token = Password::createToken($user);

    // Opened in the same browser the person is signed in with.
    $this->actingAs($user)->get("/reset-password/{$token}?email=".urlencode($user->email))->assertOk();

    $this->actingAs($user)->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-password-of-my-own',
        'password_confirmation' => 'a-password-of-my-own',
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect(Hash::check('a-password-of-my-own', $user->password))->toBeTrue()
        ->and($user->auth_source)->toBe(AuthSource::Local);
});

test('using the link signs out the session that asked for it', function () {
    $user = providerAccount();
    $token = Password::createToken($user);

    $this->actingAs($user)->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-password-of-my-own',
        'password_confirmation' => 'a-password-of-my-own',
    ]);

    // Whoever held the session before has the password no longer: the
    // next thing it asks for sends it to sign in. forgetRequestState() so
    // that request reads the account afresh, as a real one would.
    forgetRequestState();
    $this->post('/confirm-password', ['password' => 'a-password-of-my-own'])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an account that already has a password uses the form, not a link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/password/link')->assertForbidden();

    Notification::assertNothingSent();
});

test('a directory account is not sent a link either', function () {
    Notification::fake();

    $this->actingAs(providerAccount(['auth_source' => AuthSource::Ldap]))->post('/settings/password/link')->assertForbidden();

    Notification::assertNothingSent();
});

test('without a password, the last provider still cannot be removed', function () {
    $user = providerAccount();
    SocialAccount::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);

    $this->actingAs($user)->put('/settings/password', [
        'password' => 'chosen-by-whoever-holds-the-session',
        'password_confirmation' => 'chosen-by-whoever-holds-the-session',
    ]);

    $this->actingAs($user->refresh())->delete('/settings/connected-accounts/google')->assertSessionHasErrors('provider');

    expect(SocialAccount::query()->where('user_id', $user->id)->exists())->toBeTrue();
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

    // The link is asked for from that screen, and opened past the
    // enforcement too: both are part of ending the loop.
    Notification::fake();
    $this->actingAs($user)->post('/settings/password/link')->assertSessionHasNoErrors();
    Notification::assertSentTo($user, ResetPasswordNotification::class);

    $token = Password::createToken($user);
    $this->actingAs($user)->get("/reset-password/{$token}?email=".urlencode($user->email))->assertOk();
    $this->actingAs($user)->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-password-of-my-own',
        'password_confirmation' => 'a-password-of-my-own',
    ])->assertSessionHasNoErrors();

    // Setting it ends every session holding the old password, the one that
    // asked for the link included, so the way on is to sign in again.
    auth()->logout();
    forgetRequestState();
    $this->post('/login', ['email' => $user->email, 'password' => 'a-password-of-my-own']);
    $this->assertAuthenticatedAs($user);

    // And with a password, the confirmation in front of enrolling is
    // answerable, so two-factor can actually be turned on.
    $this->post('/confirm-password', ['password' => 'a-password-of-my-own'])->assertSessionHasNoErrors();

    $this->post('/settings/two-factor')->assertSessionHasNoErrors();

    expect($user->refresh()->two_factor_secret)->not->toBeNull();
});
