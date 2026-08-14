<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();

    // Settings and the display payload both outlive the per-test DB
    // rollback, so nothing here may assume a default.
    $settings = app(Settings::class);
    $settings->set(Setting::CaptchaProvider, 'none');
    $settings->set(Setting::CaptchaKeySource, 'own');
    foreach (CaptchaForm::cases() as $form) {
        $settings->set($form->setting(), true);
    }

    config()->set('projectsend.edition', Edition::Community);
    config()->set('projectsend.captcha.disabled', false);
    config()->set('projectsend.captcha.managed', ['provider' => null, 'site_key' => null, 'secret_key' => null, 'score_threshold' => 0.5]);

    Captcha::forgetDisplayCache();
    CaptchaVerifier::forgetOutage();
});

/** The payload the form posts, with only the interesting bits overridden. */
function captchaPayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'none',
        'on_login' => true,
        'on_registration' => true,
        'on_password_reset' => true,
        'on_public_comments' => true,
    ], $overrides);
}

test('the screen needs the settings permission', function () {
    $this->actingAs(staffWithPermissions([]))->get('/system/settings/captcha')->assertForbidden();

    $this->actingAs(staffWithPermissions(['edit_settings']))->get('/system/settings/captcha')->assertOk();
});

test('saving keys switches the captcha on', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/captcha', captchaPayload([
            'provider' => 'turnstile',
            'site_key' => 'site-abc',
            'secret_key' => 'secret-abc',
        ]))
        ->assertRedirect();

    $active = app(Captcha::class)->active();

    expect($active)->not->toBeNull()
        ->and($active->provider)->toBe(CaptchaProvider::Turnstile)
        ->and($active->siteKey)->toBe('site-abc')
        ->and($active->secretKey)->toBe('secret-abc');
});

test('the secret is never sent to the browser, only the fact that there is one', function () {
    CaptchaSettings::for(CaptchaProvider::Turnstile)->fill([
        'site_key' => 'site-abc',
        'secret_key' => 'secret-abc',
    ])->save();

    app(Settings::class)->set(Setting::CaptchaProvider, 'turnstile');
    Captcha::forgetDisplayCache();

    $response = $this->actingAs($this->admin)->get('/system/settings/captcha');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('system/settings/captcha')
        ->where('providers.0.provider', 'turnstile')
        ->where('providers.0.site_key', 'site-abc')
        ->where('providers.0.has_secret_key', true)
    );

    expect($response->content())->not->toContain('secret-abc');
});

test('the secret is encrypted in the database', function () {
    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'turnstile',
        'site_key' => 'site-abc',
        'secret_key' => 'secret-abc',
    ]));

    expect(DB::table('captcha_providers')->where('provider', 'turnstile')->value('secret_key'))
        ->not->toBe('secret-abc');
});

test('a blank secret keeps the stored one, so editing anything else does not wipe it', function () {
    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'turnstile',
        'site_key' => 'site-abc',
        'secret_key' => 'secret-abc',
    ]));

    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'turnstile',
        'site_key' => 'site-changed',
        'secret_key' => '',
        'on_login' => false,
    ]));

    $stored = CaptchaSettings::for(CaptchaProvider::Turnstile);

    expect($stored->site_key)->toBe('site-changed')
        ->and($stored->secret_key)->toBe('secret-abc');
});

test('a provider cannot be switched on half-configured', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/captcha', captchaPayload(['provider' => 'turnstile', 'site_key' => '', 'secret_key' => '']))
        ->assertSessionHasErrors(['site_key', 'secret_key']);
});

test('a v3 threshold outside 0 to 1 is refused', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/captcha', captchaPayload([
            'provider' => 'recaptcha_v3',
            'site_key' => 'site-abc',
            'secret_key' => 'secret-abc',
            'score_threshold' => 1.5,
        ]))
        ->assertSessionHasErrors('score_threshold');
});

test('keys are kept per provider, so comparing two of them costs nothing', function () {
    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'turnstile', 'site_key' => 'turnstile-site', 'secret_key' => 'turnstile-secret',
    ]));

    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'recaptcha_v2', 'site_key' => 'google-site', 'secret_key' => 'google-secret',
    ]));

    expect(CaptchaSettings::for(CaptchaProvider::Turnstile)->site_key)->toBe('turnstile-site')
        ->and(CaptchaSettings::for(CaptchaProvider::RecaptchaV2)->site_key)->toBe('google-site');
});

test('the per-form switches are saved', function () {
    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'turnstile',
        'site_key' => 'site-abc',
        'secret_key' => 'secret-abc',
        'on_login' => false,
        'on_public_comments' => false,
    ]));

    $captcha = app(Captcha::class);

    expect($captcha->protects(CaptchaForm::Login))->toBeFalse()
        ->and($captcha->protects(CaptchaForm::Comment))->toBeFalse()
        ->and($captcha->protects(CaptchaForm::Register))->toBeTrue();
});

test('community is never offered the platform keys, and cannot be made to use them', function () {
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'platform-site',
        'secret_key' => 'platform-secret',
        'score_threshold' => 0.5,
    ]);

    $this->actingAs($this->admin)->get('/system/settings/captcha')->assertInertia(
        fn (AssertableInertia $page) => $page->where('managed_keys_available', false)
    );

    // A hand-crafted PATCH: the field is not in the rules and is never
    // read, so it cannot smuggle the platform's credentials into a
    // self-hosted install.
    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'key_source' => 'managed',
        'provider' => 'recaptcha_v2',
        'site_key' => 'own-site',
        'secret_key' => 'own-secret',
    ]));

    expect(app(Settings::class)->get(Setting::CaptchaKeySource))->toBe('own')
        ->and(app(Captcha::class)->active()->siteKey)->toBe('own-site');
});

test('cloud can choose the platform keys, and choosing them leaves its own alone', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'platform-site',
        'secret_key' => 'platform-secret',
        'score_threshold' => 0.5,
    ]);

    CaptchaSettings::for(CaptchaProvider::RecaptchaV2)->fill([
        'site_key' => 'tenant-site',
        'secret_key' => 'tenant-secret',
    ])->save();

    $this->actingAs($this->admin)->patch('/system/settings/captcha', [
        'key_source' => 'managed',
        'on_login' => true,
        'on_registration' => true,
        'on_password_reset' => true,
        'on_public_comments' => true,
    ])->assertRedirect();

    expect(app(Captcha::class)->active()->siteKey)->toBe('platform-site')
        // Still there, so flipping back needs no re-keying.
        ->and(CaptchaSettings::for(CaptchaProvider::RecaptchaV2)->site_key)->toBe('tenant-site');
});

test('the test button proves a secret without anybody solving a challenge', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
    ]);

    $this->actingAs($this->admin)
        ->post('/system/settings/captcha/test', ['provider' => 'turnstile', 'secret_key' => 'secret-abc'])
        ->assertRedirect()
        ->assertSessionHas('captcha_test_result', fn (array $result) => $result['ok'] === true);
});

// Cloudflare's always-pass testing secret verifies anything, including
// the probe. Without a branch for it, the clearest possible "your key
// works" reads as an unexpected reply.
test('the test button accepts a secret that verifies the probe outright', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->actingAs($this->admin)
        ->post('/system/settings/captcha/test', ['provider' => 'turnstile', 'secret_key' => 'testing-key'])
        ->assertSessionHas('captcha_test_result', fn (array $result) => $result['ok'] === true);
});

test('the test button reports a rejected secret as rejected', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']]),
    ]);

    $this->actingAs($this->admin)
        ->post('/system/settings/captcha/test', ['provider' => 'turnstile', 'secret_key' => 'wrong'])
        ->assertSessionHas('captcha_test_result', fn (array $result) => $result['ok'] === false);
});

test('saving clears a stale outage warning', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);

    CaptchaSettings::for(CaptchaProvider::Turnstile)->fill(['site_key' => 's', 'secret_key' => 'k'])->save();
    app(Settings::class)->set(Setting::CaptchaProvider, 'turnstile');
    Captcha::forgetDisplayCache();

    app(CaptchaVerifier::class)->verify('token', CaptchaForm::Login);
    expect(CaptchaVerifier::lastError())->not->toBeNull();

    $this->actingAs($this->admin)->patch('/system/settings/captcha', captchaPayload([
        'provider' => 'turnstile', 'site_key' => 's', 'secret_key' => 'corrected',
    ]));

    expect(CaptchaVerifier::lastError())->toBeNull();
});
