<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EnsureTokenCan
|--------------------------------------------------------------------------
|
| These run against a throwaway route rather than a real endpoint, so the
| middleware's own behaviour is what's under test and stays covered no
| matter which domain endpoints exist at the time.
|
*/

beforeEach(function () {
    // EnsureSetupIsComplete: without a staff user every web request
    // redirects to /setup. API routes don't carry that middleware, but the
    // suite is cheaper to reason about with the same precondition.
    User::factory()->create();

    Route::middleware(['auth:sanctum', 'api-active', 'staff-token', 'token-can:edit_files,edit_others_files'])
        ->get('api/v1/_test/guarded', fn () => response()->json(['ok' => true]));
});

test('a token carrying the ability its owner still holds is accepted', function () {
    $staff = staffWithPermissions([Permission::EditFiles->value]);
    $token = $staff->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertOk();
});

test('a token that was never granted the ability is refused', function () {
    $staff = staffWithPermissions([Permission::EditFiles->value]);
    $token = $staff->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertForbidden();
});

/*
 * The property stock Sanctum does not have: Sanctum only asks what was
 * baked into the token at creation. A role change after the fact has to
 * take effect on the next request, or a demotion is meaningless for as
 * long as an old token exists.
 */
test('a token stops working when its owner loses the underlying permission', function () {
    $staff = staffWithPermissions([Permission::EditFiles->value]);
    $token = $staff->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertOk();

    RolePermission::query()
        ->where('role_id', $staff->role_id)
        ->where('permission', Permission::EditFiles->value)
        ->delete();
    forgetRequestState();

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertForbidden();
});

test('moving the owner to a role without the permission has the same effect', function () {
    $staff = staffWithPermissions([Permission::EditFiles->value]);
    $token = $staff->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $staff->forceFill(['role_id' => Role::query()->create(['name' => 'Powerless'])->id])->save();

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertForbidden();
});

test('any one of the listed abilities is enough', function () {
    $staff = staffWithPermissions([Permission::EditOthersFiles->value]);
    $token = $staff->createToken('t', [Permission::EditOthersFiles->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertOk();
});

test('no token at all is a 401, not a 403', function () {
    $this->getJson('/api/v1/_test/guarded')->assertUnauthorized();
});

test('a garbage token is a 401', function () {
    $this->withToken('psend_not-a-real-token')->getJson('/api/v1/_test/guarded')->assertUnauthorized();
});

test('an expired token is a 401', function () {
    $staff = staffWithPermissions([Permission::EditFiles->value]);
    $token = $staff->createToken('t', [Permission::EditFiles->value], now()->subMinute())->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/_test/guarded')->assertUnauthorized();
});

test('a revoked token is a 401', function () {
    $staff = staffWithPermissions([Permission::EditFiles->value]);
    $created = $staff->createToken('t', [Permission::EditFiles->value]);

    $created->accessToken->delete();

    $this->withToken($created->plainTextToken)->getJson('/api/v1/_test/guarded')->assertUnauthorized();
});
