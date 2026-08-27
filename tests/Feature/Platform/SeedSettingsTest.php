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
