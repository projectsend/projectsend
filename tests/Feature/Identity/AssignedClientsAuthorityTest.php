<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * Assigning a client to a staff account hands over everything that client
 * can see. StaffAccounts already refuses to let anybody grant a role they
 * do not hold; this is the same rule for the client roster.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine']);

    // A client-scoped role that manages staff accounts. Nothing shipped
    // combines the two; the roles screen offers every combination, and
    // ClientScopingTest pins that a custom role can be made scoped.
    $this->role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::ManageUsers, Permission::EditUsers, Permission::CreateUsers, Permission::EditClients] as $permission) {
        RolePermission::query()->create(['role_id' => $this->role->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $this->role->id]);
    $this->rep->assignedClients()->sync([$this->mine->id]);
});

test('a scoped staff member cannot widen their own reach', function () {
    // guardTarget() returns immediately for your own account — editing
    // your own name and email is not a question of authority — so this
    // payload had nothing standing in front of it at all.
    $this->actingAs($this->rep)->patch("/users/{$this->rep->id}", [
        'name' => $this->rep->name,
        'email' => $this->rep->email,
        'role_id' => $this->role->id,
        'active' => true,
        'assigned_clients' => [$this->mine->id, $this->stranger->id],
    ])->assertSessionHasErrors('assigned_clients.1');

    expect($this->rep->assignedClients()->pluck('users.id')->all())->toBe([$this->mine->id]);
});

test('a scoped staff member cannot hand a stranger client to somebody else either', function () {
    $colleague = User::factory()->create(['role_id' => $this->role->id]);

    $this->actingAs($this->rep)->patch("/users/{$colleague->id}", [
        'name' => $colleague->name,
        'email' => $colleague->email,
        'role_id' => $this->role->id,
        'active' => true,
        'assigned_clients' => [$this->stranger->id],
    ])->assertSessionHasErrors('assigned_clients.0');

    expect($colleague->assignedClients()->count())->toBe(0);
});

test('a scoped staff member cannot mint a new account holding clients they do not', function () {
    $this->actingAs($this->rep)->post('/users', [
        'name' => 'New Rep',
        'email' => 'new-rep@example.test',
        'role_id' => $this->role->id,
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
        'assigned_clients' => [$this->stranger->id],
    ])->assertSessionHasErrors('assigned_clients.0');

    expect(User::query()->where('email', 'new-rep@example.test')->exists())->toBeFalse();
});

test('their own clients still pass, on create and on edit', function () {
    // Not deny-everything: handing on what you hold is the point of the
    // roster, and the rule only says you cannot hand on what you do not.
    $this->actingAs($this->rep)->post('/users', [
        'name' => 'New Rep',
        'email' => 'new-rep@example.test',
        'role_id' => $this->role->id,
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
        'assigned_clients' => [$this->mine->id],
    ])->assertSessionHasNoErrors();

    $created = User::query()->where('email', 'new-rep@example.test')->sole();

    expect($created->assignedClients()->pluck('users.id')->all())->toBe([$this->mine->id]);
});

test('an administrator still assigns any client at all', function () {
    $rep = User::factory()->create(['role_id' => $this->role->id]);

    $this->actingAs($this->admin)->patch("/users/{$rep->id}", [
        'name' => $rep->name,
        'email' => $rep->email,
        'role_id' => $this->role->id,
        'active' => true,
        'assigned_clients' => [$this->mine->id, $this->stranger->id],
    ])->assertSessionHasNoErrors();

    expect($rep->assignedClients()->count())->toBe(2);
});

test('a staff account is still not assignable as a client', function () {
    // The type filter the old exists() rule carried is inside the list
    // this now validates against, so it did not go anywhere.
    $staff = User::factory()->create();

    $this->actingAs($this->admin)->patch("/users/{$this->rep->id}", [
        'name' => $this->rep->name,
        'email' => $this->rep->email,
        'role_id' => $this->role->id,
        'active' => true,
        'assigned_clients' => [$staff->id],
    ])->assertSessionHasErrors('assigned_clients.0');

    $this->actingAs($this->admin)->patch("/users/{$this->rep->id}", [
        'name' => $this->rep->name,
        'email' => $this->rep->email,
        'role_id' => $this->role->id,
        'active' => true,
        'assigned_clients' => [999999],
    ])->assertSessionHasErrors('assigned_clients.0');
});

test('the API answers the same on both verbs', function () {
    $token = $this->rep->createToken('t', [
        Permission::ManageUsers->value,
        Permission::EditUsers->value,
        Permission::CreateUsers->value,
    ])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/users/{$this->rep->id}", [
        'assigned_clients' => [$this->mine->id, $this->stranger->id],
    ])->assertJsonValidationErrors('assigned_clients.1');

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'New Rep',
        'email' => 'new-rep@example.test',
        'role_id' => $this->role->id,
        'password' => 'a-strong-password-1',
        'assigned_clients' => [$this->stranger->id],
    ])->assertJsonValidationErrors('assigned_clients.0');

    expect($this->rep->assignedClients()->pluck('users.id')->all())->toBe([$this->mine->id]);

    // Their own client still goes through the same endpoint.
    $this->withToken($token)->patchJson("/api/v1/users/{$this->rep->id}", [
        'assigned_clients' => [$this->mine->id],
    ])->assertOk();
});

test('converting an account to staff cannot hand out clients either', function () {
    $target = User::factory()->client()->create();

    $this->actingAs($this->rep)->post("/users/convert/{$target->id}", [
        'direction' => 'to_staff',
        'role_id' => $this->role->id,
        'assigned_clients' => [$this->stranger->id],
        'password' => 'a-strong-password-1',
    ])->assertSessionHasErrors('assigned_clients.0');

    expect($target->refresh()->isClient())->toBeTrue();
});

test('the picker offers what the rule accepts', function () {
    // A form that offers a client the server will refuse is a form that
    // teaches people the boundary by hitting it — roleOptions() is
    // narrowed for exactly this reason, and now so is this.
    $names = fn ($clients) => collect($clients)->pluck('name')->all();

    $this->actingAs($this->rep)->get('/users/create')->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'clients',
            fn ($clients) => $names($clients) === ['Mine'],
        ),
    );

    $this->actingAs($this->admin)->get('/users/create')->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'clients',
            fn ($clients) => in_array('Not Mine', $names($clients), true),
        ),
    );
});

test('an unscoped role clears the roster as before', function () {
    // syncAssignedClients still decides that, and it is untouched: this
    // change is about which ids may be offered, not about when they stick.
    $plain = Role::query()->where('name', SystemRole::AccountManager->value)->sole();

    $this->actingAs($this->admin)->patch("/users/{$this->rep->id}", [
        'name' => $this->rep->name,
        'email' => $this->rep->email,
        'role_id' => $plain->id,
        'active' => true,
        'assigned_clients' => [$this->mine->id],
    ])->assertSessionHasNoErrors();

    expect($this->rep->refresh()->assignedClients()->count())->toBe(0);
});
