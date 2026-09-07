<?php

declare(strict_types=1);

use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

beforeEach(function () {
    // Settings outlive the per-test rollback, so nothing here may assume a
    // default — see the same note in CaptchaSettingsTest.
    $settings = app(Settings::class);
    $settings->set(Setting::CaptchaProvider, 'turnstile');
    $settings->set(Setting::CaptchaKeySource, 'own');

    CaptchaSettings::for(CaptchaProvider::Turnstile)
        ->fill(['site_key' => 'site-abc', 'secret_key' => 'secret-abc'])
        ->save();

    config()->set('projectsend.edition', Edition::Community);
    config()->set('projectsend.captcha.disabled', false);
    config()->set('projectsend.captcha.managed', ['provider' => null, 'site_key' => null, 'secret_key' => null, 'score_threshold' => 0.5]);

    Captcha::forgetDisplayCache();
});

test('it switches the captcha off and keeps the keys', function () {
    expect(app(Captcha::class)->active())->not->toBeNull();

    $this->artisan('projectsend:captcha-off')
        ->expectsOutputToContain('CAPTCHA is off')
        ->assertSuccessful();

    expect(app(Captcha::class)->active())->toBeNull()
        // The whole point of the command: a way back in, not a way to lose
        // a credential somebody wants again in ten minutes.
        ->and(CaptchaSettings::for(CaptchaProvider::Turnstile)->secret_key)->toBe('secret-abc');
});

// The setting this command writes is not where managed keys come from.
// Captcha::resolve() returns managedConfig() before it ever reads
// Setting::CaptchaProvider, so on a managed installation the write lands
// somewhere nothing reads — and the old success message sent an operator
// who was still being challenged away from the only thing that would have
// explained why.
test('it says plainly that it changed nothing when the platform supplies the keys', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'managed-site',
        'secret_key' => 'managed-secret',
        'score_threshold' => 0.5,
    ]);
    app(Settings::class)->set(Setting::CaptchaKeySource, 'managed');
    Captcha::forgetDisplayCache();

    $this->artisan('projectsend:captcha-off')
        ->expectsOutputToContain('Nothing changed')
        ->expectsOutputToContain('PROJECTSEND_CAPTCHA_DISABLED')
        ->doesntExpectOutputToContain('CAPTCHA is off')
        ->assertSuccessful();

    // And it really did change nothing: the forms are still protected.
    expect(app(Captcha::class)->active())->not->toBeNull();
});

// The env switch is checked ahead of the key source, which is what makes
// it the one that works on a managed installation. If that ordering ever
// moves, a locked-out operator loses their last way in.
test('the environment switch turns off even the platform keys', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    config()->set('projectsend.captcha.managed', [
        'provider' => 'turnstile',
        'site_key' => 'managed-site',
        'secret_key' => 'managed-secret',
        'score_threshold' => 0.5,
    ]);
    app(Settings::class)->set(Setting::CaptchaKeySource, 'managed');
    config()->set('projectsend.captcha.disabled', true);
    Captcha::forgetDisplayCache();

    expect(app(Captcha::class)->active())->toBeNull();
});
