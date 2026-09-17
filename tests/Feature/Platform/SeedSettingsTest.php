<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\StoredSetting;

/**
 * A policy that has to exist before the account it protects.
 *
 * On a managed installation the only writers of a setting are whoever
 * administers it and this command — and the administrator is created in
 * the same first boot, one line below. So enforcement seeded here covers
 * the first seat; seeded by anything calling in afterwards does not.
 */
beforeEach(function () {
    // Settings are cached across tests (Cache::rememberForever survives the
    // per-test rollback), so the starting point is set rather than assumed.
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'none');
    StoredSetting::query()->where('key', Setting::TwoFactorEnforcement->value)->delete();
    app(Settings::class)->flush();
});

test('a setting nobody has stored is seeded from the environment', function () {
    config(['projectsend.platform.two_factor_enforcement' => 'staff']);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::TwoFactorEnforcement))->toBe('staff');
});

test('a setting somebody has already chosen is left alone', function () {
    // The whole design. An environment value that won every boot would
    // take the setting away from the administrator it belongs to, and
    // somebody who tightened it would find it loosened by a restart.
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'all');
    config(['projectsend.platform.two_factor_enforcement' => 'staff']);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::TwoFactorEnforcement))->toBe('all');
});

test('a stored value equal to the default still counts as chosen', function () {
    // 'none' is the enum's default, so Settings::get() cannot tell it apart
    // from nothing stored. An administrator who deliberately set 'none'
    // must not have it overwritten on the next restart, which is why the
    // command asks the table rather than the accessor.
    app(Settings::class)->set(Setting::TwoFactorEnforcement, 'none');
    config(['projectsend.platform.two_factor_enforcement' => 'all']);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::TwoFactorEnforcement))->toBe('none');
});

test('an unset variable seeds nothing and says nothing', function () {
    config(['projectsend.platform.two_factor_enforcement' => null]);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    expect(StoredSetting::query()->where('key', Setting::TwoFactorEnforcement->value)->exists())->toBeFalse();
});

test('a value that is not one of the four is named rather than ignored', function () {
    // A typo here means a tenant provisioned without the policy it was
    // meant to have. Silence would make that look like success.
    config(['projectsend.platform.two_factor_enforcement' => 'stafff']);

    $this->artisan('projectsend:seed-settings')
        ->expectsOutputToContain('is not one of none, staff, clients, all')
        ->assertSuccessful();

    expect(app(Settings::class)->get(Setting::TwoFactorEnforcement))->toBe('none');
});

test('the seeded policy is in force for the first account the same boot creates', function () {
    // The point of the ordering, end to end: the entrypoint seeds and then
    // creates the administrator, so that account is born under the policy
    // rather than ahead of it.
    config(['projectsend.platform.two_factor_enforcement' => 'staff']);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    $this->artisan('projectsend:admin', [
        '--name' => 'First',
        '--email' => 'first@example.test',
        '--password' => 'a-strong-password-1',
    ])->assertSuccessful();

    $admin = User::query()->where('email', 'first@example.test')->sole();

    expect(app(Settings::class)->get(Setting::TwoFactorEnforcement))->toBe('staff')
        ->and($admin->hasTwoFactorEnabled())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The virus scanner
|--------------------------------------------------------------------------
|
| The opposite of PROJECTSEND_SCANNER_ADDRESS, which is a policy the
| platform keeps. This is a starting value for an operator who brought up
| the optional scanner container beside the application: it arrives
| configured, and stays theirs to change.
|
*/

test('a first boot points the installation at the scanner named in its environment', function () {
    config(['projectsend.scanning.default_address' => 'tcp://clamav:3310']);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    $settings = app(App\Modules\Platform\Settings\Settings::class);

    expect($settings->get(App\Modules\Platform\Settings\Setting::VirusScannerAddress))->toBe('tcp://clamav:3310')
        // Both together: an address with scanning off would look
        // configured and check nothing.
        ->and($settings->get(App\Modules\Platform\Settings\Setting::VirusScanningEnabled))->toBeTrue();
});

test('it never argues with an administrator who has already chosen', function () {
    $settings = app(App\Modules\Platform\Settings\Settings::class);
    $settings->set(App\Modules\Platform\Settings\Setting::VirusScannerAddress, '');
    $settings->set(App\Modules\Platform\Settings\Setting::VirusScanningEnabled, false);

    config(['projectsend.scanning.default_address' => 'tcp://clamav:3310']);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    // Cleared on purpose is a decision, and a restart must not undo it.
    expect($settings->get(App\Modules\Platform\Settings\Setting::VirusScannerAddress))->toBe('')
        ->and($settings->get(App\Modules\Platform\Settings\Setting::VirusScanningEnabled))->toBeFalse();
});

test('an installation with no scanner named in its environment is left alone', function () {
    config(['projectsend.scanning.default_address' => null]);

    $this->artisan('projectsend:seed-settings')->assertSuccessful();

    expect(app(App\Modules\Platform\Settings\Settings::class)->get(App\Modules\Platform\Settings\Setting::VirusScanningEnabled))->toBeFalse();
});
