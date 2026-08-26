<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('staff can view and update download settings', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback), so the starting value is set rather than
    // assumed.
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 2048);

    $this->actingAs($this->admin)->get('/system/settings/downloads')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/downloads')
            ->where('max_zip_download_size_mb', 2048),
    );

    $this->actingAs($this->admin)
        ->patch('/system/settings/downloads', ['max_zip_download_size_mb' => 512])
        ->assertRedirect();

    expect(app(Settings::class)->get(Setting::MaxZipDownloadSizeMb))->toBe(512);

    expect(ActivityLog::query()->where('action', Action::SettingsUpdated)->where('context->section', 'downloads')->exists())
        ->toBeTrue();
});

test('a negative zip download limit is rejected', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/downloads', ['max_zip_download_size_mb' => -1])
        ->assertSessionHasErrors('max_zip_download_size_mb');
});

test('clients cannot access download settings', function () {
    $this->admin; // setup complete

    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/downloads')
        ->assertRedirect(route('dashboard'));
});

test('staff without edit_settings cannot access download settings', function () {
    $role = Role::query()->create(['name' => 'No Settings', 'is_administrator' => false, 'is_system' => false]);
    $staffer = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staffer)->get('/system/settings/downloads')->assertForbidden();
});
