<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Groups\Notifications\GroupMembershipDeniedNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;

/**
 * approve() refuses a request that is not pending. deny() did not, and a
 * denial is not idempotent: it re-stamps denied_at, which is what the
 * client's re-request cooldown counts from.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->client = User::factory()->client()->create();
    $this->group = Group::query()->create(['name' => 'Guarded', 'slug' => 'guarded', 'public' => true]);

    $this->request = MembershipRequest::query()->create([
        'group_id' => $this->group->id,
        'user_id' => $this->client->id,
        'status' => MembershipRequest::STATUS_PENDING,
    ]);
});

test('denying a pending request works, once', function () {
    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}")->assertRedirect();

    expect($this->request->fresh()->status)->toBe(MembershipRequest::STATUS_DENIED);

    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}")->assertNotFound();
});

test('a second denial does not move the stamp, or the log, or the mail', function () {
    Notification::fake();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}");
    $stamped = $this->request->fresh()->denied_at;

    $this->travel(5)->days();
    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}")->assertNotFound();

    expect($this->request->fresh()->denied_at?->toIso8601String())->toBe($stamped?->toIso8601String())
        ->and(ActivityLog::query()->where('action', Action::GroupMembershipDenied->value)->count())->toBe(1);

    Notification::assertSentToTimes($this->client, GroupMembershipDeniedNotification::class, 1);
});

test('a replayed denial cannot hold a client out of a group past the cooldown', function () {
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    app(Settings::class)->set(Setting::ClientsMembershipDenyCooldownDays, 30);

    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}");

    // Day 29: still inside the cooldown, and a denial arriving now used
    // to restart it.
    $this->travel(29)->days();
    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}")->assertNotFound();

    $this->travel(2)->days();

    $this->actingAs($this->client)
        ->post('/my-groups', ['group_id' => $this->group->id])
        ->assertSessionHasNoErrors();

    expect($this->request->fresh()->status)->toBe(MembershipRequest::STATUS_PENDING);
});

test('approve already refused a request that is not pending', function () {
    $this->actingAs($this->admin)->delete("/membership-requests/{$this->request->id}");

    $this->actingAs($this->admin)
        ->post("/membership-requests/{$this->request->id}/approve")
        ->assertNotFound();

    expect($this->group->members()->count())->toBe(0);
});
