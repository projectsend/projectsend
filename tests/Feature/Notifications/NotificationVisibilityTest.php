<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notifications\InAppNotification;
use Inertia\Testing\AssertableInertia;

/**
 * The security-critical suite: a notification belongs to exactly one
 * recipient, and no endpoint may ever leak or mutate another user's
 * rows — see Notifier's and InAppNotificationPolicy's docblocks for the
 * contract this operationalizes.
 */
beforeEach(function () {
    // EnsureSetupIsComplete redirects every request to /setup until a
    // staff account exists — needs one present even though it's unused.
    User::factory()->create();
    $this->userA = User::factory()->client()->create();
    $this->userB = User::factory()->client()->create();

    $this->notificationForA = InAppNotification::query()->create([
        'user_id' => $this->userA->id,
        'type' => 'file_shared',
        'data' => ['itemName' => 'confidential.pdf'],
        'read_at' => null,
        'created_at' => now(),
    ]);
});

test('unread-count for user B never counts user A\'s notifications', function () {
    $this->actingAs($this->userB)->getJson('/notifications/unread-count')
        ->assertOk()
        ->assertJson(['count' => 0]);
});

test('the index page for user B never lists user A\'s notifications', function () {
    $this->actingAs($this->userB)->get('/notifications')->assertInertia(
        fn (AssertableInertia $page) => $page->where('entries', [])->where('pagination.total', 0),
    );
});

test('recent for user B never includes user A\'s notifications', function () {
    $this->actingAs($this->userB)->getJson('/notifications/recent')
        ->assertOk()
        ->assertJsonCount(0, 'entries');
});

test('user B cannot mark user A\'s notification as read via its ID', function () {
    $this->actingAs($this->userB)->post("/notifications/{$this->notificationForA->id}/read")
        ->assertForbidden();

    expect($this->notificationForA->refresh()->read_at)->toBeNull();
});

test('user B cannot mark user A\'s notification as unread via its ID', function () {
    $this->notificationForA->update(['read_at' => now()]);

    $this->actingAs($this->userB)->post("/notifications/{$this->notificationForA->id}/unread")
        ->assertForbidden();

    expect($this->notificationForA->refresh()->read_at)->not->toBeNull();
});

test('mark-all-read for user B never touches user A\'s rows', function () {
    $this->actingAs($this->userB)->post('/notifications/read-all')->assertRedirect();

    expect($this->notificationForA->refresh()->read_at)->toBeNull();
});
