<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;

/**
 * Deleting your own account goes through ProfileController, which asked
 * for the current password and nothing else. Every other door that can
 * remove an administrator asks StaffAccounts::guardLastAdministrator
 * first; this one did not, and it is the one door where the account being
 * removed is certainly signed in.
 *
 * The account is soft-deleted, so the row survives — but every "is this
 * installation set up" check asked `exists()`, which does not see trashed
 * rows. Zero live staff sends every request to the first-run setup form,
 * and that form is registered without `guest`, without auth and without a
 * throttle.
 */
function liveStaffCount(): int
{
    return User::query()->where('type', UserType::Staff)->count();
}

function onlyStaffAccount(): User
{
    User::query()->where('type', UserType::Staff)->forceDelete();

    return User::factory()->create();
}

test('the last administrator cannot delete their own account', function () {
    $admin = onlyStaffAccount();

    $this->actingAs($admin)
        ->from('/settings/delete-account')
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasErrors('role_id');

    expect(liveStaffCount())->toBe(1)
        ->and($admin->fresh())->not->toBeNull();
});

test('an administrator with a colleague still may', function () {
    $admin = onlyStaffAccount();
    $second = User::factory()->create();

    $this->actingAs($admin)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect(liveStaffCount())->toBe(1)
        ->and(User::query()->whereKey($second->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($admin->id)->exists())->toBeFalse();
});

test('a staff member who is not an administrator still may', function () {
    onlyStaffAccount();
    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($uploader)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->whereKey($uploader->id)->exists())->toBeFalse();
});

test('a client can still close their own account', function () {
    onlyStaffAccount();
    $client = User::factory()->client()->create();

    $this->actingAs($client)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->whereKey($client->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The second lock: a trashed staff row still means "already set up"
|--------------------------------------------------------------------------
*/

test('setup stays shut once a staff account has existed, even trashed', function () {
    $admin = onlyStaffAccount();

    // Reached past the guard on purpose — the point of this half is that
    // the window stays closed even if some future door forgets to ask.
    $admin->delete();

    expect(liveStaffCount())->toBe(0)
        ->and(User::query()->withTrashed()->where('type', UserType::Staff)->count())->toBe(1);

    $this->get('/')->assertRedirect('/login');

    $this->post('/setup', [
        'site_name' => 'Taken Over',
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'Str0ng-Passw0rd!x',
        'password_confirmation' => 'Str0ng-Passw0rd!x',
    ])->assertRedirect(route('home'));

    expect(User::query()->where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('a genuinely fresh installation still reaches setup', function () {
    User::query()->withTrashed()->forceDelete();

    expect(User::query()->withTrashed()->count())->toBe(0);

    $this->get('/')->assertRedirect(route('setup'));

    $this->post('/setup', [
        'site_name' => 'Fresh',
        'name' => 'First Administrator',
        'email' => 'first@example.com',
        'password' => 'Str0ng-Passw0rd!x',
        'password_confirmation' => 'Str0ng-Passw0rd!x',
    ])->assertRedirect(route('setup.success'));

    $created = User::query()->where('email', 'first@example.com')->sole();
    $administrator = Role::query()->where('name', SystemRole::SystemAdministrator->value)->sole();

    expect($created->type)->toBe(UserType::Staff)
        ->and($created->role_id)->toBe($administrator->id);
});
