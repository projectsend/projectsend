<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Localization\LocaleRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();

    // Settings are cached across tests (Cache::rememberForever survives the
    // per-test DB rollback) — reset explicitly rather than assuming nothing
    // else in the suite has touched this.
    app(Settings::class)->set(Setting::EnabledLocales, ['en']);
    app(Settings::class)->set(Setting::DefaultLocale, 'en');
});

test('staff can view the installed languages and choose which are offered', function () {
    $installed = app(LocaleRegistry::class)->installed();

    // Derived, never listed — same reasoning as LocalizationTest: a
    // dropped-in translation pack must not fail the build.
    expect($installed)->toContain('en', 'es');

    $this->actingAs($this->admin)->get('/system/settings/languages')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/languages')
            ->where('installed', $installed)
            ->where('enabled', ['en'])
            ->where('default_locale', 'en'),
    );

    $this->actingAs($this->admin)->patch('/system/settings/languages', [
        'enabled_locales' => ['en', 'es'],
        'default_locale' => 'en',
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::EnabledLocales))->toBe(['en', 'es'])
        ->and(app(LocaleRegistry::class)->enabled())->toBe(['en', 'es']);

    expect(ActivityLog::query()->where('action', Action::SettingsUpdated)->where('context->section', 'languages')->exists())
        ->toBeTrue();
});

test('english is kept even when the payload leaves it out', function () {
    $this->actingAs($this->admin)->patch('/system/settings/languages', [
        'enabled_locales' => ['es'],
        'default_locale' => 'es',
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::EnabledLocales))->toBe(['en', 'es']);
});

test('turning everything off still leaves english', function () {
    $this->actingAs($this->admin)->patch('/system/settings/languages', [
        'enabled_locales' => [],
        'default_locale' => 'en',
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::EnabledLocales))->toBe(['en']);
});

test('a language that is not installed is rejected', function () {
    // 'zz' is unassigned in ISO 639-1, so no translation pack will ever
    // claim it — the same trick LocalizationTest uses.
    expect(app(LocaleRegistry::class)->installed())->not->toContain('zz');

    $this->actingAs($this->admin)->patch('/system/settings/languages', [
        'enabled_locales' => ['en', 'zz'],
        'default_locale' => 'en',
    ])->assertSessionHasErrors('enabled_locales.1');

    expect(app(Settings::class)->get(Setting::EnabledLocales))->toBe(['en']);
});

test('the chosen default language is what a visitor with no preference gets', function () {
    $this->actingAs($this->admin)->patch('/system/settings/languages', [
        'enabled_locales' => ['en', 'es'],
        'default_locale' => 'es',
    ])->assertRedirect();

    expect(app(LocaleRegistry::class)->defaultLocale())->toBe('es');

    // A browser asking for a language this install does not offer, with
    // nothing in the session and no preference on the account: the
    // operator's choice is the only thing left to go on. The header is
    // explicit because Symfony's test requests carry `en-us` by default,
    // which would answer the question before it is asked. ('xx' is
    // unassigned in ISO 639-1, so no translation pack will ever match it.)
    // Still signed in — actingAs() persists across requests, and the guest
    // screens bounce an authenticated visitor.
    $this->get('/dashboard', ['Accept-Language' => 'xx'])->assertInertia(
        fn (AssertableInertia $page) => $page->where('locale', 'es'),
    );
});

test('a default that is not among the enabled languages is rejected', function () {
    $this->actingAs($this->admin)->patch('/system/settings/languages', [
        'enabled_locales' => ['en', 'es'],
        'default_locale' => 'ja',
    ])->assertSessionHasErrors('default_locale');

    expect(app(Settings::class)->get(Setting::EnabledLocales))->toBe(['en'])
        ->and(app(Settings::class)->get(Setting::DefaultLocale))->toBe('en');
});

test('a default that is later switched off degrades to english', function () {
    $settings = app(Settings::class);
    $settings->set(Setting::EnabledLocales, ['en', 'es']);
    $settings->set(Setting::DefaultLocale, 'es');

    expect(app(LocaleRegistry::class)->defaultLocale())->toBe('es');

    // Hand-edited rather than reachable through the screen — the screen
    // refuses this combination — but the resolver must not strand visitors
    // in a language the switcher no longer offers.
    $settings->set(Setting::EnabledLocales, ['en']);

    expect(app(LocaleRegistry::class)->defaultLocale())->toBe('en');
});

test('clients cannot access language settings', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/languages')
        ->assertRedirect(route('dashboard'));
});

test('staff without edit_settings cannot access language settings', function () {
    $staffer = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($staffer)->get('/system/settings/languages')->assertForbidden();
    $this->actingAs($staffer)->patch('/system/settings/languages', [
        'enabled_locales' => ['en'],
        'default_locale' => 'en',
    ])->assertForbidden();
});
