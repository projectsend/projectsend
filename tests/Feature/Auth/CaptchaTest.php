<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'staff@example.com']);

    $settings = app(Settings::class);
    $settings->set(Setting::CaptchaProvider, 'none');
    $settings->set(Setting::CaptchaKeySource, 'own');
    $settings->set(Setting::ClientsCanRegister, true);
    foreach (CaptchaForm::cases() as $form) {
        $settings->set($form->setting(), true);
    }

    config()->set('projectsend.captcha.disabled', false);
    config()->set('projectsend.captcha.managed', ['provider' => null, 'site_key' => null, 'secret_key' => null, 'score_threshold' => 0.5]);

    Captcha::forgetDisplayCache();
    CaptchaVerifier::forgetOutage();
    RateLimiter::clear('staff@example.com|127.0.0.1');
});

/** Switch a CAPTCHA on for the whole installation. */
function protectForms(): void
{
    CaptchaSettings::for(CaptchaProvider::Turnstile)->fill([
        'site_key' => 'site-key',
        'secret_key' => 'secret-key',
    ])->save();

    app(Settings::class)->set(Setting::CaptchaProvider, CaptchaProvider::Turnstile->value);
    Captcha::forgetDisplayCache();
}

function fakeVerify(bool $success, string $action = 'login'): void
{
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(
            $success
                ? ['success' => true, 'action' => $action]
                : ['success' => false, 'error-codes' => ['invalid-input-response']],
        ),
    ]);
}

// The regression that matters most: every form must keep working exactly
// as it did on an installation that has configured no CAPTCHA.
test('an installation without a captcha is unchanged', function () {
    Http::fake();

    $this->post('/login', ['email' => 'staff@example.com', 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticated();
    Http::assertNothingSent();
});

test('login needs a token once a captcha is configured', function () {
    protectForms();
    Http::fake();

    $this->post('/login', ['email' => 'staff@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('captcha_token');

    $this->assertGuest();
    Http::assertNothingSent();
});

test('a failed check never costs the visitor one of their five attempts', function () {
    protectForms();
    fakeVerify(false);

    $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'password',
        'captcha_token' => 'a-token',
    ])->assertSessionHasErrors('captcha_token');

    // Validation runs before authenticate(), so the credential limiter
    // was never touched. Somebody whose token expired mid-form has not
    // spent a login attempt on it.
    expect(RateLimiter::attempts('staff@example.com|127.0.0.1'))->toBe(0);
});

test('login succeeds with a good token', function () {
    protectForms();
    fakeVerify(true);

    $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'password',
        'captcha_token' => 'a-token',
    ])->assertRedirect();

    $this->assertAuthenticated();
});

// The scenario the widget's reset() exists for.
test('a wrong password spends the token, and the reused one is refused', function () {
    protectForms();

    Http::fakeSequence()
        ->push(['success' => true, 'action' => 'login'])
        ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);

    $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'wrong-password',
        'captcha_token' => 'a-token',
    ])->assertSessionHasErrors('email');

    $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'password',
        'captcha_token' => 'a-token',
    ])->assertSessionHasErrors('captcha_token');

    $this->assertGuest();
});

// The property the whole three-valued result exists to protect.
test('an outage at the provider does not lock an administrator out', function () {
    protectForms();
    Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);
    Log::spy();

    $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'password',
        'captcha_token' => 'a-token',
    ])->assertRedirect();

    $this->assertAuthenticated();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'Captcha verification unavailable');
});

test('a mistyped secret key does not lock an administrator out either', function () {
    protectForms();
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']]),
    ]);

    $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'password',
        'captcha_token' => 'a-token',
    ])->assertRedirect();

    $this->assertAuthenticated();
});

test('the password reset request is protected, and still delivers during an outage', function () {
    protectForms();
    Notification::fake();

    // Armed once: a bare Http::fake() here would keep matching and leave
    // the outage stub below dead, so the test would prove nothing.
    Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);

    $this->post('/forgot-password', ['email' => 'staff@example.com'])->assertSessionHasErrors('captcha_token');
    Notification::assertNothingSent();
    Http::assertNothingSent();

    $this->post('/forgot-password', ['email' => 'staff@example.com', 'captcha_token' => 'a-token'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->user, ResetPasswordNotification::class);
});

test('a token minted on the login form is refused at the reset form', function () {
    protectForms();
    fakeVerify(true, action: 'login');

    $this->post('/forgot-password', ['email' => 'staff@example.com', 'captcha_token' => 'a-token'])
        ->assertSessionHasErrors('captcha_token');
});

test('a form whose switch is off is not asked for a token', function () {
    protectForms();
    app(Settings::class)->set(Setting::CaptchaOnLogin, false);
    Http::fake();

    // While a form that is still switched on keeps asking. Checked first,
    // because /forgot-password is a guest route and signing in below would
    // bounce this request before it ever reached validation.
    $this->post('/forgot-password', ['email' => 'staff@example.com'])->assertSessionHasErrors('captcha_token');

    $this->post('/login', ['email' => 'staff@example.com', 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticated();
});

test('the site key reaches the browser and the secret does not', function () {
    protectForms();

    $response = $this->get('/login');

    $response->assertOk();
    expect($response->content())->toContain('site-key')->not->toContain('secret-key');
});
