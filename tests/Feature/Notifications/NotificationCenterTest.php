<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notifications\InAppNotification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // EnsureSetupIsComplete redirects every request to /setup until a
    // staff account exists — needs one present even though it's unused.
    User::factory()->create();
    $this->client = User::factory()->client()->create();
});

function notificationFor(User $user, bool $read = false): InAppNotification
{
    return InAppNotification::query()->create([
        'user_id' => $user->id,
        'type' => 'file_shared',
        'data' => ['itemName' => 'report.pdf'],
        'read_at' => $read ? now() : null,
        'created_at' => now(),
    ]);
}

test('unread-count reflects only this viewer\'s unread rows', function () {
    notificationFor($this->client);
    notificationFor($this->client);
    notificationFor($this->client, read: true);

    $this->actingAs($this->client)->getJson('/notifications/unread-count')
        ->assertOk()
        ->assertJson(['count' => 2]);
});

test('the index page paginates and returns a resolved template', function () {
    notificationFor($this->client);

    $this->actingAs($this->client)->get('/notifications')->assertInertia(
        fn (AssertableInertia $page) => $page->component('notifications/index')
            ->has('entries', 1)
            ->where('entries.0.template', ':itemName was shared with you')
            ->where('entries.0.replacements.itemName', 'report.pdf')
            ->where('pagination.total', 1),
    );
});

test('recent returns the latest entries as plain JSON', function () {
    notificationFor($this->client);
    notificationFor($this->client);

    $this->actingAs($this->client)->getJson('/notifications/recent')
        ->assertOk()
        ->assertJsonCount(2, 'entries');
});

test('marking one notification read updates only that row', function () {
    $a = notificationFor($this->client);
    $b = notificationFor($this->client);

    $this->actingAs($this->client)->post("/notifications/{$a->id}/read")->assertRedirect();

    expect($a->refresh()->read_at)->not->toBeNull();
    expect($b->refresh()->read_at)->toBeNull();
});

test('marking a read notification unread reverses it', function () {
    $entry = notificationFor($this->client, read: true);

    $this->actingAs($this->client)->post("/notifications/{$entry->id}/unread")->assertRedirect();

    expect($entry->refresh()->read_at)->toBeNull();
});

test('mark-all-read clears every unread row for the viewer in one request', function () {
    notificationFor($this->client);
    notificationFor($this->client);

    $this->actingAs($this->client)->post('/notifications/read-all')->assertRedirect();

    expect(InAppNotification::query()->where('user_id', $this->client->id)->whereNull('read_at')->count())->toBe(0);
});
