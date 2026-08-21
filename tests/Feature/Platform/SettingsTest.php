<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\SettingType;
use App\Modules\Platform\Settings\StoredSetting;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('every setting declares a type and a default matching that type', function () {
    foreach (Setting::cases() as $setting) {
        $default = $setting->default();

        if ($default === null) {
            continue;
        }

        match ($setting->type()) {
            SettingType::String => expect($default)->toBeString(),
            SettingType::Boolean => expect($default)->toBeBool(),
            SettingType::Integer => expect($default)->toBeInt(),
            SettingType::Json => expect($default)->toBeArray(),
        };
    }
});

test('an unset setting returns its code default', function () {
    expect(app(Settings::class)->get(Setting::SiteName))->toBe('ProjectSend')
        ->and(app(Settings::class)->get(Setting::ClientsCanRegister))->toBeFalse();
});

test('set persists an override and get returns it typed', function () {
    $settings = app(Settings::class);

    $settings->set(Setting::SiteName, 'Estudio Jurídico');
    $settings->set(Setting::ClientsCanRegister, true);

    expect($settings->get(Setting::SiteName))->toBe('Estudio Jurídico')
        ->and($settings->get(Setting::ClientsCanRegister))->toBeTrue()
        ->and(StoredSetting::query()->count())->toBe(2);
});

test('reads are served from cache, not per-request queries', function () {
    $settings = app(Settings::class);
    $settings->set(Setting::SiteName, 'Cached Name');

    // Prime, then count queries across many reads.
    $settings->get(Setting::SiteName);
    DB::enableQueryLog();
    $settings->get(Setting::SiteName);
    $settings->get(Setting::ClientsCanRegister);
    $settings->get(Setting::ClientsAutoApprove);
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toBeEmpty();
});

test('a fresh service instance sees values written by another instance', function () {
    app(Settings::class)->set(Setting::SiteName, 'First Write');

    expect((new Settings)->get(Setting::SiteName))->toBe('First Write');
});

test('setting a wrongly-typed value is rejected', function () {
    app(Settings::class)->set(Setting::SiteName, ['not' => 'a string']);
})->throws(InvalidArgumentException::class);

test('setup stores the site name and it appears as the shared app name', function () {
    $this->post('/setup', [
        'site_name' => 'Estudio Jurídico',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page->where('name', 'Estudio Jurídico'),
    );
});

test('staff can update the site name from system settings', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/general')
            ->where('site_name', 'ProjectSend'),
    );

    $this->patch('/system/settings/general', ['site_name' => 'Renamed'])->assertRedirect();

    expect(app(Settings::class)->get(Setting::SiteName))->toBe('Renamed');
});

test('a community-edition administrator can toggle check_for_updates from system settings', function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/general')
            ->where('can_manage_updates', true)
            ->where('check_for_updates', true),
    );

    $this->patch('/system/settings/general', ['site_name' => 'ProjectSend', 'check_for_updates' => false])->assertRedirect();

    expect(app(Settings::class)->get(Setting::CheckForUpdates))->toBeFalse();
});

test('cloud edition cannot see or smuggle a check_for_updates change', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('can_manage_updates', false)
            ->where('check_for_updates', null),
    );

    $this->patch('/system/settings/general', ['site_name' => 'ProjectSend', 'check_for_updates' => false])->assertRedirect();

    expect(app(Settings::class)->get(Setting::CheckForUpdates))->toBeTrue();
});

test('a staff member without manage_updates cannot smuggle a check_for_updates change', function () {
    config()->set('projectsend.edition', Edition::Community);
    $role = Role::query()->create(['name' => 'No Update Permission', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'edit_settings']);
    $this->actingAs(User::factory()->create(['role_id' => $role->id]));

    $this->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_manage_updates', false),
    );

    $this->patch('/system/settings/general', ['site_name' => 'ProjectSend', 'check_for_updates' => false])->assertRedirect();

    expect(app(Settings::class)->get(Setting::CheckForUpdates))->toBeTrue();
});

test('staff can enable the public listing and configure its base URL segment', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback) — reset explicitly rather than assuming
    // nothing else in the suite has touched these.
    app(Settings::class)->set(Setting::PublicListingEnabled, false);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/public-listing')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/public-listing')
            ->where('public_listing_enabled', false)
            ->where('public_listing_slug', 'public'),
    );

    $this->patch('/system/settings/public-listing', [
        'public_listing_enabled' => true,
        'public_listing_slug' => 'shared',
        'public_listing_preview_enabled' => true,
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::PublicListingEnabled))->toBeTrue()
        ->and(app(Settings::class)->get(Setting::PublicListingSlug))->toBe('shared');
});

test('clients cannot access public listing settings', function () {
    User::factory()->create(); // setup complete

    $this->actingAs(User::factory()->client()->create());

    $this->get('/system/settings/public-listing')->assertRedirect(route('dashboard'));
    $this->patch('/system/settings/public-listing', ['public_listing_enabled' => true, 'public_listing_slug' => 'x'])
        ->assertForbidden();
});

test('staff can view the theming settings page and its available theme options', function () {
    app(Settings::class)->set(Setting::Theme, 'default');
    app(Settings::class)->set(Setting::EmailTheme, 'default');

    $this->actingAs(User::factory()->create());

    // preview_url/preview_url_dark mirror whether the screenshot tooling
    // has actually captured a screenshot for that key (and appearance) on this
    // checkout — never asserted as unconditionally null, since committing a
    // real screenshot for a theme is expected to flip this to a URL.
    $previewFor = fn (string $key, bool $dark = false): ?string => is_file(public_path('images/theme-previews/'.$key.($dark ? '-dark' : '').'.png')) ? asset('images/theme-previews/'.$key.($dark ? '-dark' : '').'.png') : null;
    $previewForEmail = fn (string $key, bool $dark = false): ?string => is_file(public_path('images/theme-previews/email/'.$key.($dark ? '-dark' : '').'.png')) ? asset('images/theme-previews/email/'.$key.($dark ? '-dark' : '').'.png') : null;

    $this->get('/system/settings/theming')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/theming')
            ->where('theme', 'default')
            ->where('email_theme', 'default')
            ->where('themes', [
                ['key' => 'default', 'label' => 'Default', 'description' => __('A clean, neutral layout that works well for any kind of file sharing.'), 'preview_url' => $previewFor('default'), 'preview_url_dark' => $previewFor('default', dark: true)],
                ['key' => 'compact', 'label' => 'Compact', 'description' => __('A dense, spreadsheet-style list that fits more files on screen — best for large collections and frequent uploaders.'), 'preview_url' => $previewFor('compact'), 'preview_url_dark' => $previewFor('compact', dark: true)],
                ['key' => 'drive', 'label' => 'Drive', 'description' => __('A spacious, colorful layout inspired by cloud storage apps, with clear file-type icons and generous spacing.'), 'preview_url' => $previewFor('drive'), 'preview_url_dark' => $previewFor('drive', dark: true)],
                ['key' => 'gallery', 'label' => 'Gallery', 'description' => __('A full-width photo grid built for visual browsing — the best choice for photographers and image-heavy collections.'), 'preview_url' => $previewFor('gallery'), 'preview_url_dark' => $previewFor('gallery', dark: true)],
            ])
            ->where('email_themes', [
                ['key' => 'default', 'label' => 'Default', 'description' => __("ProjectSend's classic email look — simple and neutral, and pairs well with any public/portal theme."), 'preview_url' => $previewForEmail('default'), 'preview_url_dark' => $previewForEmail('default', dark: true)],
                ['key' => 'minimal', 'label' => 'Minimal', 'description' => __('A stripped-down, understated design with no extra styling — pairs with the Compact look.'), 'preview_url' => $previewForEmail('minimal'), 'preview_url_dark' => $previewForEmail('minimal', dark: true)],
                ['key' => 'drive', 'label' => 'Drive', 'description' => __('Blue accents and clean structure inspired by cloud storage apps — pairs with the Drive look.'), 'preview_url' => $previewForEmail('drive'), 'preview_url_dark' => $previewForEmail('drive', dark: true)],
                ['key' => 'branded', 'label' => 'Branded', 'description' => __('A bold header built around your logo — pairs with the Gallery look for a polished, on-brand inbox.'), 'preview_url' => $previewForEmail('branded'), 'preview_url_dark' => $previewForEmail('branded', dark: true)],
            ]),
    );
});

test('staff can update the public/portal theme and the email theme independently', function () {
    app(Settings::class)->set(Setting::Theme, 'default');
    app(Settings::class)->set(Setting::EmailTheme, 'default');

    $this->actingAs(User::factory()->create());

    $this->patch('/system/settings/theming', [
        'theme' => 'compact',
        'email_theme' => 'minimal',
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::Theme))->toBe('compact')
        ->and(app(Settings::class)->get(Setting::EmailTheme))->toBe('minimal');
});

test('saving an unavailable theme key falls back to default rather than storing garbage', function () {
    app(Settings::class)->set(Setting::Theme, 'default');
    app(Settings::class)->set(Setting::EmailTheme, 'default');

    $this->actingAs(User::factory()->create());

    $this->patch('/system/settings/theming', [
        'theme' => 'does-not-exist',
        'email_theme' => 'also-fake',
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::Theme))->toBe('default')
        ->and(app(Settings::class)->get(Setting::EmailTheme))->toBe('default');
});

test('clients cannot access theming settings', function () {
    User::factory()->create(); // setup complete

    $this->actingAs(User::factory()->client()->create());

    $this->get('/system/settings/theming')->assertRedirect(route('dashboard'));
    $this->patch('/system/settings/theming', ['theme' => 'default', 'email_theme' => 'default'])
        ->assertForbidden();
});

test('staff can preview an available email theme without changing the live setting', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'default');
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/theming/email-preview/minimal')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('header-label', false);

    expect(app(Settings::class)->get(Setting::EmailTheme))->toBe('default');
});

test('previewing an unknown or unavailable email theme 404s', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/theming/email-preview/does-not-exist')->assertNotFound();
});

test('clients cannot preview email themes', function () {
    User::factory()->create(); // setup complete

    $this->actingAs(User::factory()->client()->create());

    $this->get('/system/settings/theming/email-preview/default')->assertRedirect(route('dashboard'));
});

test('the settings index redirects to the general section', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings')->assertRedirect('/system/settings/general');
});

test('clients cannot access system settings', function () {
    User::factory()->create(); // setup complete

    $this->actingAs(User::factory()->client()->create());

    $this->get('/system/settings/general')->assertRedirect(route('dashboard'));
    $this->patch('/system/settings/general', ['site_name' => 'Nope'])->assertForbidden();
});

test('release identity is shared with the frontend', function () {
    User::factory()->create();

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('version', config('projectsend.version'))
            // Release identity is version and edition only.
            ->missing('codename')
            ->where('links.website', 'https://www.projectsend.org/'),
    );
});
