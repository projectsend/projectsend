<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Notifications\FileShareDigestNotification;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Notifications\Jobs\SendNotificationDigest;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function digestTestFile(User $uploader, string $name): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => $name,
        'original_name' => "{$name}.pdf",
        'path' => Str::uuid().'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

beforeEach(function () {
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
});

test('sharing several files with the same client in a burst sends one digest, not one email per file', function () {
    $client = User::factory()->client()->create();
    $fileA = digestTestFile($this->admin, 'alpha');
    $fileB = digestTestFile($this->admin, 'beta');
    $fileC = digestTestFile($this->admin, 'gamma');

    Queue::fake();

    $this->actingAs($this->admin)->post("/files/{$fileA->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/files/{$fileB->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/files/{$fileC->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    // One debounced job scheduled per share — see SendFileShareDigest's
    // docblock for why that's fine even though only one of them will
    // actually find anything left to do once it runs.
    Queue::assertPushed(SendNotificationDigest::class, 3);
    expect(PendingNotification::query()->where('user_id', $client->id)->count())->toBe(3);

    Notification::fake();

    // Simulate the debounce window elapsing: run just one job, as if it
    // were the first of the three to fire.
    app()->call([new SendNotificationDigest($client->id, 'file_shared'), 'handle']);

    Notification::assertSentTo($client, FileShareDigestNotification::class, function (FileShareDigestNotification $notification) {
        $mail = $notification->toMail(new User);

        return $mail->subject === '3 items have been shared with you'
            && in_array('File: alpha', $mail->introLines, true)
            && in_array('File: beta', $mail->introLines, true)
            && in_array('File: gamma', $mail->introLines, true);
    });
    Notification::assertNotSentTo($client, FileSharedNotification::class);

    expect(PendingNotification::query()->where('user_id', $client->id)->count())->toBe(0);
});

test('a later debounced job for an already-consumed batch is a no-op', function () {
    $client = User::factory()->client()->create();
    $fileA = digestTestFile($this->admin, 'alpha');
    $fileB = digestTestFile($this->admin, 'beta');

    Queue::fake();
    $this->actingAs($this->admin)->post("/files/{$fileA->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/files/{$fileB->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    Notification::fake();

    // The first job to run consumes everything.
    app()->call([new SendNotificationDigest($client->id, 'file_shared'), 'handle']);
    Notification::assertSentToTimes($client, FileShareDigestNotification::class, 1);

    // The second job (from the second share) finds nothing pending.
    app()->call([new SendNotificationDigest($client->id, 'file_shared'), 'handle']);
    Notification::assertSentToTimes($client, FileShareDigestNotification::class, 1);
});

test('a single share still sends the plain FileSharedNotification, not a digest', function () {
    $client = User::factory()->client()->create();
    $file = digestTestFile($this->admin, 'solo');

    Notification::fake();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    Notification::assertSentTo($client, FileSharedNotification::class);
    Notification::assertNotSentTo($client, FileShareDigestNotification::class);
});

test('a folder and a file shared together land in the same digest, each labeled correctly', function () {
    $client = User::factory()->client()->create();
    $file = digestTestFile($this->admin, 'alpha');
    $folder = Folder::query()->create(['name' => 'Reports']);

    Queue::fake();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    Notification::fake();
    app()->call([new SendNotificationDigest($client->id, 'file_shared'), 'handle']);

    Notification::assertSentTo($client, FileShareDigestNotification::class, function (FileShareDigestNotification $notification) {
        $mail = $notification->toMail(new User);

        return in_array('File: alpha', $mail->introLines, true)
            && in_array('Folder: Reports', $mail->introLines, true);
    });
});

test('each group member gets their own digest, not a shared one', function () {
    $memberA = User::factory()->client()->create();
    $memberB = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Recipients', 'public' => false]);
    $group->members()->attach([$memberA->id, $memberB->id]);

    $fileA = digestTestFile($this->admin, 'alpha');
    $fileB = digestTestFile($this->admin, 'beta');

    Queue::fake();
    $this->actingAs($this->admin)->post("/files/{$fileA->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    $this->actingAs($this->admin)->post("/files/{$fileB->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    expect(PendingNotification::query()->where('user_id', $memberA->id)->count())->toBe(2)
        ->and(PendingNotification::query()->where('user_id', $memberB->id)->count())->toBe(2);

    Notification::fake();
    app()->call([new SendNotificationDigest($memberA->id, 'file_shared'), 'handle']);
    app()->call([new SendNotificationDigest($memberB->id, 'file_shared'), 'handle']);

    Notification::assertSentToTimes($memberA, FileShareDigestNotification::class, 1);
    Notification::assertSentToTimes($memberB, FileShareDigestNotification::class, 1);
});

test('disabling email notifications skips queuing entirely, not just the send', function () {
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);
    $client = User::factory()->client()->create();
    $file = digestTestFile($this->admin, 'alpha');

    Queue::fake();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    Queue::assertNotPushed(SendNotificationDigest::class);
    expect(PendingNotification::query()->count())->toBe(0);
});

test('assigning a file also creates an in-app notification, independent of the email digest pipeline', function () {
    $client = User::factory()->client()->create();
    $file = digestTestFile($this->admin, 'alpha');

    // Email notifications off — the digest pipeline above is fully
    // skipped, but the in-app notification (a separate, always-on write)
    // must still be created; the two are independent.
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $entry = InAppNotification::query()
        ->where('user_id', $client->id)
        ->where('type', 'file_shared')
        ->sole();

    expect($entry->subject_id)->toBe($file->id)
        ->and($entry->data)->toBe(['itemName' => 'alpha']);
});

test('a digest job for a deleted user or with nothing pending does not error', function () {
    app()->call([new SendNotificationDigest(999999, 'file_shared'), 'handle']);

    $client = User::factory()->client()->create();
    app()->call([new SendNotificationDigest($client->id, 'file_shared'), 'handle']);

    expect(true)->toBeTrue();
});
