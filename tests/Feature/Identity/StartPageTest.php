<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\StartPage;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    // The main administrator. Also what EnsureSetupIsComplete needs.
    $this->admin = User::factory()->create();

    // A waiting greeting sends everyone to the dashboard, and Settings
    // survive RefreshDatabase's rollback in the cache — so state both.
    app(Settings::class)->set(Setting::GettingStartedPending, false);
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '');
});

/**
 * @param  list<Permission>  $permissions
 */
function staffStartingOn(array $permissions, ?string $startPage = null): User
{
    $role = Role::query()->create(['name' => 'Role '.uniqid(), 'start_page' => $startPage]);

    if ($permissions !== []) {
        RolePermission::query()->insert(array_map(
            fn (Permission $p): array => ['role_id' => $role->id, 'permission' => $p->value],
            $permissions,
        ));
    }

    return User::factory()->create(['role_id' => $role->id]);
}

function startPageClientRole(): Role
{
    return Role::query()->where('name', SystemRole::Client->value)->sole();
}

/*
|--------------------------------------------------------------------------
| The vocabulary agrees with the routes
|--------------------------------------------------------------------------
|
| requiredPermission() is a second statement of what each route's
| middleware asks. If the two drift, somebody is sent to a 403 after
| signing in — so this opens every page for real, with and without the
| permission, instead of trusting the enum.
|
*/

test('every staff start page opens for staff holding its permission, and not for staff without it', function () {
    foreach (StartPage::optionsFor(UserType::Staff) as $page) {
        $required = $page->requiredPermission(UserType::Staff);
        $path = route($page->routeName(UserType::Staff), absolute: false);

        $holder = staffStartingOn($required === null ? [] : [$required]);
        expect($page->isReachableBy($holder))->toBeTrue();
        $this->actingAs($holder)->get($path)->assertOk();

        if ($required !== null) {
            $without = staffStartingOn([]);
            expect($page->isReachableBy($without))->toBeFalse();
            expect($this->actingAs($without)->get($path)->status())->not->toBe(200, "{$page->value} opened without {$required->value}");
        }
    }
});

test('every client start page opens for a client holding its permission, and not for one without it', function () {
    foreach (StartPage::optionsFor(UserType::Client) as $page) {
        $required = $page->requiredPermission(UserType::Client);
        $path = route($page->routeName(UserType::Client), absolute: false);

        RolePermission::query()->where('role_id', startPageClientRole()->id)->delete();
        if ($required !== null) {
            RolePermission::query()->insert(['role_id' => startPageClientRole()->id, 'permission' => $required->value]);
        }
        forgetRequestState();

        $holder = User::factory()->client()->create();
        expect($page->isReachableBy($holder))->toBeTrue();
        $this->actingAs($holder)->get($path)->assertOk();

        if ($required !== null) {
            RolePermission::query()->where('role_id', startPageClientRole()->id)->delete();
            forgetRequestState();
            $without = User::factory()->client()->create();
            expect($page->isReachableBy($without))->toBeFalse();
            expect($this->actingAs($without)->get($path)->status())->not->toBe(200, "{$page->value} opened without {$required->value}");
        }
    }
});

test('a client is never offered a staff-only page', function () {
    $values = array_map(fn (StartPage $p) => $p->value, StartPage::optionsFor(UserType::Client));

    expect($values)->not->toContain('clients')->not->toContain('activity');
});

/*
|--------------------------------------------------------------------------
| Where a sign-in lands
|--------------------------------------------------------------------------
*/

test('signing in lands on the role\'s start page', function () {
    $user = staffStartingOn([Permission::ManageClients], startPage: 'clients');

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/clients');
});

test('a personal choice beats the role\'s', function () {
    $user = staffStartingOn([Permission::ManageClients, Permission::ViewActionsLog], startPage: 'clients');
    $user->update(['start_page' => 'activity']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/activity');
});

test('an explicit personal Dashboard beats a role default', function () {
    $user = staffStartingOn([Permission::ManageClients], startPage: 'clients');
    $user->update(['start_page' => 'dashboard']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('a choice the account can no longer open falls back to the role, then to the dashboard', function () {
    // Saved while they could open it; the permission went away later.
    $user = staffStartingOn([Permission::ManageClients], startPage: 'clients');
    $user->update(['start_page' => 'activity']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/clients');

    Auth::logout();
    RolePermission::query()->where('role_id', $user->role_id)->delete();
    forgetRequestState();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('a value no version offers any more falls back to the dashboard instead of failing', function () {
    $user = staffStartingOn([], startPage: 'something-removed');

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('a page somebody was trying to reach still wins over the start page', function () {
    $user = staffStartingOn([Permission::ManageClients, Permission::ViewActionsLog], startPage: 'clients');

    $this->get('/activity')->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/activity');
});

test('a waiting greeting sends the administrator to the dashboard first', function () {
    Role::query()->whereKey($this->admin->role_id)->update(['start_page' => 'clients']);
    app(Settings::class)->set(Setting::GettingStartedPending, true);

    $this->post('/login', ['email' => $this->admin->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('a client lands on their role\'s start page', function () {
    startPageClientRole()->update(['start_page' => 'files']);
    $client = User::factory()->client()->create();

    $this->post('/login', ['email' => $client->email, 'password' => 'password'])
        ->assertRedirect('/my-files');
});

test('the site root sends a signed-in account to its start page', function () {
    $user = staffStartingOn([Permission::ManageClients], startPage: 'clients');

    $this->actingAs($user)->get('/')->assertRedirect('/clients');
});

test('finishing a two-factor challenge lands on the start page', function () {
    $user = staffStartingOn([Permission::ManageClients], startPage: 'clients');
    enableTwoFactor($user);

    Auth::logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->refresh()->two_factor_secret);

    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/clients');
});

/*
|--------------------------------------------------------------------------
| Role screens
|--------------------------------------------------------------------------
*/

test('a role cannot start on a page its own permissions keep it out of', function () {
    $this->actingAs($this->admin)->post('/roles', [
        'name' => 'No clients',
        'permissions' => [Permission::Upload->value],
        'start_page' => 'clients',
    ])->assertSessionHasErrors('start_page');

    expect(Role::query()->where('name', 'No clients')->exists())->toBeFalse();
});

test('granting the permission and choosing the page is one save', function () {
    $this->actingAs($this->admin)->post('/roles', [
        'name' => 'Client desk',
        'permissions' => [Permission::ManageClients->value],
        'start_page' => 'clients',
    ])->assertSessionHasNoErrors();

    expect(Role::query()->where('name', 'Client desk')->value('start_page'))->toBe('clients');
});

test('a built-in role keeps its name but takes a start page', function () {
    $manager = Role::query()->where('name', SystemRole::AccountManager->value)->sole();

    $this->actingAs($this->admin)->patch("/roles/{$manager->id}", [
        'name' => $manager->name,
        'permissions' => $manager->permissions()->pluck('permission')->all(),
        'start_page' => 'activity',
    ])->assertSessionHasNoErrors();

    expect($manager->refresh()->start_page)->toBe('activity');
});

test('the Client role cannot be given a staff-only page', function () {
    $role = startPageClientRole();

    $this->actingAs($this->admin)->patch("/roles/{$role->id}", [
        'name' => $role->name,
        'permissions' => $role->permissions()->pluck('permission')->all(),
        'start_page' => 'activity',
    ])->assertSessionHasErrors('start_page');
});

test('the administrator role takes a start page and nothing else', function () {
    $adminRole = Role::query()->where('is_administrator', true)->sole();

    $this->actingAs($this->admin)->patch("/roles/{$adminRole->id}", ['start_page' => 'files'])
        ->assertSessionHasNoErrors();

    expect($adminRole->refresh()->start_page)->toBe('files');

    $this->actingAs($this->admin)->patch("/roles/{$adminRole->id}", [
        'start_page' => 'clients',
        'permissions' => [],
    ])->assertSessionHasErrors('permissions');

    expect($adminRole->refresh()->start_page)->toBe('files')
        ->and(RolePermission::query()->where('role_id', $adminRole->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

test('a person can choose their own start page, and clear it to follow their role', function () {
    $user = staffStartingOn([Permission::ManageClients]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'start_page' => 'clients',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->start_page)->toBe('clients');

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'start_page' => '',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->start_page)->toBeNull();
});

test('a person cannot choose a page they cannot open', function () {
    $user = staffStartingOn([]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'start_page' => 'clients',
    ])->assertSessionHasErrors('start_page');

    $client = User::factory()->client()->create();

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name,
        'email' => $client->email,
        'start_page' => 'activity',
    ])->assertSessionHasErrors('start_page');
});
