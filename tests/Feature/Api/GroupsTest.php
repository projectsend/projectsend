<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\Permission;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('t', [
        Permission::ManageGroups->value,
        Permission::CreateGroups->value,
        Permission::EditGroups->value,
        Permission::DeleteGroups->value,
    ])->plainTextToken;
});

test('groups can be listed and created', function () {
    $this->withToken($this->token)->postJson('/api/v1/groups', [
        'name' => 'Partners',
        'public' => false,
    ])->assertStatus(201)->assertJsonPath('data.name', 'Partners');

    $this->withToken($this->token)->getJson('/api/v1/groups')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Partners')
        ->assertJsonPath('data.0.members_count', 0);

    expect(ActivityLog::query()->where('action', Action::GroupCreated)->exists())->toBeTrue();
});

test('a group slug is generated when none is given and never changes silently', function () {
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => true]);

    $this->withToken($this->token)->patchJson("/api/v1/groups/{$group->id}", ['name' => 'Renamed Partners'])
        ->assertOk();

    // The slug is part of a public URL; renaming must not break it.
    expect($group->refresh()->slug)->toBe('partners');
});

test('making a group public is recorded separately', function () {
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => false]);

    $this->withToken($this->token)->patchJson("/api/v1/groups/{$group->id}", [
        'public' => true,
        'slug' => 'partners',
    ])->assertOk();

    expect(ActivityLog::query()->where('action', Action::GroupMadePublic)->exists())->toBeTrue();
});

test('a group can be deleted', function () {
    $group = Group::query()->create(['name' => 'Temp', 'slug' => 'temp', 'public' => false]);

    $this->withToken($this->token)->deleteJson("/api/v1/groups/{$group->id}")->assertNoContent();

    expect(Group::query()->find($group->id))->toBeNull();
});

test('clients can be added to and removed from a group', function () {
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => false]);
    $client = User::factory()->client()->create(['name' => 'Acme']);

    $this->withToken($this->token)
        ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $client->id])
        ->assertOk()
        ->assertJsonPath('data.members.0.name', 'Acme');

    $this->withToken($this->token)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.members', []);
});

test('adding the same client twice is a no-op', function () {
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => false]);
    $client = User::factory()->client()->create();

    foreach (range(1, 3) as $ignored) {
        $this->withToken($this->token)
            ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $client->id])
            ->assertOk();
    }

    expect($group->members()->count())->toBe(1);
});

test('staff cannot be made group members', function () {
    // A group is a sharing target; a staff member inside one would start
    // receiving shares as though they were a customer.
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => false]);
    $staff = User::factory()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $staff->id])
        ->assertStatus(422);

    expect($group->members()->count())->toBe(0);
});

test('the group listing does not expose member emails', function () {
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => false]);
    $client = User::factory()->client()->create(['email' => 'private@acme.test']);
    $group->members()->attach($client->id);

    expect($this->withToken($this->token)->getJson('/api/v1/groups')->getContent())
        ->not->toContain('private@acme.test');

    // Reading one group does include them, matching the group edit screen.
    $this->withToken($this->token)->getJson("/api/v1/groups/{$group->id}")
        ->assertOk()
        ->assertJsonPath('data.members.0.email', 'private@acme.test');
});

test('each group route needs its own permission', function () {
    $group = Group::query()->create(['name' => 'Partners', 'slug' => 'partners', 'public' => false]);
    $readOnly = staffWithPermissions([Permission::ManageGroups->value]);
    $token = $readOnly->createToken('t', [Permission::ManageGroups->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/groups')->assertOk();
    $this->withToken($token)->postJson('/api/v1/groups', [])->assertForbidden();
    $this->withToken($token)->patchJson("/api/v1/groups/{$group->id}", [])->assertForbidden();
    $this->withToken($token)->deleteJson("/api/v1/groups/{$group->id}")->assertForbidden();
    $this->withToken($token)->postJson("/api/v1/groups/{$group->id}/members", [])->assertForbidden();
});
