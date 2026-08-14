<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Notifications\GroupMembershipApprovedNotification;
use App\Modules\Notifications\NotificationPreference;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // EnsureSetupIsComplete redirects every request to /setup until a
    // staff account exists — needs one present even though it's unused.
    User::factory()->create();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    $this->group = Group::query()->create(['name' => 'Wanted']);
});

test('an absent preference row falls back to the type default (email on for group.membership_approved)', function () {
    Notification::fake();
    $client = User::factory()->client()->create();

    expect(NotificationPreference::query()->where('user_id', $client->id)->exists())->toBeFalse();

    app(Notifier::class)->send('group.membership_approved', [$client], subject: $this->group, data: ['groupName' => $this->group->name]);

    Notification::assertSentTo($client, GroupMembershipApprovedNotification::class);
});

test('an explicit opt-out preference suppresses the mail companion', function () {
    Notification::fake();
    $client = User::factory()->client()->create();

    NotificationPreference::query()->create([
        'user_id' => $client->id,
        'type' => 'group.membership_approved',
        'email_enabled' => false,
    ]);

    app(Notifier::class)->send('group.membership_approved', [$client], subject: $this->group, data: ['groupName' => $this->group->name]);

    Notification::assertNothingSent();
});

test('updating preferences via the settings page persists a row per type', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->put('/settings/notifications', [
        'preferences' => [
            ['type' => 'group.membership_approved', 'email_enabled' => false],
        ],
    ])->assertRedirect();

    $row = NotificationPreference::query()->where('user_id', $client->id)->where('type', 'group.membership_approved')->sole();
    expect($row->email_enabled)->toBeFalse();
});

test('the master switch off suppresses email regardless of an explicit per-user opt-in', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);
    $client = User::factory()->client()->create();

    NotificationPreference::query()->create([
        'user_id' => $client->id,
        'type' => 'group.membership_approved',
        'email_enabled' => true,
    ]);

    app(Notifier::class)->send('group.membership_approved', [$client], subject: $this->group, data: ['groupName' => $this->group->name]);

    Notification::assertNothingSent();
});

test('the preferences edit page lists every type that can email, however it emails', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/settings/notifications')->assertInertia(
        function (AssertableInertia $page) {
            $page->component('settings/notifications');

            $keys = collect($page->toArray()['props']['types'])->pluck('key');

            // Both routes count: a type Notifier mails directly, and one
            // the digest buffers and mails. The digest ones had no toggle
            // at all before — a file-share email could not be switched off
            // by the person receiving it.
            expect($keys)->toContain('group.membership_approved', 'file_shared', 'file_comment.posted')
                // Still nothing for a type that cannot email either way.
                ->not->toContain('client_uploaded');
        },
    );
});
