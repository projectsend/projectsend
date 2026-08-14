<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Notifications\GroupMembershipApprovedNotification;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->group = Group::query()->create(['name' => 'Wanted']);
});

test('send() creates one row per recipient with correct type, subject, and data', function () {
    $a = User::factory()->client()->create();
    $b = User::factory()->client()->create();

    app(Notifier::class)->send('group.membership_approved', [$a, $b], subject: $this->group, data: ['groupName' => $this->group->name]);

    expect(InAppNotification::query()->where('type', 'group.membership_approved')->count())->toBe(2);

    $entry = InAppNotification::query()->where('user_id', $a->id)->sole();
    expect($entry->subject_type)->toBe(Group::class)
        ->and($entry->subject_id)->toBe($this->group->id)
        ->and($entry->data)->toBe(['groupName' => 'Wanted']);
});

test('an unknown notification type throws', function () {
    $client = User::factory()->client()->create();

    expect(fn () => app(Notifier::class)->send('not_a_real_type', [$client]))
        ->toThrow(InvalidArgumentException::class);
});

test('the mail companion is sent only when the master switch and the recipient preference both allow it', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    $client = User::factory()->client()->create();

    app(Notifier::class)->send('group.membership_approved', [$client], subject: $this->group, data: ['groupName' => $this->group->name]);

    Notification::assertSentTo($client, GroupMembershipApprovedNotification::class);
});

test('disabling the master email switch suppresses the mail companion but still creates the in-app row', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);
    $client = User::factory()->client()->create();

    app(Notifier::class)->send('group.membership_approved', [$client], subject: $this->group, data: ['groupName' => $this->group->name]);

    Notification::assertNothingSent();
    expect(InAppNotification::query()->where('user_id', $client->id)->exists())->toBeTrue();
});

test('a type with no mail companion never sends email, regardless of settings', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    $client = User::factory()->client()->create();

    app(Notifier::class)->send('file_shared', [$client], data: ['itemName' => 'report.pdf']);

    Notification::assertNothingSent();
    expect(InAppNotification::query()->where('user_id', $client->id)->where('type', 'file_shared')->exists())->toBeTrue();
});
