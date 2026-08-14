<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Platform\Capabilities\Edition;
use Inertia\Testing\AssertableInertia;

/**
 * The one permission this feature adds, from the screen that grants it to
 * the page it gates.
 *
 * Forced to the community edition because the cloud edition gates the
 * whole roles screen behind Capability::UsersManage — which is exactly why
 * commenting itself is governed by settings rather than permission keys
 * (see App\Modules\Comments\CommentAuthors). Without this, the roles half
 * of the feature would be untestable on a cloud-configured install and
 * unverifiable by hand on one.
 */
beforeEach(function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->admin = User::factory()->create();
});

test('the roles screen offers the moderation permission, under Files', function () {
    $this->actingAs($this->admin)->get('/roles/create')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $catalog = collect($page->toArray()['props']['catalog']);
            $files = $catalog->firstWhere('key', 'files');

            expect(collect($files['permissions'])->pluck('key'))->toContain('moderate_comments');
        });
});

test('granting it through the roles screen is what opens the queue', function () {
    $role = Role::query()->create(['name' => 'Moderator', 'is_system' => false, 'is_administrator' => false]);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->get('/comments')->assertForbidden();

    $this->actingAs($this->admin)->patch("/roles/{$role->id}", [
        'name' => 'Moderator',
        'permissions' => ['upload', 'moderate_comments'],
    ])->assertRedirect();

    forgetRequestState();

    $this->actingAs($staff)->get('/comments')->assertOk();
});
