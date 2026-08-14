<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('staff can view and update file retention settings', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback) — reset to defaults explicitly rather
    // than assuming nothing else in the suite has touched them.
    $settings = app(Settings::class);
    $settings->set(Setting::ExpiredFilesAutoDeleteEnabled, true);
    $settings->set(Setting::ExpiredFilesDeleteAfterDays, 30);
    $settings->set(Setting::OrphanFilesAutoDeleteEnabled, true);
    $settings->set(Setting::OrphanFilesDeleteAfterDays, 30);

    $this->actingAs($this->admin)->get('/system/settings/file-retention')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/file-retention')
            ->where('expired_files_auto_delete_enabled', true)
            ->where('expired_files_delete_after_days', 30)
            ->where('orphan_files_auto_delete_enabled', true)
            ->where('orphan_files_delete_after_days', 30)
            ->where('external_storage_active', false),
    );

    $this->actingAs($this->admin)->patch('/system/settings/file-retention', [
        'expired_files_auto_delete_enabled' => false,
        'expired_files_delete_after_days' => 7,
        'orphan_files_auto_delete_enabled' => false,
        'orphan_files_delete_after_days' => 14,
    ])->assertRedirect();

    $settings = app(Settings::class);
    expect($settings->get(Setting::ExpiredFilesAutoDeleteEnabled))->toBeFalse()
        ->and($settings->get(Setting::ExpiredFilesDeleteAfterDays))->toBe(7)
        ->and($settings->get(Setting::OrphanFilesAutoDeleteEnabled))->toBeFalse()
        ->and($settings->get(Setting::OrphanFilesDeleteAfterDays))->toBe(14);

    expect(ActivityLog::query()->where('action', Action::SettingsUpdated)->where('context->section', 'file_retention')->exists())
        ->toBeTrue();
});

test('a negative grace period is rejected', function () {
    $this->actingAs($this->admin)->patch('/system/settings/file-retention', [
        'expired_files_auto_delete_enabled' => true,
        'expired_files_delete_after_days' => -1,
        'orphan_files_auto_delete_enabled' => true,
        'orphan_files_delete_after_days' => 1,
    ])->assertSessionHasErrors('expired_files_delete_after_days');
});

test('clients cannot access file retention settings', function () {
    $this->admin; // setup complete

    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/file-retention')
        ->assertRedirect(route('dashboard'));
});

test('staff without edit_settings cannot access file retention settings', function () {
    $role = Role::query()->create(['name' => 'No Settings', 'is_administrator' => false, 'is_system' => false]);
    $staffer = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staffer)->get('/system/settings/file-retention')->assertForbidden();
});

test('once external storage is active, orphan auto-delete fields are reported inactive and reject changes', function () {
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();
    app(ExternalStorageConfigApplier::class)->flush();

    app(Settings::class)->set(Setting::OrphanFilesAutoDeleteEnabled, false);
    app(Settings::class)->set(Setting::OrphanFilesDeleteAfterDays, 30);

    $this->actingAs($this->admin)->get('/system/settings/file-retention')->assertInertia(
        fn (AssertableInertia $page) => $page->where('external_storage_active', true),
    );

    // An update attempting to enable it is silently ignored — the stored
    // value stays exactly what it was, same as the disabled UI implies.
    $this->actingAs($this->admin)->patch('/system/settings/file-retention', [
        'expired_files_auto_delete_enabled' => true,
        'expired_files_delete_after_days' => 30,
        'orphan_files_auto_delete_enabled' => true,
        'orphan_files_delete_after_days' => 5,
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::OrphanFilesAutoDeleteEnabled))->toBeFalse()
        ->and(app(Settings::class)->get(Setting::OrphanFilesDeleteAfterDays))->toBe(30);
});
