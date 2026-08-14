<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);
});

test('the screen lists every comment, held ones first', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id, 'body' => 'Already live']);
    FileComment::factory()->for($file)->fromGuest('A visitor')->pending()->create(['body' => 'Waiting']);

    $this->actingAs($this->admin)->get('/comments')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('comments/index')
                // Both, not only the held one — the screen this replaced
                // could not find a comment nobody had to decide about.
                ->count('entries', 2)
                // Held first whatever the date, since that is the row with
                // a decision waiting on it.
                ->where('entries.0.body', 'Waiting')
                ->where('entries.0.author_name', 'A visitor')
                ->where('entries.0.pending', true)
                // The one handle that makes repeat spam actionable.
                ->where('entries.0.ip_address', '203.0.113.10')
                ->where('entries.1.body', 'Already live')
                ->where('entries.1.pending', false)
                ->where('pending_total', 1)
        );
});

test('approving publishes the comment and announces it', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $comment = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    $this->actingAs($this->admin)->post("/comments/{$comment->id}/approve")->assertRedirect();

    expect($comment->fresh()->isPending())->toBeFalse();

    // Now a visitor can see it.
    $this->settings->set(Setting::PublicListingSlug, 'public');
    expect(InAppNotification::query()->where('type', 'file_comment.posted')->exists())->toBeTrue();
});

test('deleting from the screen is a soft delete', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $comment = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    $this->actingAs($this->admin)->delete("/comments/{$comment->id}/moderate")->assertRedirect();

    expect(FileComment::query()->count())->toBe(0)
        ->and(FileComment::withTrashed()->count())->toBe(1);
});

test('a staff member without the permission cannot reach the screen', function () {
    $staff = staffWithPermissions(['upload']);

    $this->actingAs($staff)->get('/comments')->assertForbidden();
});

test('clients cannot reach the screen', function () {
    $this->actingAs(User::factory()->client()->create())->get('/comments')->assertRedirect(route('dashboard'));
});

test('moderation rights are not a way around the library boundary', function () {
    $stranger = User::factory()->client()->create();
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $stranger);
    $comment = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    // Holds moderate_comments, but is scoped to a client this file has
    // nothing to do with — so this file is not theirs to act on.
    $role = Role::query()->create(['name' => 'Scoped moderator', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'moderate_comments'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $manager = User::factory()->create(['role_id' => $role->id]);
    $manager->assignedClients()->attach(User::factory()->client()->create()->id);

    $this->actingAs($manager)->get('/comments')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->count('entries', 0));

    $this->actingAs($manager)->post("/comments/{$comment->id}/approve")->assertForbidden();
});

test('a client manager sees pending comments on their own clients\' files', function () {
    $mine = User::factory()->client()->create();
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $mine);
    FileComment::factory()->for($file)->fromGuest()->pending()->create();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($mine->id);
    RolePermission::query()->create([
        'role_id' => $manager->role_id,
        'permission' => 'moderate_comments',
    ]);

    $this->actingAs($manager)->get('/comments')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->count('entries', 1));
});

test('a held comment can be approved from the panel it is seen in', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $comment = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    // The payload the details panel renders says the action is available.
    expect($this->actingAs($this->admin)->getJson("/files/{$file->id}/comments")->json('comments.0.can_approve'))
        ->toBeTrue();

    // Same route the queue posts to; a JSON request gets the thread back
    // rather than a redirect, so the panel can re-render in place.
    $this->actingAs($this->admin)->postJson("/comments/{$comment->id}/approve")
        ->assertOk()
        ->assertJsonPath('comments.0.pending', false)
        ->assertJsonPath('comments.0.can_approve', false);

    expect($comment->fresh()->isPending())->toBeFalse();
});

test('somebody who cannot moderate is never offered the approve action', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);

    $staff = staffWithPermissions(['upload', 'edit_others_files']);

    expect($this->actingAs($staff)->getJson("/files/{$file->id}/comments")->json('comments.0.can_approve'))
        ->toBeFalse();
});
