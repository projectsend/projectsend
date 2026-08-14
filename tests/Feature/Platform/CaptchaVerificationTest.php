<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaResult;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;

const TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const RECAPTCHA_VERIFY = 'https://www.google.com/recaptcha/api/siteverify';

beforeEach(function () {
    User::factory()->create();

    // Settings survive the per-test DB rollback (Cache::rememberForever),
    // and so does the display cache — set everything this file depends on
    // rather than assuming a default.
    $settings = app(Settings::class);
    $settings->set(Setting::CaptchaProvider, 'none');
    $settings->set(Setting::CaptchaKeySource, 'own');
    foreach (CaptchaForm::cases() as $form) {
        $settings->set($form->setting(), true);
    }

    config()->set('projectsend.captcha.disabled', false);
    config()->set('projectsend.captcha.managed', ['provider' => null, 'site_key' => null, 'secret_key' => null, 'score_threshold' => 0.5]);

    Captcha::forgetDisplayCache();
    CaptchaVerifier::forgetOutage();
});

/** Configure this installation with its own keys for one provider. */
function useProvider(CaptchaProvider $provider, ?float $threshold = null): void
{
    $row = CaptchaSettings::for($provider);
    $row->site_key = 'site-key';
    $row->secret_key = 'secret-key';
    $row->score_threshold = $threshold ?? CaptchaSettings::DEFAULT_SCORE_THRESHOLD;
    $row->save();

    app(Settings::class)->set(Setting::CaptchaProvider, $provider->value);
    Captcha::forgetDisplayCache();
}

function verify(CaptchaForm $form = CaptchaForm::Login): CaptchaResult
{
    return app(CaptchaVerifier::class)->verify('a-token', $form, '203.0.113.7');
}

test('a good token passes, for every provider', function (CaptchaProvider $provider, string $url) {
    useProvider($provider);

    Http::fake([$url => Http::response([
        'success' => true,
        'action' => 'login',
        'score' => 0.9,
        'hostname' => 'localhost',
    ])]);

    expect(verify())->toBe(CaptchaResult::Passed);

    Http::assertSent(fn ($request) => $request['secret'] === 'secret-key'
        && $request['response'] === 'a-token'
        && $request['remoteip'] === '203.0.113.7');
})->with([
    'turnstile' => [CaptchaProvider::Turnstile, TURNSTILE_VERIFY],
    'recaptcha v2' => [CaptchaProvider::RecaptchaV2, RECAPTCHA_VERIFY],
    'recaptcha v3' => [CaptchaProvider::RecaptchaV3, RECAPTCHA_VERIFY],
]);

test('a refusal the provider blames on the visitor fails', function () {
    useProvider(CaptchaProvider::Turnstile);

    Http::fake([TURNSTILE_VERIFY => Http::response([
        'success' => false,
        'error-codes' => ['invalid-input-response'],
    ])]);

    expect(verify())->toBe(CaptchaResult::Failed);
});

// v1 decided reCAPTCHA v2's answer with strstr($body, '"success": true'),
// so any response whose text happened to contain that substring passed.
test('a failure is read from the decoded body, not searched for in its text', function () {
    useProvider(CaptchaProvider::RecaptchaV2);

    Http::fake([RECAPTCHA_VERIFY => Http::response([
        'success' => false,
        'error-codes' => ['invalid-input-response'],
        'message' => 'expected "success": true but the token was already used',
    ])]);

    expect(verify())->toBe(CaptchaResult::Failed);
});

// v1 minted every token with the action "submit" and never compared it, so
// a token from the login form was accepted at registration.
test('a token minted for another form is refused', function (CaptchaProvider $provider, string $url) {
    useProvider($provider);

    Http::fake([$url => Http::response([
        'success' => true,
        'action' => 'register',
        'score' => 0.9,
    ])]);

    expect(verify(CaptchaForm::Login))->toBe(CaptchaResult::Failed);
})->with([
    'turnstile' => [CaptchaProvider::Turnstile, TURNSTILE_VERIFY],
    'recaptcha v3' => [CaptchaProvider::RecaptchaV3, RECAPTCHA_VERIFY],
]);

// Cloudflare's own always-pass testing keys answer this way, so refusing
// it would break every developer's local setup for no gain: a token minted
// without an action was never bound to a form to begin with.
test('a response with no action is accepted', function (CaptchaProvider $provider, string $url) {
    useProvider($provider);

    Http::fake([$url => Http::response(['success' => true, 'score' => 0.9, 'hostname' => 'example.com'])]);

    expect(verify(CaptchaForm::Login))->toBe(CaptchaResult::Passed);
})->with([
    'turnstile' => [CaptchaProvider::Turnstile, TURNSTILE_VERIFY],
    'recaptcha v3' => [CaptchaProvider::RecaptchaV3, RECAPTCHA_VERIFY],
]);

test('reCAPTCHA v2 passes without an action, because it never sends one', function () {
    useProvider(CaptchaProvider::RecaptchaV2);

    Http::fake([RECAPTCHA_VERIFY => Http::response(['success' => true, 'hostname' => 'localhost'])]);

    expect(verify())->toBe(CaptchaResult::Passed);
});

test('a v3 score below the threshold fails', function () {
    useProvider(CaptchaProvider::RecaptchaV3, threshold: 0.7);

    Http::fake([RECAPTCHA_VERIFY => Http::response([
        'success' => true,
        'action' => 'login',
        'score' => 0.6,
    ])]);

    expect(verify())->toBe(CaptchaResult::Failed);
});

test('a threshold of zero accepts every score', function () {
    // v1's !empty() check turned a saved 0 back into 0.5.
    useProvider(CaptchaProvider::RecaptchaV3, threshold: 0.0);

    Http::fake([RECAPTCHA_VERIFY => Http::response([
        'success' => true,
        'action' => 'login',
        'score' => 0.0,
    ])]);

    expect(verify())->toBe(CaptchaResult::Passed);
});

test('a provider that cannot be reached is unavailable, not a failure', function (callable $fake) {
    useProvider(CaptchaProvider::Turnstile);

    Http::fake([TURNSTILE_VERIFY => $fake()]);

    $result = verify();

    expect($result)->toBe(CaptchaResult::Unavailable)
        ->and($result->allowsRequest())->toBeTrue();
})->with([
    'connection refused' => [fn () => fn () => Http::failedConnection()],
    'server error' => [fn () => fn () => Http::response(null, 500)],
    'unparseable body' => [fn () => fn () => Http::response('<html>maintenance</html>', 200)],
]);

// The single most important property here: the likeliest misconfiguration
// is a mistyped secret, and it must not lock anybody out.
test('our own bad credentials are unavailable, so nobody is locked out', function (string $code) {
    useProvider(CaptchaProvider::Turnstile);

    Http::fake([TURNSTILE_VERIFY => Http::response(['success' => false, 'error-codes' => [$code]])]);

    expect(verify())->toBe(CaptchaResult::Unavailable);

    expect(CaptchaVerifier::lastError())
        ->not->toBeNull()
        ->and(CaptchaVerifier::lastError()['our_credentials'])->toBeTrue();
})->with(['invalid-input-secret', 'missing-input-secret', 'bad-request', 'internal-error']);

// Cloudflare answers a malformed secret with 400 *and* the error code
// together. Reading the status alone would file it as "could not reach
// them", and the administrator would never learn it was their key.
test('a rejected secret is reported as a rejected secret, even behind a 400', function () {
    useProvider(CaptchaProvider::Turnstile);

    Http::fake([TURNSTILE_VERIFY => Http::response(
        ['success' => false, 'error-codes' => ['invalid-input-secret']],
        400,
    )]);

    expect(verify())->toBe(CaptchaResult::Unavailable);

    expect(CaptchaVerifier::lastError())
        ->not->toBeNull()
        ->and(CaptchaVerifier::lastError()['our_credentials'])->toBeTrue()
        ->and(CaptchaVerifier::lastError()['codes'])->toBe(['invalid-input-secret']);
});

test('the breaker stops a second call while the provider is down', function () {
    useProvider(CaptchaProvider::Turnstile);

    Http::fake([TURNSTILE_VERIFY => Http::failedConnection()]);

    expect(verify())->toBe(CaptchaResult::Unavailable)
        ->and(verify())->toBe(CaptchaResult::Unavailable);

    Http::assertSentCount(1);
});

test('nothing is verified when no provider is configured', function () {
    Http::fake();

    expect(verify())->toBe(CaptchaResult::Passed);

    Http::assertNothingSent();
});

test('half-configured keys behave exactly as switched off', function () {
    $row = CaptchaSettings::for(CaptchaProvider::Turnstile);
    $row->site_key = 'site-key';
    $row->secret_key = null;
    $row->save();

    app(Settings::class)->set(Setting::CaptchaProvider, CaptchaProvider::Turnstile->value);
    Captcha::forgetDisplayCache();

    expect(app(Captcha::class)->active())->toBeNull()
        ->and(app(Captcha::class)->protects(CaptchaForm::Login))->toBeFalse();
});

test('a form whose switch is off is not protected, while the others still are', function () {
    useProvider(CaptchaProvider::Turnstile);
    app(Settings::class)->set(Setting::CaptchaOnLogin, false);

    $captcha = app(Captcha::class);

    expect($captcha->protects(CaptchaForm::Login))->toBeFalse()
        ->and($captcha->protects(CaptchaForm::Register))->toBeTrue();
});

test('the env kill switch turns everything off', function () {
    useProvider(CaptchaProvider::Turnstile);

    config()->set('projectsend.captcha.disabled', true);

    expect(app(Captcha::class)->active())->toBeNull();
});

test('community ignores a stored managed key source and uses its own keys', function () {
    config()->set('projectsend.edition', Edition::Community);
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'platform-site-key',
        'secret_key' => 'platform-secret',
        'score_threshold' => 0.5,
    ]);

    useProvider(CaptchaProvider::RecaptchaV2);

    // A value that could only have arrived from a v1 import, a hand-edited
    // row, or an install that used to be cloud.
    app(Settings::class)->set(Setting::CaptchaKeySource, 'managed');
    Captcha::forgetDisplayCache();

    $active = app(Captcha::class)->active();

    expect($active)->not->toBeNull()
        ->and($active->provider)->toBe(CaptchaProvider::RecaptchaV2)
        ->and($active->siteKey)->toBe('site-key')
        ->and($active->managed)->toBeFalse();
});

test('cloud on managed keys uses the platform credentials, not the tenant row', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'platform-site-key',
        'secret_key' => 'platform-secret',
        'score_threshold' => 0.5,
    ]);

    useProvider(CaptchaProvider::RecaptchaV2);
    app(Settings::class)->set(Setting::CaptchaKeySource, 'managed');
    Captcha::forgetDisplayCache();

    $active = app(Captcha::class)->active();

    expect($active->provider)->toBe(CaptchaProvider::Turnstile)
        ->and($active->siteKey)->toBe('platform-site-key')
        ->and($active->managed)->toBeTrue();

    // And the browser is told the platform's site key and nothing secret.
    expect(app(Captcha::class)->forDisplay())
        ->toMatchArray(['provider' => 'turnstile', 'site_key' => 'platform-site-key']);
});

test('cloud on managed keys with nothing configured is simply off', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    app(Settings::class)->set(Setting::CaptchaKeySource, 'managed');
    Captcha::forgetDisplayCache();

    expect(app(Captcha::class)->active())->toBeNull()
        ->and(app(Captcha::class)->forDisplay())->toBeNull();
});

test('an incomplete own configuration does not silently fall back to the platform keys', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'platform-site-key',
        'secret_key' => 'platform-secret',
        'score_threshold' => 0.5,
    ]);

    app(Settings::class)->set(Setting::CaptchaKeySource, 'own');
    app(Settings::class)->set(Setting::CaptchaProvider, CaptchaProvider::Turnstile->value);
    Captcha::forgetDisplayCache();

    expect(app(Captcha::class)->active())->toBeNull();
});

test('the secret is encrypted at rest', function () {
    useProvider(CaptchaProvider::Turnstile);

    $stored = DB::table('captcha_providers')->where('provider', 'turnstile')->value('secret_key');

    expect($stored)->not->toBe('secret-key')
        ->and(CaptchaSettings::for(CaptchaProvider::Turnstile)->secret_key)->toBe('secret-key');
});
