<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Hash;

/*
 * Your own password, email address and second factor are changed from your
 * profile, which asks for your current password first. The staff screens
 * and the API must not be a second door to them: a token holding only
 * manage_users and edit_users could otherwise reset its own owner and
 * become a full browser session with abilities the token was never given,
 * and a borrowed browser session could take the account for good without
 * the password the profile asks for.
 *
 * Changing somebody *else's* credentials is what edit_users and
 * edit_clients mean, on the web and over the API alike, and stays so.
 */
beforeEach(function () {
    $this->admin = User::factory()->role(SystemRole::SystemAdministrator)->create(['password' => 'Original-pass-1!']);
    $this->token = $this->admin->createToken('t', ['manage_users', 'edit_users'])->plainTextToken;
});

test('a token cannot set a new password for its own owner', function () {
    $this->withToken($this->token)->patchJson("/api/v1/users/{$this->admin->id}", ['password' => 'Picked-by-token-9!'])
        ->assertForbidden();

    expect(Hash::check('Original-pass-1!', $this->admin->refresh()->password))->toBeTrue();
});

test('a token cannot change its own owner\'s email address', function () {
    $this->withToken($this->token)->patchJson("/api/v1/users/{$this->admin->id}", ['email' => 'elsewhere@example.test'])
        ->assertForbidden();

    expect($this->admin->refresh()->email)->not->toBe('elsewhere@example.test');
});

test('a token cannot remove its own owner\'s second factor', function () {
    // The factory's own password: enableTwoFactor() confirms with it.
    $owner = User::factory()->role(SystemRole::SystemAdministrator)->create();
    enableTwoFactor($owner);
    forgetRequestState();
    auth()->logout();

    $token = $owner->createToken('t', ['manage_users', 'edit_users'])->plainTextToken;

    $this->withToken($token)->deleteJson("/api/v1/users/{$owner->id}/two-factor")->assertForbidden();

    expect($owner->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a token can still rename its own owner, and resend the same email address', function () {
    $this->withToken($this->token)->patchJson("/api/v1/users/{$this->admin->id}", ['name' => 'Renamed', 'email' => $this->admin->email])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

test('a token can still set another staff member\'s password, and that ends their tokens', function () {
    $colleague = User::factory()->role(SystemRole::Uploader)->create();
    $colleague->createToken('theirs', ['upload']);

    $this->withToken($this->token)->patchJson("/api/v1/users/{$colleague->id}", ['password' => 'Reset-by-admin-9!'])
        ->assertOk();

    expect(Hash::check('Reset-by-admin-9!', $colleague->refresh()->password))->toBeTrue()
        ->and($colleague->tokens()->count())->toBe(0);
});

test('renaming another staff member leaves their tokens alone', function () {
    $colleague = User::factory()->role(SystemRole::Uploader)->create();
    $colleague->createToken('theirs', ['upload']);

    $this->withToken($this->token)->patchJson("/api/v1/users/{$colleague->id}", ['name' => 'New name'])->assertOk();

    expect($colleague->tokens()->count())->toBe(1);
});

test('a token holding edit_clients can still reset a client it manages', function () {
    $staff = staffWithPermissions(['edit_clients']);
    $token = $staff->createToken('t', ['edit_clients'])->plainTextToken;
    $client = User::factory()->client()->create();

    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", ['password' => 'Reset-by-staff-9!'])->assertOk();

    expect(Hash::check('Reset-by-staff-9!', $client->refresh()->password))->toBeTrue();
});

/*
 * The staff screen, editing yourself.
 */
function ownAccountPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $user->role_id,
        'active' => true,
        'assigned_clients' => [],
    ], $overrides);
}

test('the staff screen does not change your own email address', function () {
    $this->actingAs($this->admin)
        ->patch("/users/{$this->admin->id}", ownAccountPayload($this->admin, ['email' => 'elsewhere@example.test']))
        ->assertSessionHasErrors('email');

    expect($this->admin->refresh()->email)->not->toBe('elsewhere@example.test');
});

test('the staff screen does not change your own password', function () {
    $this->actingAs($this->admin)
        ->patch("/users/{$this->admin->id}", ownAccountPayload($this->admin, ['password' => 'Picked-here-9!', 'password_confirmation' => 'Picked-here-9!']))
        ->assertSessionHasErrors('password');

    expect(Hash::check('Original-pass-1!', $this->admin->refresh()->password))->toBeTrue();
});

test('the staff screen still saves the rest of your own account', function () {
    $this->actingAs($this->admin)
        ->patch("/users/{$this->admin->id}", ownAccountPayload($this->admin, ['name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($this->admin->refresh()->name)->toBe('Renamed');
});

test('setting another staff member\'s password on the screen ends their tokens', function () {
    $colleague = User::factory()->role(SystemRole::Uploader)->create();
    $colleague->createToken('theirs', ['upload']);

    $this->actingAs($this->admin)
        ->patch("/users/{$colleague->id}", ownAccountPayload($colleague, ['password' => 'Reset-by-admin-9!', 'password_confirmation' => 'Reset-by-admin-9!']))
        ->assertSessionHasNoErrors();

    expect($colleague->tokens()->count())->toBe(0);
});
