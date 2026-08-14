<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;

/*
|--------------------------------------------------------------------------
| Who may hold a working token at all
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->staff = User::factory()->create();
});

test('a staff token reaches the API', function () {
    $token = $this->staff->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.type', 'staff');
});

/*
 * Clients have no route to a token today — the settings page that issues
 * them is behind `staff`. This covers the case where one exists anyway: a
 * seeded fixture, a support script, or an account whose type changed after
 * issuance. A client token acting on the API is the single largest privacy
 * surface this design could have, so it fails closed rather than relying
 * on the issuing page being the only door.
 */
test('a client token is refused everywhere', function () {
    $client = User::factory()->client()->create();
    $token = $client->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
});

test('a deactivated account loses API access on the next request', function () {
    $token = $this->staff->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    $this->staff->forceFill(['active' => false])->save();
    forgetRequestState();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('a soft-deleted account loses API access', function () {
    $token = $this->staff->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->staff->delete();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('a session cookie cannot authenticate the API', function () {
    // config/sanctum.php lists no stateful domains and no guards, so being
    // logged into the web UI grants nothing here. If this ever starts
    // passing, cookie auth has been reintroduced and with it CSRF exposure
    // and XSS reach into the whole API.
    $this->actingAs($this->staff)->getJson('/api/v1/me')->assertUnauthorized();
});

test('me reports the effective abilities, not the token is stored list', function () {
    $limited = staffWithPermissions([Permission::Upload->value, Permission::EditFiles->value]);

    // Granted both, then demoted to one. The stored list still says two.
    $token = $limited->createToken('t', [Permission::Upload->value, Permission::EditFiles->value])->plainTextToken;

    $limited->role->permissions()->where('permission', Permission::EditFiles->value)->delete();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.abilities', [Permission::Upload->value]);
});

test('a token can revoke itself but only itself', function () {
    $other = $this->staff->createToken('other', [Permission::Upload->value]);
    $current = $this->staff->createToken('current', [Permission::Upload->value]);

    $this->withToken($current->plainTextToken)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    expect($this->staff->tokens()->pluck('name')->all())->toBe(['other'])
        ->and($other->accessToken->fresh())->not->toBeNull();
});
