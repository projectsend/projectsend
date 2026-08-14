<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Comments\Notifications\CommentDigestNotification;
use App\Modules\Comments\Notifications\CommentPostedNotification;
use App\Modules\Files\Models\File;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Notifications\Jobs\SendNotificationDigest;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\NotificationPreference;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Comment email goes through the same debounced digest as file shares, so
 * answering several comments in a row is one message rather than one each.
 */
beforeEach(function () {
    Notification::fake();
    // Without this the queue runs sync, so the delayed digest fires inline
    // and consumes its own buffer before the assertion can see it — the
    // debounce only debounces where jobs actually wait.
    Queue::fake();

    $this->admin = User::factory()->create();
    $this->client = User::factory()->client()->create();
    $this->comments = app(FileComments::class);
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
    $this->settings->set(Setting::EmailNotificationsEnabled, true);

    $this->file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($this->file, $this->client);
});

/** Run the digest the way the queue would, resolving its dependencies. */
function runDigest(int $userId): void
{
    app()->call([new SendNotificationDigest($userId, 'file_comment.posted'), 'handle']);
}

test('a comment buffers an email for everyone it notifies', function () {
    $this->comments->post($this->file, $this->admin, CommentVisibility::Clients, 'An update');

    $pending = PendingNotification::query()->where('type', 'file_comment.posted')->get();

    expect($pending)->toHaveCount(1)
        ->and($pending->first()->user_id)->toBe($this->client->id)
        ->and($pending->first()->subject_name)->toBe($this->file->name)
        // The body is deliberately absent: email is forwarded and quoted,
        // and a comment can be visible to one client only.
        ->and(json_encode($pending->first()->context))->not->toContain('An update');
});

test('a lone comment sends the single email', function () {
    $this->comments->post($this->file, $this->admin, CommentVisibility::Clients, 'An update');

    runDigest($this->client->id);

    Notification::assertSentTo($this->client, CommentPostedNotification::class);
    Notification::assertNotSentTo($this->client, CommentDigestNotification::class);
});

test('a burst becomes one digest, not one email each', function () {
    foreach (['First', 'Second', 'Third'] as $body) {
        $this->comments->post($this->file, $this->admin, CommentVisibility::Clients, $body);
    }

    runDigest($this->client->id);

    Notification::assertSentTo($this->client, CommentDigestNotification::class);
    Notification::assertNotSentTo($this->client, CommentPostedNotification::class);

    // Consumed, so the other queued jobs for the same burst find nothing.
    expect(PendingNotification::query()->where('type', 'file_comment.posted')->count())->toBe(0);

    runDigest($this->client->id);
    Notification::assertSentTimes(CommentDigestNotification::class, 1);
});

test('somebody who switched comment email off is not buffered at all', function () {
    NotificationPreference::query()->create([
        'user_id' => $this->client->id,
        'type' => 'file_comment.posted',
        'email_enabled' => false,
    ]);

    $this->comments->post($this->file, $this->admin, CommentVisibility::Clients, 'An update');

    expect(PendingNotification::query()->where('type', 'file_comment.posted')->count())->toBe(0);

    // The in-app notification still happens — turning off the email is not
    // turning off the notification.
    expect(InAppNotification::query()->where('user_id', $this->client->id)->count())->toBe(1);
});

test('the installation master switch stops it before anything is buffered', function () {
    $this->settings->set(Setting::EmailNotificationsEnabled, false);

    $this->comments->post($this->file, $this->admin, CommentVisibility::Clients, 'An update');

    expect(PendingNotification::query()->count())->toBe(0);
});

test('an only-me note emails nobody, since it notifies nobody', function () {
    $this->comments->post($this->file, $this->admin, CommentVisibility::OnlyMe, 'A private note');

    expect(PendingNotification::query()->count())->toBe(0);
});

test('comments and shares queue separately, so one does not swallow the other', function () {
    $this->comments->post($this->file, $this->admin, CommentVisibility::Clients, 'An update');

    app(NotificationDigester::class)
        ->queue('file_shared', [$this->client], 'contract.pdf', ['is_folder' => false]);

    // Same recipient, two types, two buffers — a digest consumes only its
    // own, so a comment email never arrives titled as a file share.
    runDigest($this->client->id);

    Notification::assertSentTo($this->client, CommentPostedNotification::class);
    expect(PendingNotification::query()->where('type', 'file_shared')->count())->toBe(1);
});
