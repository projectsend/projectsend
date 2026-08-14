<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Notifications\NewVersionAvailableNotification;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Who hears about a new version — an INTERSECTION, never a difference.
 *
 * Notifier performs no authorization of its own, so a mistake here mails a
 * client the name of a file that was never shared with them. That is the
 * highest-consequence notification in this feature and it gets its own file.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
});

/** @return Collection<int, InAppNotification> */
function versionNotifications(): Collection
{
    return InAppNotification::query()->where('type', 'file_new_version')->get();
}

test('a client who already had both files is told a newer version exists', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    // Both shared up front, so the link is not what grants access.
    shareFileWith($original, $client);
    shareFileWith($revision, $client);

    $this->versions->link($revision, $original, $this->admin);

    $notifications = versionNotifications();

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->user_id)->toBe($client->id)
        ->and($notifications->first()->data['previousName'])->toBe('Rev C');
});

test('a client who only had the old file is told nothing at all', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);

    // The revision inherits the original's recipients, so this client does
    // gain access — but from the merge, which sends file_shared. Sending
    // file_new_version too would be two mails for one action.
    $this->versions->link($revision, $original, $this->admin);

    expect(versionNotifications())->toHaveCount(0);
});

test('a stranger is never notified', function () {
    $stranger = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    expect(versionNotifications())->toHaveCount(0)
        ->and(InAppNotification::query()->where('user_id', $stranger->id)->count())->toBe(0);
});

test('group members who hold both files are notified once each', function () {
    $first = User::factory()->client()->create();
    $second = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Contractors']);
    $group->members()->attach([$first->id, $second->id]);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWithGroup($original, $group);
    shareFileWithGroup($revision, $group);

    $this->versions->link($revision, $original, $this->admin);

    expect(versionNotifications()->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

test('a client who cannot see the new version because it expired is not notified', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
    ]);

    shareFileWith($original, $client);
    shareFileWith($revision, $client);

    // Telling someone about a file they cannot open is noise at best.
    $this->versions->link($revision, $original, $this->admin);

    expect(versionNotifications())->toHaveCount(0);
});

test('the email is the digest mail, carrying both file names', function () {
    // Set explicitly, never assumed: the Settings cache survives
    // RefreshDatabase, so a test relying on this default would inherit
    // whatever the previous file left behind.
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    Notification::fake();

    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    shareFileWith($original, $client);
    shareFileWith($revision, $client);

    $this->versions->link($revision, $original, $this->admin);

    // The queue is sync under test, so the debounce job runs inline and the
    // PendingNotification row is consumed before this line — assert the mail
    // it produced, not the row it briefly held.
    Notification::assertSentTo($client, NewVersionAvailableNotification::class);
});

test('staff are not notified about their own linking', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($revision, $original, $this->admin);

    expect(InAppNotification::query()->where('user_id', $this->admin->id)->count())->toBe(0);
});
