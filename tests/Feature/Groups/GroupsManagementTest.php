<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('a group can be created, listed, and updated', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback) — reset explicitly rather than assuming
    // nothing else in the suite has touched this setting.
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $this->actingAs($this->admin);

    $this->post('/groups', ['name' => 'Studio Clients', 'slug' => 'studio-clients', 'description' => 'The design studio', 'public' => false])
        ->assertRedirect();

    $group = Group::query()->where('name', 'Studio Clients')->sole();
    expect(ActivityLog::query()->where('action', Action::GroupCreated)->where('subject_name', 'Studio Clients')->exists())->toBeTrue();

    $this->patch("/groups/{$group->id}", ['name' => 'Studio Clients', 'slug' => 'studio-clients', 'description' => 'Renamed desc', 'public' => true])
        ->assertRedirect();

    expect($group->refresh()->public)->toBeTrue();

    $this->get('/groups')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('groups/index')
            ->has('groups', 1)
            ->where('groups.0.public', true)
            ->where('groups.0.public_url', route('public.show', ['public', 'studio-clients'])),
    );
});

test('a private group has no public URL in the groups list', function () {
    $this->actingAs($this->admin);
    Group::query()->create(['name' => 'Internal Only', 'public' => false]);

    $this->get('/groups')->assertInertia(
        fn (AssertableInertia $page) => $page->where('groups.0.public_url', null),
    );
});

test('a group\'s slug is required only when public, but always unique', function () {
    $this->actingAs($this->admin);

    $this->post('/groups', ['name' => 'First', 'slug' => 'shared-slug', 'description' => null, 'public' => true])
        ->assertRedirect();

    // Required once the group is public.
    $this->post('/groups', ['name' => 'No Slug', 'description' => null, 'public' => true])
        ->assertSessionHasErrors('slug');

    // Not required for a private group — one is derived from the name.
    $this->post('/groups', ['name' => 'Private No Slug', 'description' => null, 'public' => false])
        ->assertRedirect();
    expect(Group::query()->where('name', 'Private No Slug')->sole()->slug)->toBe('private-no-slug');

    // Uniqueness is enforced regardless of public status.
    $this->post('/groups', ['name' => 'Second', 'slug' => 'shared-slug', 'description' => null, 'public' => true])
        ->assertSessionHasErrors('slug');

    // A group can keep its own slug on update without tripping the
    // uniqueness rule against itself.
    $group = Group::query()->where('name', 'First')->sole();
    $this->patch("/groups/{$group->id}", ['name' => 'First', 'slug' => 'shared-slug', 'description' => null, 'public' => true])
        ->assertRedirect();
});

test('omitting the slug on update leaves the existing one alone, even if the name changes', function () {
    $this->actingAs($this->admin);
    // A private group doesn't require sending a slug — but it already
    // has one (assigned explicitly here, standing in for one it kept
    // from an earlier public stint or the auto-fallback).
    $group = Group::query()->create(['name' => 'Original Name', 'slug' => 'kept-slug', 'public' => false]);

    $this->patch("/groups/{$group->id}", ['name' => 'Renamed', 'description' => null, 'public' => false])
        ->assertRedirect();

    expect($group->refresh()->slug)->toBe('kept-slug')->and($group->name)->toBe('Renamed');
});

test('creating a Group without a slug falls back to one derived from the name', function () {
    $group = Group::query()->create(['name' => 'Marketing Team']);

    expect($group->slug)->toBe('marketing-team');

    $duplicate = Group::query()->create(['name' => 'Marketing Team']);
    expect($duplicate->slug)->toBe('marketing-team-2');
});

test('clients can be added to and removed from a group; staff cannot join', function () {
    $this->actingAs($this->admin);
    $group = Group::query()->create(['name' => 'Members Test']);
    $client = User::factory()->client()->create(['name' => 'Member Client']);
    $staffer = User::factory()->role(SystemRole::Uploader)->create();

    $this->post("/groups/{$group->id}/members", ['user_id' => $client->id])->assertRedirect();
    expect($group->members()->pluck('users.id')->all())->toBe([$client->id]);
    expect(ActivityLog::query()->where('action', Action::GroupMemberAdded)->sole()->context)
        ->toBe(['member' => 'Member Client']);

    // Adding again is idempotent.
    $this->post("/groups/{$group->id}/members", ['user_id' => $client->id])->assertRedirect();
    expect($group->members()->count())->toBe(1);

    // Staff are rejected.
    $this->post("/groups/{$group->id}/members", ['user_id' => $staffer->id])->assertSessionHasErrors('user_id');

    // The edit screen offers only clients not already in the group.
    $other = User::factory()->client()->create();
    $this->get("/groups/{$group->id}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('members', 1)
            ->has('available_clients', 1)
            ->where('available_clients.0.id', $other->id),
    );

    $this->delete("/groups/{$group->id}/members/{$client->id}")->assertRedirect();
    expect($group->members()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', Action::GroupMemberRemoved)->exists())->toBeTrue();
});

test('deleting a group is soft and leaves the clients alone', function () {
    $this->actingAs($this->admin);
    $group = Group::query()->create(['name' => 'Doomed']);
    $client = User::factory()->client()->create();
    $group->members()->attach($client->id);

    $this->delete("/groups/{$group->id}")->assertRedirect(route('groups.index'));

    expect(Group::query()->find($group->id))->toBeNull()
        ->and(Group::withTrashed()->find($group->id))->not->toBeNull()
        ->and(User::query()->find($client->id))->not->toBeNull();
});

test('group log entries link to the group for authorized viewers', function () {
    $this->actingAs($this->admin);
    $this->post('/groups', ['name' => 'Linky', 'slug' => 'linky', 'description' => null, 'public' => false]);
    $group = Group::query()->where('name', 'Linky')->sole();

    $this->get('/activity?action=group.created')->assertInertia(
        fn (AssertableInertia $page) => $page->where('entries.0.subject_url', "/groups/{$group->id}"),
    );
});

test('group permissions are granular and follow v1 defaults', function () {
    // Account Manager: create/edit/delete but no manage_groups (no index).
    $manager = User::factory()->role(SystemRole::AccountManager)->create();
    $this->actingAs($manager);
    $this->get('/groups')->assertForbidden();
    $this->post('/groups', ['name' => 'AM Group', 'slug' => 'am-group', 'description' => null, 'public' => false])->assertRedirect();

    // Uploader: nothing group-related.
    $uploader = User::factory()->role(SystemRole::Uploader)->create();
    $this->actingAs($uploader);
    $this->get('/groups')->assertForbidden();
    $this->post('/groups', ['name' => 'Nope', 'slug' => 'nope', 'description' => null, 'public' => false])->assertForbidden();

    // Clients: sent home from staff URLs.
    $this->actingAs(User::factory()->client()->create());
    $this->get('/groups')->assertRedirect(route('dashboard'));
});
