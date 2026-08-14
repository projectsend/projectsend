<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AccountConversion;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function convertRoleId(SystemRole $role): int
{
    return app(EnsureSystemRoles::class)->materialize($role)->id;
}

/**
 * A staff account that holds the user-management permissions but is not an
 * administrator — the only way to exercise the authority guards, since an
 * administrator passes `mayGrant` unconditionally.
 */
function accountManager(): User
{
    $role = Role::query()->create(['name' => 'User Manager '.uniqid()]);

    foreach (['manage_users', 'edit_users', 'edit_clients'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/*
|--------------------------------------------------------------------------
| Edition
|--------------------------------------------------------------------------
*/

test('the tool is absent in the cloud edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs($this->admin)->get('/users/convert')->assertNotFound();
});

// "convert" must be registered before users/{user} or it binds as a route
// key and 404s — the trap routes/web.php already documents for files/orphans.
test('the tool is not swallowed as a user route key', function () {
    $this->actingAs($this->admin)->get('/users/convert')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('users/convert'));
});

/*
|--------------------------------------------------------------------------
| Guards
|--------------------------------------------------------------------------
*/

test('you cannot convert your own account', function () {
    $this->actingAs($this->admin)
        ->post("/users/convert/{$this->admin->id}", ['direction' => 'to_client'])
        ->assertSessionHasErrors('user');

    expect($this->admin->refresh()->isStaff())->toBeTrue();
});

test('one administrator may demote another when there are two', function () {
    $second = User::factory()->create();

    $this->actingAs($this->admin)
        ->post("/users/convert/{$second->id}", ['direction' => 'to_client'])
        ->assertRedirect();

    expect($second->refresh()->isClient())->toBeTrue();
});

// Through the screen this guard cannot fire: demoting the sole active
// administrator needs an actor who is themselves an active administrator
// (guardTarget), which would make two. It is a service-level backstop, so
// it is tested at that level — and what is asserted is the part that would
// otherwise break silently: the message is re-keyed onto `user`, because
// StaffAccounts raises it on `role_id` and this form has no role field.
test('the last-administrator backstop refuses on a key the form renders', function () {
    $soleAdmin = $this->admin;

    // The actor has to clear guardTarget, so they must be an administrator
    // — and must not themselves be an *active* one, or there would be two
    // and the guard would rightly not fire. An inactive administrator is
    // the only shape that reaches it, which is precisely why this is a
    // backstop rather than a path the screen can take.
    $actor = User::factory()->create(['active' => false]);

    try {
        app(AccountConversion::class)->guardToClient($actor, $soleAdmin);
        $this->fail('Expected the last-administrator guard to refuse.');
    } catch (ValidationException $e) {
        expect($e->validator->errors()->has('user'))->toBeTrue()
            ->and($e->validator->errors()->first('user'))
            ->toBe('This is the last active administrator account.')
            ->and($e->validator->errors()->has('role_id'))->toBeFalse();
    }
});

test('a caller cannot convert an account whose role outranks them', function () {
    $manager = accountManager();

    $this->actingAs($manager)
        ->post("/users/convert/{$this->admin->id}", ['direction' => 'to_client'])
        ->assertForbidden();
});

// The guard that actually limits a promotion. guardTarget deliberately
// does not apply here — see AccountConversion::guardToStaff().
test('a caller cannot promote a client into a role they could not grant', function () {
    $manager = accountManager();
    $client = User::factory()->client()->create();

    $this->actingAs($manager)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => $this->admin->role_id,
    ])->assertSessionHasErrors('role_id');

    expect($client->refresh()->isClient())->toBeTrue();
});

// The other half of that: a promotion into a role they *can* grant works,
// even though the Client system role grants `upload` — which the actor
// does not hold. Applying guardTarget to a client target would have
// refused this for no reason.
test('a caller may promote a client into a role they can grant', function () {
    $manager = accountManager();
    $client = User::factory()->client()->create();
    $roleId = convertRoleId(SystemRole::Uploader);

    // Give the manager exactly the Uploader role's keys so it is grantable.
    foreach (app(EnsureSystemRoles::class)->materialize(SystemRole::Uploader)->permissions()->pluck('permission') as $permission) {
        RolePermission::query()->firstOrCreate(['role_id' => $manager->role_id, 'permission' => $permission]);
    }

    forgetRequestState();

    $this->actingAs($manager)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => $roleId,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($client->refresh()->isStaff())->toBeTrue();
});

// An account request is not an account yet; approving one is a deliberate
// decision with its own screen and its own audit entry.
test('a client awaiting approval cannot be promoted', function () {
    $pending = User::factory()->pendingClient()->create();

    $this->actingAs($this->admin)->post("/users/convert/{$pending->id}", [
        'direction' => 'to_staff',
        'role_id' => convertRoleId(SystemRole::Uploader),
    ])->assertSessionHasErrors('user');

    expect($pending->refresh()->isClient())->toBeTrue();
});

test('an account of the wrong type for the direction 404s', function () {
    $client = User::factory()->client()->create();
    $staff = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", ['direction' => 'to_client'])->assertNotFound();
    $this->actingAs($this->admin)->post("/users/convert/{$staff->id}", ['direction' => 'to_staff', 'role_id' => 1])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Staff → client
|--------------------------------------------------------------------------
*/

test('demoting revokes API tokens, clears the client roster and moves the role', function () {
    $staff = User::factory()->role(SystemRole::ClientManager)->create();
    $client = User::factory()->client()->create();
    $staff->assignedClients()->attach($client->id);
    $staff->createToken('integration', ['upload']);

    expect($staff->tokens()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post("/users/convert/{$staff->id}", ['direction' => 'to_client'])
        ->assertRedirect();

    $staff->refresh();

    expect($staff->isClient())->toBeTrue()
        ->and($staff->role?->name)->toBe(SystemRole::Client->value)
        // The one hole per-request type checks cannot close: the API is
        // staff-only, so a live bearer token would keep staff-level access.
        ->and($staff->tokens()->count())->toBe(0)
        ->and(DB::table('staff_client_assignments')->where('staff_id', $staff->id)->count())->toBe(0);
});

test('demoting keeps the files they uploaded', function () {
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    File::factory()->create([
        'uploaded_by' => $staff->id, 'name' => 'doc', 'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf', 'size' => 10,
    ]);

    $this->actingAs($this->admin)->post("/users/convert/{$staff->id}", ['direction' => 'to_client']);

    expect(File::query()->where('uploaded_by', $staff->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Client → staff
|--------------------------------------------------------------------------
*/

test('promoting sets the role and clears who managed them', function () {
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($client->id);

    $roleId = convertRoleId(SystemRole::Uploader);

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => $roleId,
    ])->assertRedirect();

    $client->refresh();

    expect($client->isStaff())->toBeTrue()
        ->and($client->role_id)->toBe($roleId)
        // Leaving this would put a staff account inside another staff
        // member's client scope.
        ->and(DB::table('staff_client_assignments')->where('client_id', $client->id)->count())->toBe(0);
});

test('a promoted client keeps their group memberships, but stops being a share recipient', function () {
    $client = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Acme']);
    $group->members()->attach($client->id);

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => convertRoleId(SystemRole::Uploader),
    ])->assertRedirect();

    // The row survives, so converting back restores the membership...
    expect(DB::table('group_members')->where('user_id', $client->id)->count())->toBe(1);

    // ...but it is genuinely inert meanwhile: Group::members() filters on
    // type, and that relation is what FileSharing expands a group into, so
    // a promoted account stops receiving "shared with you" notifications.
    expect($group->fresh()->members)->toHaveCount(0);
});

test('a round trip restores the account', function () {
    $client = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Acme']);
    $group->members()->attach($client->id);

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => convertRoleId(SystemRole::Uploader),
    ]);

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", ['direction' => 'to_client']);

    $client->refresh();

    expect($client->isClient())->toBeTrue()
        ->and($client->role?->name)->toBe(SystemRole::Client->value)
        ->and($group->fresh()->members->pluck('id')->all())->toBe([$client->id]);
});

test('promoting can set a new password', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => convertRoleId(SystemRole::Uploader),
        'password' => 'correct-horse-battery-staple',
    ])->assertRedirect();

    expect(Hash::check('correct-horse-battery-staple', $client->refresh()->password))->toBeTrue();
});

/**
 * A client whose credential lives in the directory: LdapProvisioner gives
 * them a random password nobody has ever seen, because the directory is
 * what signs them in.
 */
function ldapClient(): User
{
    $client = User::factory()->client()->create();

    $client->forceFill([
        'auth_source' => AuthSource::Ldap,
        'ldap_dn' => 'uid=jordan,ou=people,dc=example,dc=test',
        'ldap_synced_at' => now(),
    ])->save();

    return $client;
}

test('promoting a directory account without a password is refused', function () {
    $client = ldapClient();

    $this->actingAs($this->admin)
        ->post("/users/convert/{$client->id}", [
            'direction' => 'to_staff',
            'role_id' => convertRoleId(SystemRole::Uploader),
        ])
        ->assertInvalid('password');

    // The refusal has to leave them a client. Half-converting would produce
    // exactly the locked-out staff account the rule exists to prevent.
    expect($client->refresh()->isClient())->toBeTrue()
        ->and($client->auth_source)->toBe(AuthSource::Ldap);
});

test('promoting a directory account with a password moves it to local sign-in', function () {
    $client = ldapClient();

    $this->actingAs($this->admin)
        ->post("/users/convert/{$client->id}", [
            'direction' => 'to_staff',
            'role_id' => convertRoleId(SystemRole::Uploader),
            'password' => 'correct-horse-battery-staple',
        ])
        ->assertRedirect();

    $client->refresh();

    expect($client->isStaff())->toBeTrue()
        ->and(Hash::check('correct-horse-battery-staple', $client->password))->toBeTrue()
        ->and($client->auth_source)->toBe(AuthSource::Local)
        // Kept as the record of where the account came from — a demotion
        // sends them back to the directory.
        ->and($client->ldap_dn)->toBe('uid=jordan,ou=people,dc=example,dc=test');
});

test('the service refuses a passwordless directory promotion, not just the form', function () {
    $client = ldapClient();

    expect(fn () => app(AccountConversion::class)->toStaff($client, convertRoleId(SystemRole::Uploader)))
        ->toThrow(ValidationException::class);

    expect($client->refresh()->isClient())->toBeTrue();
});

test('a local client is still promoted without setting a password', function () {
    $client = User::factory()->client()->create();
    $original = $client->password;

    $this->actingAs($this->admin)
        ->post("/users/convert/{$client->id}", [
            'direction' => 'to_staff',
            'role_id' => convertRoleId(SystemRole::Uploader),
        ])
        ->assertRedirect();

    $client->refresh();

    expect($client->isStaff())->toBeTrue()
        ->and($client->password)->toBe($original);
});

/*
|--------------------------------------------------------------------------
| Audit and access
|--------------------------------------------------------------------------
*/

test('both directions are audited with their own action', function () {
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post("/users/convert/{$staff->id}", ['direction' => 'to_client']);
    $this->actingAs($this->admin)->post("/users/convert/{$client->id}", [
        'direction' => 'to_staff',
        'role_id' => convertRoleId(SystemRole::Uploader),
    ]);

    expect(ActivityLog::query()->where('action', Action::AccountConvertedToClient)->where('subject_id', $staff->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::AccountConvertedToStaff)->where('subject_id', $client->id)->exists())->toBeTrue();
});

// The conversion takes effect on the demoted account's very next request:
// type is re-read from the database every time, so no session surgery is
// needed for the web surfaces.
test('a demoted staff member is bounced out of the staff area on their next request', function () {
    $staff = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($this->admin)->post("/users/convert/{$staff->id}", ['direction' => 'to_client']);

    forgetRequestState();

    $this->actingAs($staff->refresh())->get('/files')->assertRedirect(route('dashboard'));
});

test('the tool requires manage_users, and the mutation also requires edit_clients', function () {
    $withoutEditClients = User::factory()->create([
        'role_id' => Role::query()->create(['name' => 'Half '.uniqid()])->id,
    ]);

    foreach (['manage_users', 'edit_users'] as $permission) {
        RolePermission::query()->create(['role_id' => $withoutEditClients->role_id, 'permission' => $permission]);
    }

    $staff = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($withoutEditClients)->get('/users/convert')->assertOk();
    $this->actingAs($withoutEditClients)
        ->post("/users/convert/{$staff->id}", ['direction' => 'to_client'])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Accounts an identity provider created
|--------------------------------------------------------------------------
|
| Same rule as a directory account, different reason. A provider-created
| account holds a Str::password(64) nobody has ever seen, and a staff
| account must carry a credential this application can check on its own —
| or an administrator is one provider outage away from being locked out.
|
*/

test('promoting a provider-created client without a password is refused', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['auth_source' => AuthSource::Social])->save();

    $this->actingAs($this->admin)
        ->post("/users/convert/{$client->id}", [
            'direction' => 'to_staff',
            'role_id' => convertRoleId(SystemRole::Uploader),
        ])
        ->assertInvalid('password');

    expect($client->refresh()->isClient())->toBeTrue()
        ->and($client->auth_source)->toBe(AuthSource::Social);
});

test('promoting a provider-created client with a password moves it to local sign-in', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['auth_source' => AuthSource::Social])->save();

    $this->actingAs($this->admin)
        ->post("/users/convert/{$client->id}", [
            'direction' => 'to_staff',
            'role_id' => convertRoleId(SystemRole::Uploader),
            'password' => 'correct-horse-battery-staple',
        ])
        ->assertRedirect();

    $client->refresh();

    expect($client->isStaff())->toBeTrue()
        ->and($client->auth_source)->toBe(AuthSource::Local)
        ->and(Hash::check('correct-horse-battery-staple', $client->password))->toBeTrue();
});
