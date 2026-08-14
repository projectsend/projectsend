<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/**
 * Every way a comment can be created, changed or removed, and the entry it
 * leaves behind.
 *
 * Written path by path rather than against the service, because the point
 * is that no *surface* skips the log — the service is shared, but a
 * controller that wrote to the model directly would bypass it silently and
 * nothing else here would notice.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->client = User::factory()->client()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);
    $this->settings->set(Setting::CommentsEditWindowMinutes, 15);
    $this->settings->set(Setting::PublicListingEnabled, true);
    $this->settings->set(Setting::PublicListingSlug, 'public');

    $this->file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($this->file, $this->client);
});

/** The single entry for an action, or a failure if there isn't exactly one. */
function commentEntry(Action $action): ActivityLog
{
    return ActivityLog::query()->where('action', $action)->sole();
}

test('posting through the web leaves an entry against the file', function () {
    $this->actingAs($this->admin)->postJson("/files/{$this->file->id}/comments", [
        'body' => 'A note',
        'visibility' => 'clients',
    ])->assertCreated();

    $entry = commentEntry(Action::CommentPosted);

    expect($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($this->file->id)
        ->and($entry->subject_type)->toBe($this->file->getMorphClass())
        ->and($entry->origin)->toBe(ActivityOrigin::Ui)
        // The body is never recorded: a comment can be visible to one
        // client only, and the log is read by staff whose file scope may
        // not include that conversation.
        ->and(json_encode($entry->context))->not->toContain('A note');
});

test('a client posting is logged as the client', function () {
    $this->actingAs($this->client)->postJson("/files/{$this->file->id}/comments", [
        'body' => 'A question',
        'visibility' => 'clients',
    ])->assertCreated();

    expect(commentEntry(Action::CommentPosted)->actor_id)->toBe($this->client->id);
});

test('editing through the web is logged, as the editor', function () {
    $comment = FileComment::factory()->for($this->file)->staffOnly()->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)->patchJson("/comments/{$comment->id}", ['body' => 'Reworded'])->assertOk();

    $entry = commentEntry(Action::CommentEdited);

    expect($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($this->file->id);
});

test('deleting through the web is logged, as whoever deleted it', function () {
    $comment = FileComment::factory()->for($this->file)->inThreadOf($this->client)->create(['author_id' => $this->client->id]);

    // A moderator removing somebody else's comment: the entry names them,
    // not the author.
    $this->actingAs($this->admin)->deleteJson("/comments/{$comment->id}")->assertOk();

    expect(commentEntry(Action::CommentDeleted)->actor_id)->toBe($this->admin->id);
});

test('a visitor posting through the public page is logged as a visitor', function () {
    $group = Group::query()->create(['name' => 'Showcase', 'public' => true]);
    $public = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWithGroup($public, $group);

    $this->postJson("/public/files/{$public->slug}/comments", [
        'body' => 'Nice',
        'guest_name' => 'A passer-by',
    ])->assertCreated();

    $entry = commentEntry(Action::CommentPostedByVisitor);

    expect($entry->actor_id)->toBeNull()
        ->and($entry->subject_id)->toBe($public->id)
        // Its own action rather than a null actor, which would have read
        // as "System commented on this file".
        ->and($entry->context['guest'])->toBe('A passer-by')
        ->and(ActivityLog::query()->where('action', Action::CommentPosted)->exists())->toBeFalse();
});

test('approving from the moderation queue is logged', function () {
    $comment = FileComment::factory()->for($this->file)->fromGuest()->pending()->create();

    $this->actingAs($this->admin)->post("/comments/{$comment->id}/approve")->assertRedirect();

    expect(commentEntry(Action::CommentApproved)->actor_id)->toBe($this->admin->id);
});

test('approving from the details panel is logged the same way', function () {
    $comment = FileComment::factory()->for($this->file)->fromGuest()->pending()->create();

    // Same route, JSON representation — one decision, so one entry.
    $this->actingAs($this->admin)->postJson("/comments/{$comment->id}/approve")->assertOk();

    expect(commentEntry(Action::CommentApproved)->actor_id)->toBe($this->admin->id);
});

test('deleting from the moderation queue is logged', function () {
    $comment = FileComment::factory()->for($this->file)->fromGuest()->pending()->create();

    $this->actingAs($this->admin)->delete("/comments/{$comment->id}/moderate")->assertRedirect();

    expect(commentEntry(Action::CommentDeleted)->actor_id)->toBe($this->admin->id);
});

test('the API writes the same entries, marked as having come from a token', function () {
    Sanctum::actingAs($this->admin, ['edit_others_files']);

    $this->postJson("/api/v1/files/{$this->file->id}/comments", [
        'body' => 'From an integration',
        'visibility' => 'clients',
    ])->assertCreated();

    $posted = commentEntry(Action::CommentPosted);

    expect($posted->actor_id)->toBe($this->admin->id)
        // The one thing that differs from the web path, and the reason a
        // shared service is worth having: same entry, honest origin.
        ->and($posted->origin)->toBe(ActivityOrigin::Api);

    $comment = FileComment::query()->sole();

    $this->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'Reworded'])->assertOk();
    expect(commentEntry(Action::CommentEdited)->origin)->toBe(ActivityOrigin::Api);

    $this->deleteJson("/api/v1/comments/{$comment->id}")->assertNoContent();
    expect(commentEntry(Action::CommentDeleted)->origin)->toBe(ActivityOrigin::Api);
});

test('every comment action is offered as a filter on the activity page', function () {
    $this->actingAs($this->admin)->get('/activity')->assertInertia(
        function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['actions'])->pluck('key');

            // The filter is built from Action::cases(), so this is really
            // asserting that nothing is logged under a key the log cannot
            // then be narrowed to.
            expect($keys)->toContain('comment.posted', 'comment.posted_by_visitor', 'comment.edited', 'comment.deleted', 'comment.approved');
        }
    );
});

test('each comment action renders a sentence rather than its key', function () {
    foreach ([
        Action::CommentPosted,
        Action::CommentPostedByVisitor,
        Action::CommentEdited,
        Action::CommentDeleted,
        Action::CommentApproved,
    ] as $action) {
        // template() is the sentence on a log row, description() the label
        // in the filter dropdown. A case missing from either would render
        // as its raw key, or not compile at all.
        expect($action->template())->not->toBe($action->value)->not->toBeEmpty()
            ->and($action->description())->not->toBe($action->value)->not->toBeEmpty();
    }
});

test('a visitor is recorded as not signed in, and the scheduler is still the system', function () {
    $group = Group::query()->create(['name' => 'Showcase', 'public' => true]);
    $public = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWithGroup($public, $group);

    $this->postJson("/public/files/{$public->slug}/comments", [
        'body' => 'Nice',
        'guest_name' => 'A passer-by',
    ])->assertCreated();

    // Both have no actor, and they were showing the same word — which had
    // the audit trail claiming the installation commented on its own file.
    expect(commentEntry(Action::CommentPostedByVisitor)->origin)->toBe(ActivityOrigin::Public);

    app(ActivityLogger::class)->logSystem(Action::ExpiredFileDeleted, ['name' => 'old.pdf']);

    expect(ActivityLog::query()->where('action', Action::ExpiredFileDeleted)->sole()->origin)
        ->toBe(ActivityOrigin::System);
});

test('the activity page can be narrowed to unauthenticated actions', function () {
    $this->actingAs($this->admin)->get('/activity')->assertInertia(
        function (AssertableInertia $page) {
            $origins = collect($page->toArray()['props']['origins'])->pluck('key');

            expect($origins)->toContain('public', 'system', 'ui', 'api');
        }
    );
});
