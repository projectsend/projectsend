<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AuthSource;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Password;

/**
 * Changing the address a password reset would be sent to is a credential
 * change, and it was the one credential change on the profile screen that
 * asked for nothing.
 *
 * The chain the report walks: a stolen session PATCHes a new address, the
 * attacker asks for a reset there, and the temporary session becomes
 * permanent ownership. Deleting the account, one method further down the
 * same controller, has always required the current password — this is the
 * same rule, on the door that leads to the same place.
 *
 * Reported as GHSA-f32x-fgmp-q353.
 */
beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Real Owner',
        'email' => 'owner@example.com',
        'password' => 'the-real-password',
    ]);
});

test('the email cannot be changed without the current password', function () {
    $this->actingAs($this->user)
        ->from('/settings/profile')
        ->patch('/settings/profile', [
            'name' => 'Real Owner',
            'email' => 'attacker@example.com',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->user->fresh()->email)->toBe('owner@example.com');
});

test('a wrong current password does not change it either', function () {
    $this->actingAs($this->user)
        ->from('/settings/profile')
        ->patch('/settings/profile', [
            'name' => 'Real Owner',
            'email' => 'attacker@example.com',
            'current_password' => 'not-the-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->user->fresh()->email)->toBe('owner@example.com');
});

test('the owner can still change it with their password', function () {
    $this->actingAs($this->user)
        ->patch('/settings/profile', [
            'name' => 'Real Owner',
            'email' => 'new@example.com',
            'current_password' => 'the-real-password',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($this->user->fresh()->email)->toBe('new@example.com');
});

test('everything else on the profile still saves without a password', function () {
    // The reauthentication is about the address, not about the screen. A
    // name or a timezone is not a credential and must not start asking.
    $this->actingAs($this->user)
        ->patch('/settings/profile', [
            'name' => 'Renamed',
            'email' => 'owner@example.com',
            'timezone' => 'Europe/Madrid',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($this->user->fresh()->name)->toBe('Renamed')
        ->and($this->user->fresh()->timezone)->toBe('Europe/Madrid');
});

test('the takeover chain is closed end to end', function () {
    // Not just "the field is validated": the thing that made this high was
    // that the new address immediately became the reset destination.
    Notification::fake();

    $this->actingAs($this->user)
        ->patch('/settings/profile', ['name' => 'Real Owner', 'email' => 'attacker@example.com']);

    $this->post('/forgot-password', ['email' => 'attacker@example.com']);

    Notification::assertNothingSent();
    expect(User::query()->where('email', 'attacker@example.com')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Accounts whose credentials live elsewhere
|--------------------------------------------------------------------------
|
| An LDAP or social account holds a local password nobody knows — see
| LdapProvisioner, which stores Str::password(64) precisely so it can never
| be used. Asking such a person for "your current password" is a dead end
| dressed as a form error, so they are told what is actually true.
*/

test('a directory account is told where its address comes from', function () {
    $ldap = User::factory()->create(['email' => 'directory@example.com']);
    $ldap->forceFill(['auth_source' => AuthSource::Ldap])->save();

    $this->actingAs($ldap)
        ->from('/settings/profile')
        ->patch('/settings/profile', ['name' => $ldap->name, 'email' => 'elsewhere@example.com'])
        ->assertSessionHasErrors('email');

    expect($ldap->fresh()->email)->toBe('directory@example.com');
});

test('a directory account can still change everything else', function () {
    $ldap = User::factory()->create(['email' => 'directory@example.com']);
    $ldap->forceFill(['auth_source' => AuthSource::Ldap])->save();

    $this->actingAs($ldap)
        ->patch('/settings/profile', ['name' => 'New Name', 'email' => 'directory@example.com'])
        ->assertRedirect(route('profile.edit'));

    expect($ldap->fresh()->name)->toBe('New Name');
});
