<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Localization\LocaleRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // The app redirects everything to /setup until a staff user exists.
    User::factory()->create();

    // Only English is enabled out of the box, and the settings cache
    // survives the per-test DB rollback — so anything switching to Spanish
    // has to enable it explicitly rather than inherit it from whatever ran
    // before. Set here rather than per-test: most of this file is about the
    // resolution chain, not about the enabled list.
    app(Settings::class)->set(Setting::EnabledLocales, ['en', 'es']);
    app(Settings::class)->set(Setting::DefaultLocale, 'en');
});

/** A registry over a lang directory this test controls, bypassing the singleton. */
function registryFor(string $dir): LocaleRegistry
{
    app()->useLangPath($dir);

    return new LocaleRegistry(app(Settings::class));
}

test('installed locales are discovered from lang catalogs, english always included', function () {
    $dir = sys_get_temp_dir().'/projectsend-lang-'.uniqid();
    File::makeDirectory($dir);
    File::put($dir.'/es.json', '{}');
    File::put($dir.'/pt_BR.json', '{}');

    try {
        expect(registryFor($dir)->installed())->toBe(['en', 'es', 'pt_BR']);
    } finally {
        File::deleteDirectory($dir);
    }
});

test('a lang directory with no catalogs still offers english', function () {
    $dir = sys_get_temp_dir().'/projectsend-lang-'.uniqid();
    File::makeDirectory($dir);

    try {
        expect(registryFor($dir)->installed())->toBe(['en']);
    } finally {
        File::deleteDirectory($dir);
    }
});

test('only enabled locales are offered, and english survives being switched off', function () {
    $dir = sys_get_temp_dir().'/projectsend-lang-'.uniqid();
    File::makeDirectory($dir);
    File::put($dir.'/es.json', '{}');
    File::put($dir.'/pt_BR.json', '{}');

    try {
        expect(registryFor($dir)->enabled())->toBe(['en', 'es']);

        // Not a state the settings screen can produce — both halves force
        // English in — but a hand-edited row must not leave the install
        // with no language at all.
        app(Settings::class)->set(Setting::EnabledLocales, ['pt_BR']);

        expect(registryFor($dir)->enabled())->toBe(['en', 'pt_BR']);
    } finally {
        File::deleteDirectory($dir);
    }
});

test('the default locale is english and ships no message catalog', function () {
    // Derived, never listed: locales are discovered from lang/*.json (see
    // LocaleRegistry), so installing a translation pack is supposed to be a
    // matter of dropping in a file. A literal list here would turn that into
    // a failing build, and the test would be asserting which languages happen
    // to be installed rather than the thing it exists to check — that the
    // enabled set is what reaches the frontend.
    $offered = app(LocaleRegistry::class)->enabled();

    expect($offered)->toContain('en');

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('locale', 'en')
            ->where('locales', $offered)
            // English is the key, so it ships no catalogue of its own.
            ->where('translations', []),
    );
});

test('a disabled locale is rejected and never reaches the switcher', function () {
    app(Settings::class)->set(Setting::EnabledLocales, ['en']);

    // Precondition: this only tests anything while Spanish is installed.
    expect(app(LocaleRegistry::class)->installed())->toContain('es');

    $this->from('/login')->put('/locale', ['locale' => 'es'])->assertSessionHasErrors('locale');

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page->where('locales', ['en']),
    );
});

test('a stored preference for a locale that has been switched off falls back to english', function () {
    $user = User::query()->sole();
    $user->update(['locale' => 'es']);

    app(Settings::class)->set(Setting::EnabledLocales, ['en']);

    // Not /login: a signed-in visitor is bounced off the guest screens.
    $this->actingAs($user)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('locale', 'en'),
    );
});

test('the disabled-locale count is only shared with staff who can manage them', function () {
    app(Settings::class)->set(Setting::EnabledLocales, ['en']);

    $expected = count(app(LocaleRegistry::class)->installed()) - 1;

    // Precondition: with only English on, every other catalogue is off.
    expect($expected)->toBeGreaterThan(0);

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page->where('locales_disabled', 0),
    );

    $this->actingAs(User::query()->sole())->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('locales_disabled', $expected),
    );

    $this->actingAs(User::factory()->client()->create())->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('locales_disabled', 0),
    );
});

test('switching locale persists in the session and shares the catalog', function () {
    $this->from('/login')->put('/locale', ['locale' => 'es'])->assertRedirect('/login');

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('locale', 'es')
            ->where('translations.Log in', 'Iniciar sesión'),
    );
});

test('switching locale while authenticated persists it to the user row', function () {
    $user = User::query()->sole();

    $this->actingAs($user)->from('/login')->put('/locale', ['locale' => 'es'])->assertRedirect('/login');

    expect($user->refresh()->locale)->toBe('es')
        ->and($user->preferredLocale())->toBe('es');
});

test('an unsupported locale is rejected', function () {
    // Same reasoning as above, in reverse: this needs a code that is not
    // installed, and any real language is one dropped-in file away from
    // becoming installed — 'de' used to serve here and silently stopped
    // testing anything the day a German catalogue appeared. 'zz' is
    // unassigned in ISO 639-1, so no pack will ever claim it; the
    // precondition is asserted rather than assumed, so if one somehow does,
    // this says so instead of failing obscurely.
    $unsupported = 'zz';

    expect(app(LocaleRegistry::class)->installed())->not->toContain($unsupported);

    $this->from('/login')->put('/locale', ['locale' => $unsupported])
        ->assertSessionHasErrors('locale');

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page->where('locale', 'en'),
    );
});

test('the Accept-Language header picks the locale when no preference is stored', function () {
    $this->get('/login', ['Accept-Language' => 'es-AR,es;q=0.9'])->assertInertia(
        fn (AssertableInertia $page) => $page->where('locale', 'es'),
    );
});

test('an Accept-Language header matching nothing falls back to the app default on a pristine install', function () {
    // Regression for the pre-preferenceOrder() bug: getPreferredLanguage()
    // returns the first candidate — never null — when the header matches
    // nothing, so a sorted candidate list came up Catalan ('ca' sorts before
    // 'en') for every curl, API client and link previewer. With no operator
    // choice stored, the environment default must win. 'zz' is unassigned in
    // ISO 639-1, so no translation pack will ever claim it; the header is
    // explicit because Symfony's test requests carry `en-us` by default.
    $this->get('/login', ['Accept-Language' => 'zz-ZZ,zz;q=0.9'])->assertInertia(
        fn (AssertableInertia $page) => $page->where('locale', config('app.locale')),
    );
});

test('server-rendered framework strings follow the locale', function () {
    $this->put('/locale', ['locale' => 'es']);

    app()->setLocale('es');

    expect(__('auth.failed'))->toBe('Estas credenciales no coinciden con nuestros registros.');
});
