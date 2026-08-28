<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\MailProviderSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the password is encrypted at rest', function () {
    MailProviderSettings::current()->fill(['password' => 'super-secret-smtp-password'])->save();

    $raw = DB::table('mail_provider_settings')->value('password');

    expect($raw)->not->toBe('super-secret-smtp-password')
        ->and(MailProviderSettings::current()->password)->toBe('super-secret-smtp-password');
});

test('the password never reaches the cache store', function () {
    // The column above is only half the promise. This class caches its
    // resolved settings forever on every process boot, and a cache store
    // encrypts nothing — so a password in that array is a password in
    // clear, in whatever the store happens to be. On the store INSTALL.md
    // documents that is the same database the cast above protects.
    MailProviderSettings::current()->fill([
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => 'postmaster',
        'password' => 'super-secret-smtp-password',
    ])->save();

    Cache::flush();
    app(MailConfigApplier::class)->apply();

    $cached = Cache::get('platform.mail_provider_settings.v4');

    expect($cached)->toBeArray()
        ->and(json_encode($cached))->not->toContain('super-secret-smtp-password');
});

test('the password never reaches the database cache store either', function () {
    // The same assertion against the store config/cache.php actually
    // defaults to, read as the raw row an operator would find in a dump —
    // phpunit.xml runs the suite on the array store, where a regression
    // here would still be invisible to anybody reading a backup.
    config(['cache.default' => 'database']);

    MailProviderSettings::current()->fill([
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => 'postmaster',
        'password' => 'super-secret-smtp-password',
    ])->save();

    Cache::store('database')->flush();
    app(MailConfigApplier::class)->apply();

    $rows = DB::table('cache')->pluck('value')->implode('|');

    expect($rows)->not->toContain('super-secret-smtp-password');
});

test('apply() still configures the smtp password it no longer caches', function () {
    // The other half: keeping the credential out of the cache must not
    // stop the transport from being configured with it.
    MailProviderSettings::current()->fill([
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => 'postmaster',
        'password' => 'super-secret-smtp-password',
    ])->save();

    Cache::flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.mailers.smtp.password'))->toBe('super-secret-smtp-password');
});

test('apply() is a no-op when no host is configured', function () {
    $originalHost = config('mail.mailers.smtp.host');

    app(MailConfigApplier::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe($originalHost);
});

test('apply() overrides mail config once a host is saved', function () {
    MailProviderSettings::current()->fill([
        'host' => 'smtp.example.test',
        'port' => 2525,
        'username' => 'mailer',
        'password' => 'secret',
        'encryption' => 'tls',
        'from_address' => 'noreply@example.test',
        'from_name' => 'Example App',
    ])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret')
        ->and(config('mail.mailers.smtp.encryption'))->toBe('tls')
        ->and(config('mail.from.address'))->toBe('noreply@example.test')
        ->and(config('mail.from.name'))->toBe('Example App');
});

test('apply() maps "none" encryption to a null Symfony-compatible value', function () {
    MailProviderSettings::current()->fill(['host' => 'smtp.example.test', 'encryption' => 'none'])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

test('staff can save mail provider settings', function () {
    $this->actingAs($this->admin)->patch('/system/settings/email', validEmailSettingsPayload([
        'provider' => 'sendgrid',
        'host' => 'smtp.sendgrid.net',
        'port' => 587,
        'username' => 'apikey',
        'password' => 'sg-secret',
    ]))->assertRedirect();

    $settings = MailProviderSettings::current();
    expect($settings->provider->value)->toBe('sendgrid')
        ->and($settings->host)->toBe('smtp.sendgrid.net')
        ->and($settings->port)->toBe(587)
        ->and($settings->password)->toBe('sg-secret');
});

test('saving with a blank password keeps the previously stored password', function () {
    $this->actingAs($this->admin)->patch('/system/settings/email', validEmailSettingsPayload([
        'username' => 'mailer',
        'password' => 'first-password',
    ]));

    $this->actingAs($this->admin)->patch('/system/settings/email', validEmailSettingsPayload([
        'username' => 'mailer',
        'password' => '',
        'from_name' => 'Renamed App',
    ]));

    $settings = MailProviderSettings::current();
    expect($settings->password)->toBe('first-password')
        ->and($settings->from_name)->toBe('Renamed App');
});

test('saving mail provider settings rejects invalid input', function () {
    $this->actingAs($this->admin)->patch('/system/settings/email', validEmailSettingsPayload([
        'port' => 999999,
        'encryption' => 'rot13',
        'from_address' => 'not-an-email',
    ]))->assertSessionHasErrors(['port', 'encryption', 'from_address']);
});

test('saving mail provider settings signals the queue worker to restart', function () {
    Cache::forget('illuminate:queue:restart');

    $this->actingAs($this->admin)->patch('/system/settings/email', validEmailSettingsPayload());

    expect(Cache::get('illuminate:queue:restart'))->not->toBeNull();
});

test('clients cannot save mail provider settings', function () {
    $this->actingAs(User::factory()->client()->create())
        ->patch('/system/settings/email', validEmailSettingsPayload())
        ->assertForbidden();
});
