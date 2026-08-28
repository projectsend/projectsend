<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Modules\Clients\Http\Controllers\RegistrationController;
use App\Modules\Identity\Http\Controllers\SocialLoginController;
use App\Modules\Identity\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

// NOTE: there is deliberately no staff registration route. /register is
// CLIENT self-registration (v1's register.php), gated by the
// clients_can_register setting inside the controller.
//
// **Every `throttle:` below names its own bucket, and must.** The bare
// two-argument form does not key on the route at all — Laravel keys it on
// `sha1(domain|ip)` for a guest and `sha1(user_id)` for a signed-in user
// (ThrottleRequests::resolveRequestSignature) — so all of these counted
// into one number together with the public share links in web.php, and the
// tightest limit on that number applied to all of them. Opening six share
// links locked the visitor out of the two-factor challenge. The numbers
// here are unchanged; the third argument is what makes each of them mean
// what it says.
//
// POST login is deliberately absent from this: it is rate-limited per
// email *and* IP inside LoginRequest, which is a stronger boundary than a
// per-IP count and does not lock out a whole office behind one address.
Route::middleware('guest')->group(function () {
    Route::get('register', [RegistrationController::class, 'create'])
        ->name('register');

    Route::post('register', [RegistrationController::class, 'store'])
        ->middleware('throttle:6,1,register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Beginning a provider exchange is a guest action; completing one is
    // not necessarily — see the callback below, which sits outside every
    // group.
    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->middleware('throttle:20,1,social-redirect')
        ->name('social.redirect');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // The broker's own throttle is per-address (config/auth.php), which
    // does nothing to stop one host walking a list of addresses — so the
    // endpoint is throttled per IP as well, same as register/2FA below.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1,password-email')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1,password-reset')
        ->name('password.store');

    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:6,1,two-factor');
});

// Deliberately in neither group. Signing in through a provider must not
// require a session, and connecting one to an existing account requires
// exactly that — so the guard is the intent written into the session
// before the redirect, which also refuses a callback nobody asked for.
Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
    ->middleware('throttle:20,1,social-callback')
    ->name('social.callback');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,verify-email'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,verification-send')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    // Named so EnforceTwoFactor can exempt it. Its exemption list matches
    // on route names, and an unnamed route matches nothing -- which left
    // the form reachable and its submission not, closing the enrolment
    // path enforcement depends on.
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->name('password.confirm.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
