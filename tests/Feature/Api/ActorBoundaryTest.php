<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use App\Modules\Files\Models\File;
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

/*
 * The write half of the same boundary, and it needs its own test now that
 * FilePolicy has a client branch.
 *
 * Since clients may edit and delete their own uploads in the portal,
 * `Gate::authorize('update', $file)` inside Api\FilesController *passes*
 * for a client holding the key on a file they uploaded. The only thing
 * standing between a client token and the API's write endpoints is the
 * `staff-token` middleware. That was always true, but until the portal
 * work it was belt-and-braces: the policy refused as well. It no longer
 * does, so this pins the one remaining door rather than leaving the whole
 * boundary resting on a middleware nothing tests against a *passing*
 * policy.
 *
 * If client tokens are ever issued (see docs/api-todo.md), this test is
 * where that decision has to be made deliberately.
 */
test('a client token cannot write through the API even to its own file', function () {
    $client = User::factory()->client()->create();

    foreach (['edit_files', 'delete_files'] as $permission) {
        $client->role->permissions()->create(['permission' => $permission]);
    }

    $file = File::factory()->create(['uploaded_by' => $client->id]);

    // The policy itself now says yes — this is the premise, not an aside.
    expect(Gate::forUser($client)->allows('update', $file))->toBeTrue()
        ->and(Gate::forUser($client)->allows('delete', $file))->toBeTrue();

    $token = $client->createToken('t', ['edit_files', 'delete_files'])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", ['name' => 'taken'])->assertForbidden();
    $this->withToken($token)->deleteJson("/api/v1/files/{$file->id}")->assertForbidden();

    expect($file->refresh()->name)->not->toBe('taken')
        ->and($file->trashed())->toBeFalse();
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
