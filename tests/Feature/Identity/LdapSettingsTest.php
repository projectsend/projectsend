<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Ldap\LdapConnectionFactory;
use App\Modules\Identity\Ldap\LdapEncryption;
use App\Modules\Identity\Ldap\LdapSettings;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function ldapPayload(array $overrides = []): array
{
    return array_merge([
        'active' => false,
        'host' => 'ldap.example.test',
        'port' => 389,
        'encryption' => 'tls',
        'ca_cert_path' => null,
        'bind_dn' => 'cn=svc,dc=example,dc=test',
        'bind_password' => 'super-secret-bind-password',
        'base_dn' => 'ou=people,dc=example,dc=test',
        'user_filter' => null,
        'email_attribute' => 'mail',
        'name_attribute' => 'cn',
        'auto_provision' => false,
        'auto_approve' => false,
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| The credential
|--------------------------------------------------------------------------
|
| v1 stored this in plain text and echoed it into the settings form's HTML
| value attribute. Three separate assertions, because each layer is a
| distinct promise.
|
*/

test('the bind password is encrypted at rest', function () {
    $this->actingAs($this->admin)->patch('/system/settings/ldap', ldapPayload())->assertRedirect();

    $raw = DB::table('ldap_settings')->value('bind_password');

    expect($raw)->not->toBe('super-secret-bind-password')
        ->and(LdapSettings::current()->bind_password)->toBe('super-secret-bind-password');
});

test('the bind password is never sent to the browser', function () {
    $this->actingAs($this->admin)->patch('/system/settings/ldap', ldapPayload());

    $this->actingAs($this->admin)->get('/system/settings/ldap')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ldap.has_bind_password', true)
            ->missing('ldap.bind_password'));
});

test('a blank submitted password keeps the stored one', function () {
    $this->actingAs($this->admin)->patch('/system/settings/ldap', ldapPayload());

    $this->actingAs($this->admin)->patch('/system/settings/ldap', ldapPayload([
        'bind_password' => '',
        'host' => 'moved.example.test',
    ]))->assertRedirect();

    $settings = LdapSettings::current();

    expect($settings->host)->toBe('moved.example.test')
        ->and($settings->bind_password)->toBe('super-secret-bind-password');
});

/*
|--------------------------------------------------------------------------
| Encryption actually reaches the connection
|--------------------------------------------------------------------------
|
| v1 exposed a use_tls setting and never called ldap_start_tls() anywhere,
| so every installation that believed it was encrypted was shipping its
| bind password in clear. configFor() is a pure function precisely so this
| can be asserted directly rather than trusted.
|
*/

test('the encryption setting maps onto the connection', function (string $encryption, bool $ssl, bool $tls) {
    $settings = LdapSettings::current();
    $settings->forceFill(['host' => 'ldap.example.test', 'encryption' => $encryption])->save();

    $config = LdapConnectionFactory::configFor($settings->refresh());

    expect($config['use_ssl'])->toBe($ssl)
        ->and($config['use_tls'])->toBe($tls);
})->with([
    'StartTLS' => ['tls', false, true],
    'LDAPS' => ['ssl', true, false],
    'none' => ['none', false, false],
]);

// Certificate verification has no off switch anywhere in this code path;
// a private CA is answered with a CA path instead.
test('a CA certificate path reaches the connection, and verification is never weakened', function () {
    $settings = LdapSettings::current();
    $settings->forceFill(['host' => 'ldap.example.test', 'ca_cert_path' => '/etc/ssl/corp.pem'])->save();

    $config = LdapConnectionFactory::configFor($settings->refresh());

    expect($config['options'][LDAP_OPT_X_TLS_CACERTFILE])->toBe('/etc/ssl/corp.pem')
        ->and($config['options'])->not->toHaveKey(LDAP_OPT_X_TLS_REQUIRE_CERT)
        // Referrals off is not optional against Active Directory.
        ->and($config['options'][LDAP_OPT_REFERRALS])->toBe(0);
});

test('choosing LDAPS moves the default port with it', function () {
    expect(LdapEncryption::Ssl->defaultPort())->toBe(636)
        ->and(LdapEncryption::Tls->defaultPort())->toBe(389)
        ->and(LdapEncryption::None->defaultPort())->toBe(389);
});

/*
|--------------------------------------------------------------------------
| Reach
|--------------------------------------------------------------------------
*/

// LDAP is an administrator's setting, not an edition difference. This is
// the test that encodes the "no new Capability" decision.
test('the screen is reachable in both editions', function (Edition $edition) {
    config()->set('projectsend.edition', $edition);

    $this->actingAs($this->admin)->get('/system/settings/ldap')->assertOk();
})->with([
    'community' => [Edition::Community],
    'cloud' => [Edition::Cloud],
]);

test('a staff member without edit_settings cannot reach or change it', function () {
    $other = staffWithPermissions(['upload']);

    $this->actingAs($other)->get('/system/settings/ldap')->assertForbidden();
    $this->actingAs($other)->patch('/system/settings/ldap', ldapPayload())->assertForbidden();
});

test('clients cannot reach it at all', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/system/settings/ldap')->assertRedirect(route('dashboard'));
});

test('the screen reports whether the server can actually speak LDAP', function () {
    $this->actingAs($this->admin)->get('/system/settings/ldap')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('extension_available', extension_loaded('ldap')));
});

test('an unknown encryption value is rejected', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/ldap', ldapPayload(['encryption' => 'plaintext-please']))
        ->assertSessionHasErrors('encryption');
});

/*
|--------------------------------------------------------------------------
| Usability gate
|--------------------------------------------------------------------------
*/

// Half-configured settings must behave exactly as "switched off" rather
// than throwing on every login.
test('settings are only usable once they are complete', function () {
    $settings = LdapSettings::current();

    $settings->forceFill(['active' => false, 'host' => 'h', 'base_dn' => 'b'])->save();
    expect($settings->usable())->toBeFalse();

    $settings->forceFill(['active' => true, 'host' => null, 'base_dn' => 'b'])->save();
    expect($settings->refresh()->usable())->toBeFalse();

    $settings->forceFill(['active' => true, 'host' => 'h', 'base_dn' => null])->save();
    expect($settings->refresh()->usable())->toBeFalse();

    $settings->forceFill(['active' => true, 'host' => 'h', 'base_dn' => 'b'])->save();
    expect($settings->refresh()->usable())->toBe(extension_loaded('ldap'));
});
