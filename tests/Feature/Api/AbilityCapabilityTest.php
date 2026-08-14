<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Api\Auth\TokenAbilities;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Abilities are gated by capability as well as permission
|--------------------------------------------------------------------------
|
| Two independent gates: a permission says what a role may do, a capability
| says what the edition has at all. Every other surface applies both
| (routes/web.php pairs `capability:users.manage` with `can:manage_users`;
| the sidebar pairs them again). Token issuance must too, or a cloud install
| offers abilities for features it does not have.
|
| The suite runs as community by default (phpunit.xml), which is why the
| original permission-only implementation looked correct here while the
| cloud dev install visibly offered custom-asset and user-management
| abilities that could never work. These flip the edition explicitly.
|
*/

beforeEach(function () {
    $this->admin = User::factory()->create();
    confirmPassword($this->admin);
});

function asCloud(): void
{
    // CapabilityRegistry is bound (not a singleton) precisely so this works.
    config(['projectsend.edition' => Edition::Cloud]);
}

test('the capability pairing covers exactly the edition-gated permissions', function () {
    $paired = collect(Permission::cases())
        ->filter(fn (Permission $p): bool => $p->capability() !== null)
        ->map(fn (Permission $p): string => $p->value)
        ->values()
        ->all();

    expect($paired)->toBe([
        'create_users',
        'edit_users',
        'delete_users',
        'manage_users',
        'manage_updates',
        'create_assets',
        'edit_assets',
        'delete_assets',
    ]);
});

/*
 * The capability half is asserted against isAvailable() rather than the
 * rendered checkbox list: the "is it implemented" filter also applies to
 * the list, and none of the capability-gated permissions has an endpoint
 * yet, so the UI cannot currently distinguish "absent in this edition"
 * from "not built yet". This targets the edition question on its own.
 */
test('capability-gated abilities are unavailable in an edition that lacks them', function () {
    asCloud();
    $abilities = app(TokenAbilities::class);

    foreach (['manage_users', 'create_users', 'manage_updates', 'create_assets', 'delete_assets'] as $key) {
        expect($abilities->isAvailable($key))->toBeFalse();
    }

    // Ungated abilities are unaffected by the edition.
    expect($abilities->isAvailable('upload'))->toBeTrue()
        ->and($abilities->isAvailable('edit_settings'))->toBeTrue();
});

test('the same abilities are available in the edition that has them', function () {
    $abilities = app(TokenAbilities::class); // community, per phpunit.xml

    foreach (['manage_users', 'create_users', 'manage_updates', 'create_assets', 'delete_assets'] as $key) {
        expect($abilities->isAvailable($key))->toBeTrue();
    }
});

test('the rendered checkbox list is never empty of implemented abilities', function () {
    $offered = $this->actingAs($this->admin)->get('/settings/api-tokens/create')
        ->assertOk()
        ->viewData('page')['props']['available_abilities'];

    $keys = collect($offered)->flatMap(fn (array $group) => array_column($group['abilities'], 'key'))->all();

    // `upload` is read by the files endpoints and is edition-agnostic, so
    // it is offered wherever this suite runs.
    expect($keys)->toContain('upload');
});

test('issuance rejects a capability-unavailable ability even if the role grants it', function () {
    asCloud();

    // The role still grants manage_users — an administrator holds every
    // permission — so only the capability half can refuse this.
    $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'Cloud user management',
        'abilities' => [Permission::ManageUsers->value],
        'expires_in_days' => 30,
    ])->assertSessionHasErrors('abilities.0');

    expect($this->admin->tokens()->count())->toBe(0);
});

test('a token minted under one edition stops working under another', function () {
    Route::middleware(['auth:sanctum', 'api-active', 'staff-token', 'token-can:manage_users'])
        ->get('api/v1/_test/users', fn () => response()->json(['ok' => true]));

    // Minted on community, where the capability exists.
    $token = $this->admin->createToken('t', [Permission::ManageUsers->value])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/_test/users')->assertOk();

    asCloud();
    forgetRequestState();

    $this->withToken($token)->getJson('/api/v1/_test/users')->assertForbidden();
});

test('me reports only abilities the edition can honour', function () {
    asCloud();

    $token = $this->admin->createToken('t', [
        Permission::Upload->value,
        Permission::ManageUsers->value,
    ])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.abilities', [Permission::Upload->value]);
});

/*
|--------------------------------------------------------------------------
| Only abilities an endpoint actually consumes are offered
|--------------------------------------------------------------------------
|
| The API covers a fraction of the web UI. Offering a checkbox for an
| ability no endpoint reads invites granting something that silently does
| nothing, and quietly widens a credential for no benefit.
|
| These assert the mechanism rather than a snapshot of today's endpoints,
| so they keep their value as each phase lands instead of needing an edit
| every time the surface grows.
|
*/

test('every offered ability is required by at least one API route', function () {
    $abilities = app(TokenAbilities::class);

    $offered = collect($abilities->casesFor($this->admin))
        ->map(fn (Permission $p): string => $p->value)
        ->all();

    expect($offered)->not->toBeEmpty();

    foreach ($offered as $key) {
        expect($abilities->inUse())->toContain($key);
    }
});

test('a permission with no endpoint behind it is not offered', function () {
    $abilities = app(TokenAbilities::class);

    // Whatever the API implements, it does not implement every permission —
    // pick one that no route consumes and assert it stays out.
    $unimplemented = collect(Permission::cases())
        ->map(fn (Permission $p): string => $p->value)
        ->reject(fn (string $key): bool => in_array($key, $abilities->inUse(), true))
        ->first();

    expect($unimplemented)->not->toBeNull()
        ->and($abilities->availableFor($this->admin))->not->toContain($unimplemented);

    $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'Reaching for an unbuilt feature',
        'abilities' => [$unimplemented],
        'expires_in_days' => 30,
    ])->assertSessionHasErrors('abilities.0');
});

test('registering an endpoint makes its abilities selectable', function () {
    $abilities = app(TokenAbilities::class);
    $unimplemented = collect(Permission::cases())
        ->map(fn (Permission $p): string => $p->value)
        ->reject(fn (string $key): bool => in_array($key, $abilities->inUse(), true))
        ->reject(fn (string $key): bool => ! $abilities->isAvailable($key))
        ->first();

    Route::middleware(['auth:sanctum', 'api-active', 'staff-token', "token-can:{$unimplemented}"])
        ->get('api/v1/_test/new-feature', fn () => response()->json(['ok' => true]));

    // A fresh instance, since the route scan is memoised per instance.
    expect(app()->make(TokenAbilities::class)->availableFor($this->admin))->toContain($unimplemented);
});

test('an unknown ability string is never available', function () {
    $abilities = app(TokenAbilities::class);

    expect($abilities->isAvailable('not_a_permission'))->toBeFalse()
        // A capability key is not an ability key — the two vocabularies
        // must never be interchangeable.
        ->and($abilities->isAvailable(Capability::Branding->value))->toBeFalse();
});
