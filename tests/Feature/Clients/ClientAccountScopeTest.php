<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Managing a client account is inside the same library boundary as
 * everything else about that client — the one clients/{client}/files
 * already applies under the very same permission.
 */
beforeEach(function () {
    Storage::fake('files');

    // A client-scoped role that manages clients. Nothing shipped combines
    // the two, but the roles screen offers every combination and
    // ClientScopingTest pins that a custom role can be made scoped.
    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::ManageClients, Permission::EditClients, Permission::DeleteClients] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine', 'email' => 'stranger@example.test']);

    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->rep->assignedClients()->sync([$this->mine->id]);

    $this->token = $this->rep->createToken('t', [
        Permission::ManageClients->value,
        Permission::EditClients->value,
        Permission::DeleteClients->value,
    ])->plainTextToken;
});

test('the neighbouring route already draws this line', function () {
    // clients/{client}/files, same permission, same bound object. The
    // routes below used to answer where this one refuses.
    $this->actingAs($this->rep)->get("/clients/{$this->mine->id}/files")->assertOk();
    $this->actingAs($this->rep)->get("/clients/{$this->stranger->id}/files")->assertNotFound();
});

test('a scoped rep cannot open or edit a client that is not theirs', function () {
    $this->actingAs($this->rep)->get("/clients/{$this->stranger->id}")->assertNotFound();

    $this->actingAs($this->rep)->patch("/clients/{$this->stranger->id}", [
        'name' => 'Taken Over',
        'email' => 'attacker@example.test',
        'active' => true,
        'password' => 'a-new-password-123',
        'password_confirmation' => 'a-new-password-123',
    ])->assertNotFound();

    $this->stranger->refresh();

    // The takeover this closes: email and password are editable here, so
    // reaching a client outside the boundary means signing in as them and
    // reading their whole library.
    expect($this->stranger->email)->toBe('stranger@example.test')
        ->and($this->stranger->name)->toBe('Not Mine');
});

test('a scoped rep cannot delete a client that is not theirs, or reset their second factor', function () {
    $this->actingAs($this->rep)->delete("/clients/{$this->stranger->id}", ['content_action' => 'keep'])
        ->assertNotFound();

    expect(User::query()->whereKey($this->stranger->id)->exists())->toBeTrue();

    enableTwoFactor($this->stranger);
    forgetRequestState();
    confirmPassword($this->rep);

    $this->actingAs($this->rep)->delete("/clients/{$this->stranger->id}/two-factor")->assertNotFound();

    expect($this->stranger->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('their own clients are still entirely theirs to manage', function () {
    // Not deny-everything: the whole point of the role is that these work.
    $this->actingAs($this->rep)->get("/clients/{$this->mine->id}")->assertOk();

    $this->actingAs($this->rep)->patch("/clients/{$this->mine->id}", [
        'name' => 'Renamed',
        'email' => $this->mine->email,
        'active' => true,
    ])->assertRedirect();

    expect($this->mine->refresh()->name)->toBe('Renamed');
});

test('an unscoped staff member reaches every client exactly as before', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->get("/clients/{$this->stranger->id}")->assertOk();

    $this->actingAs($admin)->patch("/clients/{$this->stranger->id}", [
        'name' => 'Renamed By Admin',
        'email' => $this->stranger->email,
        'active' => true,
    ])->assertRedirect();

    expect($this->stranger->refresh()->name)->toBe('Renamed By Admin');
});

test('a staff account still 404s on these routes, scoped or not', function () {
    // The type filter the scope check absorbed still filters.
    $staff = User::factory()->create();

    $this->actingAs($this->rep)->get("/clients/{$staff->id}")->assertNotFound();
    $this->actingAs(User::factory()->create())->get("/clients/{$staff->id}")->assertNotFound();
});

test('a scoped token reaches exactly the clients its owner does', function () {
    $this->withToken($this->token)->getJson("/api/v1/clients/{$this->stranger->id}")->assertNotFound();

    $this->withToken($this->token)->patchJson("/api/v1/clients/{$this->stranger->id}", [
        'email' => 'attacker@example.test',
        'password' => 'a-new-password-123',
    ])->assertNotFound();

    $this->withToken($this->token)->deleteJson("/api/v1/clients/{$this->stranger->id}", ['content_action' => 'keep'])
        ->assertNotFound();

    expect($this->stranger->refresh()->email)->toBe('stranger@example.test');

    // And their own client, over the same token, still answers.
    $this->withToken($this->token)->getJson("/api/v1/clients/{$this->mine->id}")->assertOk();
});

test('a scoped token cannot reset an out-of-reach client two-factor secret', function () {
    enableTwoFactor($this->stranger);
    forgetRequestState();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/clients/{$this->stranger->id}/two-factor")
        ->assertNotFound();

    expect($this->stranger->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('the roster listing is deliberately left alone', function () {
    // manage_clients, not edit_clients: the list is the one client
    // surface this change does not narrow, because nothing in the code
    // claims it is narrowed — see the note in the pull request.
    $names = collect($this->withToken($this->token)->getJson('/api/v1/clients')->assertOk()->json('data'))
        ->pluck('name');

    expect($names)->toContain('Mine', 'Not Mine');
});
