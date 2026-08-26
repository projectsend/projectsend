<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);
    $this->settings->set(Setting::CommentsEditWindowMinutes, 15);
});

/**
 * A moderator who is scoped to their own clients — the role that holds
 * moderate_comments without holding the whole library.
 */
function commentModeratorScopedTo(?User $client = null): User
{
    $role = Role::query()->create(['name' => 'Scoped moderator', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'moderate_comments'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);

    $manager = User::factory()->create(['role_id' => $role->id]);
    $manager->assignedClients()->attach(($client ?? User::factory()->client()->create())->id);

    return $manager;
}

/**
 * A commented-on file belonging to somebody else's client.
 *
 * @return array{0: File, 1: FileComment}
 */
function strangersCommentedFile(User $uploader): array
{
    $stranger = User::factory()->client()->create();
    $file = File::factory()->public()->create(['uploaded_by' => $uploader->id]);
    shareFileWith($file, $stranger);

    return [$file, FileComment::factory()->for($file)->fromGuest()->pending()->create()];
}

test('a scoped moderator cannot delete a comment on a file outside their library', function () {
    [, $comment] = strangersCommentedFile($this->admin);

    // The per-file endpoint, not the moderation screen: the policy is the
    // whole gate here, since the route carries no permission of its own.
    $this->actingAs(commentModeratorScopedTo())
        ->deleteJson("/comments/{$comment->id}")
        ->assertForbidden();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('a scoped moderator cannot delete an out-of-library comment through the API', function () {
    [, $comment] = strangersCommentedFile($this->admin);

    Sanctum::actingAs(commentModeratorScopedTo(), ['upload']);

    $this->deleteJson("/api/v1/comments/{$comment->id}")->assertForbidden();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('a scoped moderator cannot delete an out-of-library comment from the moderation screen', function () {
    [, $comment] = strangersCommentedFile($this->admin);

    $this->actingAs(commentModeratorScopedTo())
        ->delete("/comments/{$comment->id}/moderate")
        ->assertForbidden();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('a scoped moderator cannot approve an out-of-library comment through the API', function () {
    [, $comment] = strangersCommentedFile($this->admin);

    Sanctum::actingAs(commentModeratorScopedTo(), ['moderate_comments']);

    $this->postJson("/api/v1/comments/{$comment->id}/approve")->assertForbidden();

    expect($comment->fresh()->isPending())->toBeTrue();
});

test('a scoped moderator still moderates the files that are theirs', function () {
    $mine = User::factory()->client()->create();
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $mine);
    $comment = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    // Same role, same permission — the only difference is that this file
    // belongs to a client they are assigned to.
    $this->actingAs(commentModeratorScopedTo($mine))
        ->deleteJson("/comments/{$comment->id}")
        ->assertOk();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});

test('an unscoped moderator still reaches every comment', function () {
    [, $comment] = strangersCommentedFile($this->admin);

    $this->actingAs($this->admin)->deleteJson("/comments/{$comment->id}")->assertOk();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});

test('the library boundary does not follow an author onto their own comment', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $client);
    $comment = FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id]);

    // Deleting your own words inside the edit window is not moderation, and
    // the boundary the moderation branch now carries must not reach it.
    $this->actingAs($client)->deleteJson("/comments/{$comment->id}")->assertOk();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});
