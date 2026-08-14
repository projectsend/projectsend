<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('t', [
        Permission::ManageUsers->value,
        Permission::CreateUsers->value,
        Permission::EditUsers->value,
        Permission::DeleteUsers->value,
    ])->plainTextToken;
});

/**
 * Materialised on demand, the same way UserFactory does it: fresh installs
 * no longer seed the legacy Uploader role, so looking it up without
 * creating it returns null on a database where no test has used it yet.
 */
function roleId(SystemRole $role): int
{
    return app(EnsureSystemRoles::class)->materialize($role)->id;
}

/**
 * A staff account that genuinely holds the user-management permissions but
 * is not an administrator.
 *
 * Not a shortcut: EnsureTokenCan re-checks the owner's live permissions on
 * every request, so handing these abilities to a token owned by, say, a
 * Client Manager would be refused by the ability gate long before any
 * authority rule was reached — and the test would pass while asserting
 * nothing about the rule it names.
 *
 * @param  list<string>  $permissions
 * @return array{User, string}
 */
function userManager(array $permissions = ['manage_users', 'create_users', 'edit_users', 'delete_users']): array
{
    $role = Role::query()->create(['name' => 'User Manager '.uniqid()]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    $manager = User::factory()->create(['role_id' => $role->id]);

    return [$manager, $manager->createToken('t', $permissions)->plainTextToken];
}

/*
|--------------------------------------------------------------------------
| Edition
|--------------------------------------------------------------------------
|
| The whole point of the feature: managed installations create staff
| accounts outside the application, so an API able to mint them there
| would be a second, unmanaged door into the same thing.
|
*/

test('every staff endpoint is refused on cloud, with a reason a caller can branch on', function (string $method, string $uri) {
    config(['projectsend.edition' => Edition::Cloud]);

    $this->withToken($this->token)->json($method, $uri)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'capability_unavailable')
        ->assertJsonPath('capability', 'users.manage')
        ->assertJsonPath('edition', 'cloud');
})->with([
    'list' => ['GET', '/api/v1/users'],
    'roles' => ['GET', '/api/v1/roles'],
    'read' => ['GET', '/api/v1/users/1'],
    'create' => ['POST', '/api/v1/users'],
    'update' => ['PATCH', '/api/v1/users/1'],
    'delete' => ['DELETE', '/api/v1/users/1'],
    'reset two-factor' => ['DELETE', '/api/v1/users/1/two-factor'],
]);

// The routes exist in every edition so the committed OpenAPI document is
// identical everywhere — the middleware refuses, the route table does not
// lie. A 404 here would mean the document described a path that was not
// registered.
test('the routes are registered on cloud even though they refuse', function () {
    config(['projectsend.edition' => Edition::Cloud]);

    $this->withToken($this->token)->getJson('/api/v1/users')->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Privacy
|--------------------------------------------------------------------------
|
| `users` is the most sensitive table here, and these rows carry the
| permissions that administer the installation. Asserted against the raw
| body so a leak through a nested relation or a future column is caught.
|
*/

test('no staff response carries credentials', function () {
    $other = User::factory()->role(SystemRole::Uploader)->create();

    $bodies = [
        $this->withToken($this->token)->getJson('/api/v1/users')->getContent(),
        $this->withToken($this->token)->getJson("/api/v1/users/{$other->id}")->getContent(),
        $this->withToken($this->token)->postJson('/api/v1/users', [
            'name' => 'New Person', 'email' => 'new@example.test',
            'role_id' => roleId(SystemRole::Uploader), 'password' => 'correct-horse-battery-staple',
        ])->getContent(),
    ];

    foreach ($bodies as $body) {
        expect($body)->not->toContain('password')
            ->and($body)->not->toContain('two_factor_secret')
            ->and($body)->not->toContain('two_factor_recovery_codes')
            ->and($body)->not->toContain('remember_token');
    }
});

/*
|--------------------------------------------------------------------------
| Reading
|--------------------------------------------------------------------------
*/

test('the list carries staff only, never clients', function () {
    User::factory()->role(SystemRole::Uploader)->create(['name' => 'A Staffer']);
    $client = User::factory()->client()->create(['name' => 'A Client']);

    $ids = collect($this->withToken($this->token)->getJson('/api/v1/users')
        ->assertOk()->json('data'))->pluck('id');

    expect($ids)->not->toContain($client->id)
        ->and($ids)->toContain($this->admin->id);
});

test('a client is not addressable as a staff account even by id', function () {
    $client = User::factory()->client()->create();

    $this->withToken($this->token)->getJson("/api/v1/users/{$client->id}")->assertNotFound();
    $this->withToken($this->token)->patchJson("/api/v1/users/{$client->id}", ['name' => 'x'])->assertNotFound();
    $this->withToken($this->token)->deleteJson("/api/v1/users/{$client->id}")->assertNotFound();
});

test('reading one account carries its role, its clients and its content counts', function () {
    $user = User::factory()->role(SystemRole::Uploader)->create();

    $this->withToken($this->token)->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.role.name', SystemRole::Uploader->value)
        ->assertJsonPath('data.role.is_administrator', false)
        ->assertJsonPath('data.content', ['files' => 0, 'folders' => 0])
        ->assertJsonPath('data.assigned_client_ids', [])
        ->assertJsonPath('data.two_factor_enabled', false);
});

test('the list filters by search, role and status', function () {
    $uploader = User::factory()->role(SystemRole::Uploader)->create(['name' => 'Zoe Zebra', 'active' => false]);

    $byName = $this->withToken($this->token)->getJson('/api/v1/users?search=Zebra')->assertOk()->json('data');
    expect($byName)->toHaveCount(1)->and($byName[0]['id'])->toBe($uploader->id);

    $byRole = $this->withToken($this->token)->getJson('/api/v1/users?role_id='.roleId(SystemRole::Uploader))->assertOk()->json('data');
    expect(collect($byRole)->pluck('id')->all())->toBe([$uploader->id]);

    $inactive = $this->withToken($this->token)->getJson('/api/v1/users?status=inactive')->assertOk()->json('data');
    expect(collect($inactive)->pluck('id')->all())->toBe([$uploader->id]);
});

test('roles lists what may be assigned, and never the client role', function () {
    roleId(SystemRole::Uploader);

    $names = collect($this->withToken($this->token)->getJson('/api/v1/roles')->assertOk()->json('data'))
        ->pluck('name');

    expect($names)->toContain(SystemRole::Uploader->value)
        ->and($names)->not->toContain(SystemRole::Client->value);
});

/*
|--------------------------------------------------------------------------
| Writing
|--------------------------------------------------------------------------
*/

test('creating an account makes it active, verified and audited', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/users', [
        'name' => 'New Person',
        'email' => 'new@example.test',
        'role_id' => roleId(SystemRole::Uploader),
        'password' => 'correct-horse-battery-staple',
    ])->assertCreated();

    $user = User::query()->where('email', 'new@example.test')->sole();

    expect($user->active)->toBeTrue()
        ->and($user->isStaff())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($response->json('data.id'))->toBe($user->id);

    expect(ActivityLog::query()->where('action', Action::UserCreated)->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('updating is PATCH — an absent field is left alone', function () {
    $user = User::factory()->role(SystemRole::Uploader)->create(['name' => 'Before', 'email' => 'before@example.test']);

    $this->withToken($this->token)->patchJson("/api/v1/users/{$user->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    expect($user->refresh()->email)->toBe('before@example.test');
});

test('the assigned role can be changed, and the change is audited', function () {
    $user = User::factory()->role(SystemRole::Uploader)->create();
    $managerId = roleId(SystemRole::AccountManager);

    $this->withToken($this->token)->patchJson("/api/v1/users/{$user->id}", ['role_id' => $managerId])
        ->assertOk()
        ->assertJsonPath('data.role.id', $managerId);

    expect($user->refresh()->role_id)->toBe($managerId);

    $entry = ActivityLog::query()->where('action', Action::UserUpdated)->where('subject_id', $user->id)->sole();
    expect($entry->context['role']['to'])->toBe(SystemRole::AccountManager->value);
});

test('deactivating and reactivating each log their own entry', function () {
    $user = User::factory()->role(SystemRole::Uploader)->create();

    $this->withToken($this->token)->patchJson("/api/v1/users/{$user->id}", ['active' => false])->assertOk();
    $this->withToken($this->token)->patchJson("/api/v1/users/{$user->id}", ['active' => true])->assertOk();

    expect(ActivityLog::query()->where('action', Action::UserDeactivated)->where('subject_id', $user->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::UserActivated)->where('subject_id', $user->id)->exists())->toBeTrue();
});

// Only a client-scoped role has a roster; any other role clears it, which
// is the sync's rule rather than something a caller has to remember.
test('assigned clients stick to a client-scoped role and are cleared by any other', function () {
    $client = User::factory()->client()->create();
    $user = User::factory()->role(SystemRole::ClientManager)->create();

    $this->withToken($this->token)->patchJson("/api/v1/users/{$user->id}", [
        'assigned_clients' => [$client->id],
    ])->assertOk()->assertJsonPath('data.assigned_client_ids', [$client->id]);

    $this->withToken($this->token)->patchJson("/api/v1/users/{$user->id}", [
        'role_id' => roleId(SystemRole::Uploader),
    ])->assertOk()->assertJsonPath('data.assigned_client_ids', []);
});

/*
|--------------------------------------------------------------------------
| Authority
|--------------------------------------------------------------------------
|
| The invariants StaffAccounts exists to hold in one place. If the API
| ever stops calling it, these are what notice.
|
*/

test('a caller cannot grant a role carrying permissions they do not hold', function () {
    [, $token] = userManager();

    $administratorId = Role::query()->where('is_administrator', true)->value('id');

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Escalation',
        'email' => 'escalation@example.test',
        'role_id' => $administratorId,
        'password' => 'correct-horse-battery-staple',
    ])->assertStatus(422)->assertJsonPath('type', 'validation_failed');

    expect(User::query()->where('email', 'escalation@example.test')->exists())->toBeFalse();
});

test('a caller cannot touch an account whose role outranks them', function () {
    [, $token] = userManager();

    $this->withToken($token)->getJson("/api/v1/users/{$this->admin->id}")->assertForbidden();
    $this->withToken($token)->patchJson("/api/v1/users/{$this->admin->id}", ['name' => 'x'])->assertForbidden();
    $this->withToken($token)->deleteJson("/api/v1/users/{$this->admin->id}")->assertForbidden();

    expect($this->admin->refresh()->name)->not->toBe('x');
});

// Only the administrator themselves can attempt this: anybody else is
// stopped by guardTarget first, since they could not grant that role.
test('the last active administrator cannot demote themselves', function () {
    $this->withToken($this->token)->patchJson("/api/v1/users/{$this->admin->id}", [
        'role_id' => roleId(SystemRole::Uploader),
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.role_id.0', 'This is the last active administrator account.');

    expect($this->admin->refresh()->role?->is_administrator)->toBeTrue();
});

test('deleting an administrator is refused while they are the last active one', function () {
    $second = User::factory()->create();
    [, $token] = userManager(['manage_users', 'delete_users']);

    // A User Manager cannot delete an administrator at all, so the actor
    // here has to be the other administrator.
    $this->withToken($token)->deleteJson("/api/v1/users/{$second->id}")->assertForbidden();

    // The auth guard caches whoever it resolved first, so a second actor in
    // the same test would otherwise be ignored — see forgetRequestState().
    forgetRequestState();

    // With two administrators, one may go.
    $this->withToken($this->token)->deleteJson("/api/v1/users/{$second->id}")->assertNoContent();

    // Now the survivor is the last one, and nothing can remove them: their
    // own request is refused as a self-delete, and no lesser account may
    // reach them.
    $this->withToken($this->token)->deleteJson("/api/v1/users/{$this->admin->id}")->assertStatus(422);

    expect(User::query()->whereKey($this->admin->id)->exists())->toBeTrue();
});

test('a caller cannot deactivate or delete their own account', function () {
    // A second administrator, so the last-administrator guard is not what
    // refuses these.
    User::factory()->create();

    $this->withToken($this->token)->patchJson("/api/v1/users/{$this->admin->id}", ['active' => false])
        ->assertStatus(422)
        ->assertJsonPath('errors.active.0', 'You cannot deactivate your own account.');

    $this->withToken($this->token)->deleteJson("/api/v1/users/{$this->admin->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.user.0', 'You cannot delete your own account.');
});

/*
|--------------------------------------------------------------------------
| Deletion and its content
|--------------------------------------------------------------------------
*/

test('deleting an account that owns files demands a decision about them', function () {
    $user = User::factory()->role(SystemRole::Uploader)->create();
    File::factory()->create([
        'uploaded_by' => $user->id, 'name' => 'doc', 'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf', 'size' => 10,
    ]);

    $this->withToken($this->token)->deleteJson("/api/v1/users/{$user->id}")
        ->assertStatus(422)
        ->assertJsonPath('type', 'validation_failed');

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    $this->withToken($this->token)->deleteJson("/api/v1/users/{$user->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->admin->id,
    ])->assertNoContent();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(File::query()->where('uploaded_by', $this->admin->id)->exists())->toBeTrue();
});

test('deleting an account with no content needs no body, and is audited', function () {
    $user = User::factory()->role(SystemRole::Uploader)->create(['name' => 'Departing']);

    $this->withToken($this->token)->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();

    $entry = ActivityLog::query()->where('action', Action::UserDeleted)->latest('id')->sole();
    expect($entry->context['name'])->toBe('Departing');
});

/*
|--------------------------------------------------------------------------
| Token abilities
|--------------------------------------------------------------------------
*/

test('each endpoint needs its own ability on top of manage_users', function () {
    $readOnly = $this->admin->createToken('read', [
        Permission::ManageUsers->value,
    ])->plainTextToken;

    $user = User::factory()->role(SystemRole::Uploader)->create();

    // manage_users alone reaches the listing and the roles list...
    $this->withToken($readOnly)->getJson('/api/v1/users')->assertOk();
    $this->withToken($readOnly)->getJson('/api/v1/roles')->assertOk();

    // ...and nothing else.
    $this->withToken($readOnly)->getJson("/api/v1/users/{$user->id}")->assertForbidden();
    $this->withToken($readOnly)->postJson('/api/v1/users', [])->assertForbidden();
    $this->withToken($readOnly)->patchJson("/api/v1/users/{$user->id}", [])->assertForbidden();
    $this->withToken($readOnly)->deleteJson("/api/v1/users/{$user->id}")->assertForbidden();
});

test('a token without manage_users reaches none of it, whatever else it holds', function () {
    $token = $this->admin->createToken('t', [
        Permission::CreateUsers->value,
        Permission::EditUsers->value,
        Permission::DeleteUsers->value,
    ])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
    $this->withToken($token)->getJson('/api/v1/roles')->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/users', [])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Two-factor reset
|--------------------------------------------------------------------------
|
| The API twin of the button on /users/{user}: an account whose
| authenticator app and recovery codes are both gone cannot be opened by
| anybody, including the caller.
|
*/

test('a staff account\'s second factor can be removed', function () {
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);
    forgetRequestState();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/users/{$locked->id}/two-factor")
        ->assertNoContent();

    expect($locked->refresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and($locked->two_factor_secret)->toBeNull();

    $entry = ActivityLog::query()->where('action', Action::TwoFactorReset)->sole();
    expect($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($locked->id);
});

test('removing a second factor needs edit_users, not just manage_users', function () {
    [, $token] = userManager(['manage_users']);
    $locked = User::factory()->role(SystemRole::Uploader)->create();
    enableTwoFactor($locked);
    forgetRequestState();

    $this->withToken($token)
        ->deleteJson("/api/v1/users/{$locked->id}/two-factor")
        ->assertForbidden();

    expect($locked->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a non-administrator cannot strip an administrator\'s second factor', function () {
    [, $token] = userManager(['manage_users', 'edit_users']);
    $admin = User::factory()->create();
    enableTwoFactor($admin);
    forgetRequestState();

    $this->withToken($token)
        ->deleteJson("/api/v1/users/{$admin->id}/two-factor")
        ->assertForbidden();

    expect($admin->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a client is not addressable through the staff two-factor route', function () {
    $client = User::factory()->client()->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/users/{$client->id}/two-factor")
        ->assertNotFound();
});
