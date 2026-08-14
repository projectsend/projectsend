<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * What the FileComments service does when a comment is written — the
 * decisions no controller is allowed to make for itself, above all which
 * client's thread a comment joins.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->comments = app(FileComments::class);
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    $this->settings->set(Setting::CommentsGuestModeration, true);
});

test('a client always posts into their own thread', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();

    $comment = $this->comments->post($file, $client, CommentVisibility::Clients, 'Is this final?');

    expect($comment->client_context_id)->toBe($client->id)
        ->and($comment->author_id)->toBe($client->id)
        ->and($comment->approved_at)->not->toBeNull();
});

test('a staff reply inherits the conversation of the comment it answers', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    $question = $this->comments->post($file, $client, CommentVisibility::Clients, 'Is this final?');
    $answer = $this->comments->post($file, $this->admin, CommentVisibility::Clients, 'Yes it is.', $question);

    expect($answer->client_context_id)->toBe($client->id);
});

test('a reply takes its audience from the comment it answers, not from the caller', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $note = $this->comments->post($file, $this->admin, CommentVisibility::StaffOnly, 'Waiting on legal.');

    // Asking for a wider audience than the comment being answered must not
    // widen it — two sources of truth for who reads this, and the wrong
    // one would eventually win.
    $reply = $this->comments->post($file, $this->admin, CommentVisibility::Clients, 'Chasing them.', $note);

    expect($reply->visibility)->toBe(CommentVisibility::StaffOnly)
        ->and($reply->client_context_id)->toBeNull();
});

test('a staff comment written fresh reaches every client on the file', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $comment = $this->comments->post($file, $this->admin, CommentVisibility::Clients, 'New version attached.');

    // No conversation of its own — every client with access reads it, and
    // none of them can see each other's replies to it.
    expect($comment->client_context_id)->toBeNull();
});

test('a staff-only note carries no client at all', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $comment = $this->comments->post($file, $this->admin, CommentVisibility::StaffOnly, 'Waiting on legal.');

    expect($comment->client_context_id)->toBeNull()
        ->and($comment->visibility)->toBe(CommentVisibility::StaffOnly);
});

test('a client-scoped staff member cannot reply in a conversation outside their scope', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $stranger = User::factory()->client()->create();
    shareFileWith($file, $stranger);
    $manager = User::factory()->role(SystemRole::ClientManager)->create();

    $question = FileComment::factory()->for($file)->inThreadOf($stranger)->create(['author_id' => $stranger->id]);

    $this->comments->post($file, $manager, CommentVisibility::Clients, 'Hello', $question);
})->throws(AuthorizationException::class);

test('audiences that own no conversation never carry a client context', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    $note = $this->comments->post($file, $client, CommentVisibility::OnlyMe, 'Remember to check this.');
    $shout = $this->comments->post($file, $client, CommentVisibility::Everyone, 'Great file!');

    expect($note->client_context_id)->toBeNull()
        ->and($shout->client_context_id)->toBeNull();
});

test('a visibility the settings do not allow is refused', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $this->settings->set(Setting::PublicCommentsEnabled, false);

    $this->comments->post($file, $this->admin, CommentVisibility::Everyone, 'Hello world');
})->throws(AuthorizationException::class);

test('posting on a file outside the comment scope is refused', function () {
    $this->settings->set(Setting::CommentsScope, 'none');

    $this->comments->post(File::factory()->create(['uploaded_by' => $this->admin->id]), $this->admin, CommentVisibility::Clients, 'Hi');
})->throws(AuthorizationException::class);

test('an anonymous comment is held for approval and notifies only moderators', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    $comment = $this->comments->post($file, null, CommentVisibility::Everyone, 'Nice', null, 'Visitor');

    expect($comment->isPending())->toBeTrue()
        ->and($comment->guest_name)->toBe('Visitor')
        ->and($comment->ip_address)->not->toBeNull();

    $notifications = InAppNotification::query()->get();

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe('file_comment.pending')
        ->and($notifications->first()->user_id)->toBe($this->admin->id);
});

test('an anonymous comment goes live immediately when moderation is off', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, false);

    $comment = $this->comments->post($file, null, CommentVisibility::Everyone, 'Nice', null, 'Visitor');

    expect($comment->isPending())->toBeFalse()
        ->and(InAppNotification::query()->where('type', 'file_comment.posted')->count())->toBe(1);
});

test('approving a held comment publishes it and announces it once', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $comment = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    $this->comments->approve($comment, $this->admin);

    expect($comment->fresh()->isPending())->toBeFalse()
        ->and(InAppNotification::query()->where('type', 'file_comment.posted')->count())->toBe(1);

    // Approving twice must not re-announce.
    $this->comments->approve($comment->fresh(), $this->admin);

    expect(InAppNotification::query()->where('type', 'file_comment.posted')->count())->toBe(1);
});

test('a reply notifies its client and the staff who can see it, and no other client', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    shareFileWith($file, $alice);

    $question = FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id]);

    $this->comments->post($file, $this->admin, CommentVisibility::Clients, 'An update for you', $question);

    $recipients = InAppNotification::query()->where('type', 'file_comment.posted')->pluck('user_id')->all();

    expect($recipients)->toBe([$alice->id])
        ->and($recipients)->not->toContain($bob->id)
        // The author never notifies themselves.
        ->and($recipients)->not->toContain($this->admin->id);
});

test('an "only me" comment notifies nobody', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    $this->comments->post($file, $client, CommentVisibility::OnlyMe, 'A private reminder');

    expect(InAppNotification::query()->count())->toBe(0);
});

test('a client-scoped staff member is not told about a thread outside their scope', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($mine->id);

    shareFileWith($file, $theirs);

    $this->comments->post($file, $theirs, CommentVisibility::Clients, 'A question');

    $recipients = InAppNotification::query()->pluck('user_id')->all();

    expect($recipients)->not->toContain($manager->id)
        ->and($recipients)->toContain($this->admin->id);
});

test('the activity entry records the visibility but never the comment body', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->comments->post($file, $this->admin, CommentVisibility::Clients, 'Something confidential');

    $entry = ActivityLog::query()->where('action', Action::CommentPosted)->firstOrFail();

    expect($entry->context)->toBe(['visibility' => 'clients'])
        ->and($entry->subject_id)->toBe($file->id)
        ->and(json_encode($entry->context))->not->toContain('confidential');
});

test('a visitor\'s comment is logged as a visitor, not as the system', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);

    $this->comments->post($file, null, CommentVisibility::Everyone, 'Nice', null, 'A passer-by');

    // A null actor renders as "System" in the activity log, which would
    // claim the installation commented on its own file and would throw
    // away the only name the entry has.
    expect(ActivityLog::query()->where('action', Action::CommentPosted)->exists())->toBeFalse();

    $entry = ActivityLog::query()->where('action', Action::CommentPostedByVisitor)->firstOrFail();

    expect($entry->context)->toBe(['visibility' => 'everyone', 'guest' => 'A passer-by'])
        ->and($entry->subject_id)->toBe($file->id);
});

test('editing stamps edited_at and logs, deleting is soft', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $comment = $this->comments->post($file, $this->admin, CommentVisibility::Clients, 'First draft');

    $this->comments->edit($comment, 'Second draft');

    expect($comment->fresh()->body)->toBe('Second draft')
        ->and($comment->fresh()->edited_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::CommentEdited)->count())->toBe(1);

    $this->comments->remove($comment);

    expect(FileComment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(FileComment::withTrashed()->whereKey($comment->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::CommentDeleted)->count())->toBe(1);
});
