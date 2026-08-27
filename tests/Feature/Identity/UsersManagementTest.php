<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use Inertia\Testing\AssertableInertia;

function admin(): User
{
    return User::factory()->create();
}

function staffFile(User $uploader): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => 'doc',
        'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 123,
    ]);
}

test('the index lists staff only — clients never appear', function () {
    $adminUser = admin();
    User::factory()->role(SystemRole::Uploader)->create(['name' => 'Staff Member']);
    User::factory()->client()->create(['name' => 'A Client']);

    $this->actingAs($adminUser)->get('/users')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('users/index')
            ->has('users', 2)
            ->where('users.0.role', fn ($role) => $role !== 'Client'),
    );
});

test('the index flags the acting user\'s own row so the UI can hide self-delete', function () {
    $adminUser = admin();
    $other = User::factory()->role(SystemRole::Uploader)->create(['name' => 'Other Staffer']);

    $this->actingAs($adminUser)->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $adminUser->id)['is_self'] === true
            && collect($users)->firstWhere('id', $other->id)['is_self'] === false));
});

test('an administrator can create a staff user with a role', function () {
    $this->actingAs(admin());

    $staffRole = Role::query()->where('name', 'Account Manager')->sole();

    $response = $this->post('/users', [
        'name' => 'New Staffer',
        'email' => 'staffer@example.com',
        'role_id' => $staffRole->id,
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $created = User::query()->where('email', 'staffer@example.com')->sole();
    $response->assertRedirect(route('users.edit', $created));
    expect($created->isStaff())->toBeTrue()
        ->and($created->role?->name)->toBe('Account Manager')
        ->and(ActivityLog::query()->where('action', Action::UserCreated)->where('subject_name', 'New Staffer')->exists())->toBeTrue();
});

test('the client role cannot be assigned to a staff user', function () {
    $this->actingAs(admin());

    $clientRole = Role::query()->where('name', 'Client')->sole();

    $this->post('/users', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'role_id' => $clientRole->id,
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('role_id');
});

test('editing updates fields and password only when provided', function () {
    $this->actingAs(admin());
    $user = User::factory()->role(SystemRole::Uploader)->create();
    $originalPassword = $user->password;

    $managerRole = Role::query()->where('name', 'Account Manager')->sole();

    $this->patch("/users/{$user->id}", [
        'name' => 'Renamed',
        'email' => $user->email,
        'role_id' => $managerRole->id,
        'active' => true,
        'password' => null,
    ])->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('Renamed')
        ->and($user->role?->name)->toBe('Account Manager')
        ->and($user->password)->toBe($originalPassword);

    $this->patch("/users/{$user->id}", [
        'name' => 'Renamed',
        'email' => $user->email,
        'role_id' => $managerRole->id,
        'active' => true,
        'password' => 'a-changed-password-1',
        'password_confirmation' => 'a-changed-password-1',
    ]);

    expect($user->refresh()->password)->not->toBe($originalPassword);
});

test('client accounts are not reachable through the users screens', function () {
    $this->actingAs(admin());
    $client = User::factory()->client()->create();

    $this->get("/users/{$client->id}")->assertNotFound();
    $this->delete("/users/{$client->id}")->assertNotFound();
});

test('deactivating a user terminates their session and blocks login', function () {
    $adminUser = admin();
    $user = User::factory()->role(SystemRole::Uploader)->create();

    // The user has an active session.
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $this->actingAs($adminUser)->patch("/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $user->role_id,
        'active' => false,
    ])->assertRedirect();

    expect(ActivityLog::query()->where('action', Action::UserDeactivated)->exists())->toBeTrue();

    // Their next request logs them out.
    $this->actingAs($user->refresh())->get('/dashboard')->assertRedirect(route('login'));

    // And the login form refuses them with the deactivation notice.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a wrong password on a deactivated account does not reveal its state', function () {
    admin();
    $user = User::factory()->create(['active' => false]);

    $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong']);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
});

test('you cannot deactivate or delete yourself', function () {
    $adminUser = admin();
    // A second admin so the last-administrator guard is not what trips.
    User::factory()->create();

    $this->actingAs($adminUser);

    $this->patch("/users/{$adminUser->id}", [
        'name' => $adminUser->name,
        'email' => $adminUser->email,
        'role_id' => $adminUser->role_id,
        'active' => false,
    ])->assertSessionHasErrors('active');

    $this->delete("/users/{$adminUser->id}")->assertSessionHasErrors('user');
});

test('the last active administrator cannot be deleted, demoted, or deactivated by another admin path', function () {
    $adminUser = admin();
    $secondAdmin = User::factory()->create();

    $this->actingAs($adminUser);

    // Demoting the other admin is fine while this one remains...
    $staffRole = Role::query()->where('name', 'Account Manager')->sole();
    $this->patch("/users/{$secondAdmin->id}", [
        'name' => $secondAdmin->name,
        'email' => $secondAdmin->email,
        'role_id' => $staffRole->id,
        'active' => true,
    ])->assertSessionDoesntHaveErrors();

    // ...but now this account is the last admin: demoting self is refused.
    $this->patch("/users/{$adminUser->id}", [
        'name' => $adminUser->name,
        'email' => $adminUser->email,
        'role_id' => $staffRole->id,
        'active' => true,
    ])->assertSessionHasErrors('role_id');
});

test('the last active administrator cannot demote themselves, and the index/edit pages flag them for the UI', function () {
    $adminUser = admin();
    $staffRole = Role::query()->create(['name' => 'Plain Staff']);

    $this->actingAs($adminUser);

    // Sole active admin: flagged everywhere, and the server refuses the
    // demotion. Self-demotion is the live path to this guard — only an
    // administrator may touch an administrator now (see the 403 test
    // below), and any *other* admin actor would themselves be a second
    // active admin, which is exactly the condition that clears it.
    $this->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $adminUser->id)['is_last_administrator'] === true));
    $this->get("/users/{$adminUser->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('is_last_administrator', true));

    $this->patch("/users/{$adminUser->id}", [
        'name' => $adminUser->name,
        'email' => $adminUser->email,
        'role_id' => $staffRole->id,
        'active' => true,
    ])->assertSessionHasErrors('role_id');
    expect($adminUser->refresh()->role?->is_administrator)->toBeTrue();

    // A second active admin joins: the flag drops and the demotion lands.
    User::factory()->create();
    $this->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $adminUser->id)['is_last_administrator'] === false));
    $this->get("/users/{$adminUser->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('is_last_administrator', false));

    $this->patch("/users/{$adminUser->id}", [
        'name' => $adminUser->name,
        'email' => $adminUser->email,
        'role_id' => $staffRole->id,
        'active' => true,
    ])->assertSessionHasNoErrors();
    expect($adminUser->refresh()->role_id)->toBe($staffRole->id);
});

// manage_users is a permission like any other; without this it would be a
// back door to every other permission, since a role is just a bundle of
// them and this is the screen that hands roles out.
test('a non-administrator with manage_users cannot reach, alter, or mint an administrator', function () {
    $adminUser = admin();

    $role = Role::query()->create(['name' => 'User Manager']);
    foreach (['manage_users', 'create_users', 'edit_users', 'delete_users'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }
    $manager = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($manager);

    // The administrator's account is out of reach entirely.
    $this->get("/users/{$adminUser->id}")->assertForbidden();
    $this->patch("/users/{$adminUser->id}", [
        'name' => 'Renamed', 'email' => $adminUser->email, 'role_id' => $role->id, 'active' => true,
    ])->assertForbidden();
    $this->delete("/users/{$adminUser->id}")->assertForbidden();
    expect($adminUser->refresh()->name)->not->toBe('Renamed');

    // The administrator role is not offered, and is refused if posted anyway.
    $adminRoleId = Role::query()->where('is_administrator', true)->value('id');
    $this->get('/users/create')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('roles', fn ($roles) => ! collect($roles)->pluck('id')->contains($adminRoleId)));

    $this->post('/users', [
        'name' => 'Backdoor', 'email' => 'backdoor@example.com', 'role_id' => $adminRoleId,
        'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password',
    ])->assertSessionHasErrors('role_id');
    expect(User::query()->where('email', 'backdoor@example.com')->exists())->toBeFalse();

    // Nor can they mint a role carrying permissions they do not hold and
    // route around the check that way.
    $this->post('/roles', ['name' => 'Sneaky', 'permissions' => ['edit_settings']])
        ->assertSessionHasErrors('permissions');
    expect(Role::query()->where('name', 'Sneaky')->exists())->toBeFalse();

    // A role within their own authority is still fine.
    $this->post('/roles', ['name' => 'Fine', 'permissions' => ['edit_users']])
        ->assertSessionHasNoErrors();
    expect(Role::query()->where('name', 'Fine')->exists())->toBeTrue();
});

test('the index exposes each user\'s content counts and a shared reassignment candidate list', function () {
    $adminUser = admin();
    $user = User::factory()->role(SystemRole::Uploader)->create();
    staffFile($user);

    $this->actingAs($adminUser)->get('/users')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('users', fn ($users) => collect($users)->firstWhere('id', $user->id)['content'] === ['files' => 1, 'folders' => 0])
            ->has('reassign_candidates')
            ->where('reassign_candidates', fn ($candidates) => collect($candidates)->pluck('id')->contains($adminUser->id)),
    );
});

test('deleting a staff user that owns content is refused without a choice', function () {
    $adminUser = admin();
    $user = User::factory()->role(SystemRole::Uploader)->create();
    staffFile($user);

    $this->actingAs($adminUser)->delete("/users/{$user->id}")->assertSessionHasErrors('content_action');

    expect(User::query()->find($user->id))->not->toBeNull();
});

test('cascade-deleting a staff user removes their own files and logs a summary', function () {
    $adminUser = admin();
    $user = User::factory()->role(SystemRole::Uploader)->create();
    $file = staffFile($user);
    $folder = Folder::query()->create(['name' => 'Mine', 'created_by' => $user->id]);

    $this->actingAs($adminUser)->delete("/users/{$user->id}", ['content_action' => 'cascade_delete'])
        ->assertRedirect('/users');

    expect(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue()
        ->and(Folder::withTrashed()->findOrFail($folder->id)->trashed())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted)->exists())->toBeTrue();
});

test('reassigning a deleted staff user\'s content transfers ownership and logs a summary', function () {
    $adminUser = admin();
    $user = User::factory()->role(SystemRole::Uploader)->create();
    $target = User::factory()->role(SystemRole::Uploader)->create();
    $file = staffFile($user);

    $this->actingAs($adminUser)->delete("/users/{$user->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $target->id,
    ])->assertRedirect('/users');

    expect($file->refresh()->uploaded_by)->toBe($target->id)
        ->and(ActivityLog::query()->where('action', Action::AccountContentReassigned)->exists())->toBeTrue();
});

test('a failure while disposing of a deleted staff account\'s content rolls the deletion back', function () {
    $adminUser = admin();
    $user = User::factory()->role(SystemRole::Uploader)->create();
    $reassignTarget = User::factory()->role(SystemRole::Uploader)->create();

    failAccountContentDisposal();

    $this->actingAs($adminUser)->delete("/users/{$user->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $reassignTarget->id,
    ])->assertStatus(500);

    // The soft-delete and its log belong to the same transaction as the
    // content step, so a failure there leaves the account intact rather than
    // deleted-but-still-owning-files.
    expect(User::query()->find($user->id))->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::UserDeleted)->exists())->toBeFalse();
});

test('users management requires granular permissions', function () {
    // Account Manager lacks manage_users entirely.
    $this->actingAs(User::factory()->role(SystemRole::AccountManager)->create());
    $this->get('/users')->assertForbidden();
});

test('users management is present in the cloud edition too', function () {
    // It was absent until 2.2.0, on the reasoning that a managed
    // installation's accounts arrived from outside it. A platform
    // provisions how many seats exist; who fills them, and which role each
    // holds, is knowledge the platform does not have.
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs(admin());

    $this->get('/users')->assertOk();
});
