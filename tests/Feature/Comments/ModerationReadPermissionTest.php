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

/*
|--------------------------------------------------------------------------
| Moderating is not a way to read
|--------------------------------------------------------------------------
|
| FilePolicy::view() has two halves for staff: one of the three file keys
| (upload / edit_files / edit_others_files), AND the library scope. The
| comment surfaces narrowed by the library half alone, so a role holding
| moderate_comments and nothing else got a 403 on every file while reading
| every comment written about them — text, staff-only notes, the client
| name in `conversation`, and a visitor's IP.
|
*/

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);
});

/** A moderator holding moderate_comments and no file key whatsoever. */
function moderatorWithoutFileKeys(): User
{
    $role = Role::query()->create(['name' => 'Comment moderator']);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'moderate_comments'],
    ]);

    return User::factory()->create(['role_id' => $role->id]);
}

/** A moderator who may also read files — the shipped Account Manager shape. */
function moderatorWithFileKeys(): User
{
    $role = Role::query()->create(['name' => 'Account manager shape']);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'moderate_comments'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);

    return User::factory()->create(['role_id' => $role->id]);
}

/** @return array{0: File, 1: FileComment} */
function commentedFile(User $uploader): array
{
    $file = File::factory()->public()->create(['uploaded_by' => $uploader->id, 'name' => 'merger-terms']);

    return [$file, FileComment::factory()->for($file)->fromGuest()->pending()->create()];
}

test('a moderator who may read no file is refused the file itself', function () {
    // The premise the rest of this file rests on, measured rather than
    // assumed: the door next to the comment is shut for this viewer.
    [$file] = commentedFile($this->admin);

    $this->actingAs(moderatorWithoutFileKeys())
        ->get("/files/{$file->id}/download")
        ->assertForbidden();
});

test('the moderation screen shows no comment about a file the viewer cannot open', function () {
    [, $comment] = commentedFile($this->admin);

    $props = $this->actingAs(moderatorWithoutFileKeys())
        ->get('/comments')->assertOk()->viewData('page')['props'];

    expect(collect($props['entries'])->pluck('id')->all())->not->toContain($comment->id)
        ->and($props['entries'])->toBe([]);
});

test('the pending badge counts nothing a viewer may not open', function () {
    commentedFile($this->admin);

    $props = $this->actingAs(moderatorWithoutFileKeys())
        ->get('/comments')->assertOk()->viewData('page')['props'];

    expect($props['pending_total'])->toBe(0)
        ->and($props['pending']['comments'] ?? 0)->toBe(0);
});

test('the API queue refuses a moderator without a file key', function () {
    // The class form of the policy answers "does this user moderate at
    // all", and without a file key the answer is no — so the queue is
    // refused rather than returned empty, exactly as it is for somebody
    // holding no moderation permission.
    commentedFile($this->admin);

    Sanctum::actingAs(moderatorWithoutFileKeys(), ['moderate_comments']);

    $this->getJson('/api/v1/comments/pending')->assertForbidden();
});

test('approving is refused, so the response cannot hand back the body', function () {
    [, $comment] = commentedFile($this->admin);

    Sanctum::actingAs(moderatorWithoutFileKeys(), ['moderate_comments']);

    $this->postJson("/api/v1/comments/{$comment->id}/approve")->assertForbidden();

    expect($comment->fresh()->isPending())->toBeTrue();
});

test('deleting from the moderation screen is refused too', function () {
    [, $comment] = commentedFile($this->admin);

    $this->actingAs(moderatorWithoutFileKeys())
        ->delete("/comments/{$comment->id}/moderate")
        ->assertForbidden();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('a moderator who may read files still moderates the whole installation', function () {
    // Guards against narrowing by more than the permission half: this role
    // is unscoped, so every comment stays in reach.
    [, $comment] = commentedFile($this->admin);

    $moderator = moderatorWithFileKeys();

    $props = $this->actingAs($moderator)->get('/comments')->assertOk()->viewData('page')['props'];

    expect(collect($props['entries'])->pluck('id')->all())->toContain($comment->id)
        ->and($props['pending_total'])->toBe(1);

    $this->actingAs($moderator)->delete("/comments/{$comment->id}/moderate")->assertRedirect();

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});
