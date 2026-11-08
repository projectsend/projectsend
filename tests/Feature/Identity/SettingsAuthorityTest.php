<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * edit_settings is not the administrator. Two things follow.
 *
 *  - Outgoing mail carries every password-reset link, so whoever picks the
 *    mail server can reset the administrator's password. The mail screen
 *    is the administrator's alone, like LDAP and social login.
 *  - The settings screens act on the whole installation, so a role limited
 *    to assigned clients cannot hold edit_settings at all, the same way it
 *    cannot hold import_orphans.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function settingsRole(bool $scoped): User
{
    $role = Role::query()->create(['name' => 'Settings '.Str::random(6), 'client_scoped' => $scoped]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'edit_settings']);

    return User::factory()->create(['role_id' => $role->id]);
}

test('a non-administrator with edit_settings cannot open or change the mail settings', function () {
    $editor = settingsRole(scoped: false);
    confirmPassword($editor);

    $this->actingAs($editor)->get('/system/settings/general')->assertOk();
    $this->actingAs($editor)->get('/system/settings/email')->assertForbidden();
    $this->actingAs($editor)->patch('/system/settings/email', ['mail_mailer' => 'smtp', 'mail_host' => 'relay.evil.test'])->assertForbidden();
    $this->actingAs($editor)->post('/system/settings/email/oauth/connect')->assertForbidden();
    $this->actingAs($editor)->delete('/system/settings/email/oauth')->assertForbidden();
});

test('the administrator is asked for the password before changing the mail settings', function () {
    $this->actingAs($this->admin)->get('/system/settings/email')->assertOk();

    $this->actingAs($this->admin)->patch('/system/settings/email', ['mail_mailer' => 'smtp'])
        ->assertRedirect('/confirm-password');
});

test('a role limited to assigned clients cannot hold edit_settings', function () {
    $scoped = settingsRole(scoped: true);
    $group = Group::query()->create(['name' => 'Board']);

    expect($scoped->can('edit_settings'))->toBeFalse();

    $this->actingAs($scoped)->get('/system/settings/clients')->assertForbidden();
    $this->actingAs($scoped)->patch('/system/settings/clients', [
        'clients_can_register' => true,
        'clients_auto_approve' => true,
        'clients_auto_group' => $group->id,
    ])->assertForbidden();
    $this->actingAs($scoped)->get('/system/settings/privacy')->assertForbidden();
});

test('a non-administrator with edit_settings cannot choose external storage', function () {
    $editor = settingsRole(scoped: false);
    confirmPassword($editor);

    $this->actingAs($editor)->get('/system/settings/storage')->assertForbidden();
    $this->actingAs($editor)->patch('/system/settings/storage', [
        'provider' => 's3', 'bucket' => 'theirs', 'endpoint' => 'https://bucket.evil.test',
    ])->assertForbidden();
    $this->actingAs($editor)->post('/system/settings/storage/test', [
        'provider' => 's3', 'endpoint' => 'http://10.0.0.1:6379',
    ])->assertForbidden();
});

test('the administrator is asked for the password before changing external storage', function () {
    $this->actingAs($this->admin)->get('/system/settings/storage')->assertOk();
    $this->actingAs($this->admin)->patch('/system/settings/storage', ['provider' => 's3'])
        ->assertRedirect('/confirm-password');
});

test('a non-administrator with edit_settings may change the scanning options but not the scanner', function () {
    $settings = app(Settings::class);
    $settings->set(Setting::VirusScannerAddress, 'tcp://clamav:3310');
    $settings->set(Setting::VirusScanningEnabled, true);
    $editor = settingsRole(scoped: false);

    $this->actingAs($editor)->get('/system/settings/virus-scanning')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('connection_locked', true)
            ->where('can_test', false)
            ->where('address', '')
    );

    $this->actingAs($editor)->patch('/system/settings/virus-scanning', [
        'enabled' => false,
        'address' => 'tcp://redis:6379',
        'max_size_mb' => 10,
        'unscannable_policy' => 'block',
        'scanner_down_policy' => 'hold',
        'wait_minutes' => 5,
        'existing_rate_per_minute' => 60,
    ])->assertRedirect();

    expect($settings->get(Setting::VirusScannerAddress))->toBe('tcp://clamav:3310')
        ->and($settings->get(Setting::VirusScanningEnabled))->toBeTrue()
        ->and($settings->get(Setting::VirusUnscannablePolicy))->toBe('block');

    $this->actingAs($editor)->post('/system/settings/virus-scanning/test', ['address' => 'tcp://redis:6379'])
        ->assertForbidden();
});
