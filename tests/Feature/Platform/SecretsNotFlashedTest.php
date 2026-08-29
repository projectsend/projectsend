<?php

declare(strict_types=1);

use App\Models\User;

/**
 * A failed save must not write the credential back out in clear.
 *
 * Laravel flashes the request's input into the session when validation
 * fails, so the form can be repopulated. Its own exclusion list is
 * current_password / password / password_confirmation -- written for the
 * login and password forms, and covering none of the credentials the
 * settings screens take. config/session.php stores sessions in the
 * database by default and does not encrypt them, so anything flashed
 * lands in clear in the same database the `encrypted` casts exist to
 * protect.
 *
 * Each of these submits a form that fails validation *and* carries a
 * secret, then reads the old input back the way the form would.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('a rejected storage form does not flash the secret access key', function () {
    // Fails on the missing bucket, which is required.
    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 's3',
        'access_key' => 'AKIAEXAMPLE',
        'secret' => 'super-secret-access-key',
        'region' => 'us-east-1',
        'use_path_style' => false,
    ])->assertSessionHasErrors('bucket');

    expect(session()->getOldInput('secret'))->toBeNull()
        // The rest of the form still comes back, or the screen would clear
        // itself every time somebody mistypes one field.
        ->and(session()->getOldInput('region'))->toBe('us-east-1');
});

test('a rejected storage form does not flash the service account key file', function () {
    // The sharpest case: serviceAccountKeyRule() is what catches a paste
    // that lost its last line, so the request most likely to fail here is
    // the one carrying a private key.
    $key = json_encode([
        'type' => 'service_account',
        'project_id' => 'example',
        'private_key_id' => 'abc',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIBVAIBADAN\n-----END PRIVATE KEY-----\n",
        'client_email' => 'svc@example.iam.gserviceaccount.com',
    ]);

    $this->actingAs($this->admin)->patch('/system/settings/storage', [
        'active' => true,
        'provider' => 'gcs',
        'key_file' => $key,
        'use_path_style' => false,
    ])->assertSessionHasErrors('bucket');

    expect(session()->getOldInput('key_file'))->toBeNull()
        ->and(json_encode(session()->all()))->not->toContain('BEGIN PRIVATE KEY');
});

test('a rejected ldap form does not flash the bind password', function () {
    // Fails on the missing email_attribute, which is required.
    $this->actingAs($this->admin)->patch('/system/settings/ldap', [
        'active' => true,
        'port' => 636,
        'encryption' => 'ssl',
        'bind_dn' => 'cn=admin,dc=example,dc=test',
        'bind_password' => 'super-secret-bind-password',
        'name_attribute' => 'cn',
        'auto_provision' => false,
        'auto_approve' => false,
    ])->assertSessionHasErrors('email_attribute');

    expect(session()->getOldInput('bind_password'))->toBeNull()
        ->and(session()->getOldInput('bind_dn'))->toBe('cn=admin,dc=example,dc=test');
});

test('a rejected social login form does not flash the client secret', function () {
    $this->actingAs($this->admin)->patch('/system/settings/social-login/google', [
        'enabled' => true,
        'client_id' => '',
        'client_secret' => 'super-secret-client-secret',
    ])->assertSessionHasErrors();

    expect(session()->getOldInput('client_secret'))->toBeNull();
});

test('a rejected captcha form does not flash the secret key', function () {
    // Fails on the missing site_key, which turnstile requires.
    $this->actingAs($this->admin)->patch('/system/settings/captcha', [
        'provider' => 'turnstile',
        'secret_key' => 'super-secret-captcha-key',
        'on_login' => false,
        'on_registration' => false,
        'on_password_reset' => false,
        'on_public_comments' => false,
    ])->assertSessionHasErrors('site_key');

    expect(session()->getOldInput('secret_key'))->toBeNull()
        ->and(session()->getOldInput('provider'))->toBe('turnstile');
});

test('the framework defaults are kept, not replaced', function () {
    // dontFlash() merges, so adding to it must not drop password.
    $this->actingAs($this->admin)->put('/settings/password', [
        'current_password' => 'wrong-password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(session()->getOldInput('password'))->toBeNull()
        ->and(session()->getOldInput('current_password'))->toBeNull();
});
