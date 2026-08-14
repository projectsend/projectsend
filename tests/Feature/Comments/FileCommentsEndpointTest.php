<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
    $this->settings->set(Setting::PublicCommentsEnabled, false);
    $this->settings->set(Setting::CommentsEditWindowMinutes, 15);
});

test('a client sees their own thread and is never told the others exist', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    shareFileWith($file, $alice);
    shareFileWith($file, $bob);

    FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id, 'body' => 'From Alice']);
    FileComment::factory()->for($file)->inThreadOf($bob)->create(['author_id' => $bob->id, 'body' => 'From Bob']);

    $response = $this->actingAs($alice)->getJson("/files/{$file->id}/comments")->assertOk();

    expect($response->json('comments'))->toHaveCount(1)
        ->and($response->json('comments.0.body'))->toBe('From Alice')
        // No thread list, and no thread on the comment itself: there is
        // nothing in the payload a client could learn Bob from.
        ->and($response->json('threads'))->toBeNull()
        ->and($response->json('comments.0.thread'))->toBeNull();

    expect(json_encode($response->json()))->not->toContain('From Bob')
        ->and(json_encode($response->json()))->not->toContain($bob->name);
});

test('staff see every conversation on the file, each labelled with whose it is', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $alice = User::factory()->client()->create();
    shareFileWith($file, $alice);

    FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id]);
    FileComment::factory()->for($file)->staffOnly()->create(['author_id' => $this->admin->id]);
    FileComment::factory()->for($file)->toAllClients()->create(['author_id' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/comments")->assertOk();

    expect($response->json('comments'))->toHaveCount(3)
        // Only the one addressed to a single client is labelled with a
        // name; the staff note and the message to everyone are not.
        ->and($response->json('comments.0.conversation'))->toBe($alice->name)
        ->and($response->json('comments.1.conversation'))->toBeNull()
        ->and($response->json('comments.2.conversation'))->toBeNull()
        // Replying is the only way to address one client, so it is offered
        // on theirs and on nothing else.
        ->and($response->json('comments.0.can_reply'))->toBeTrue()
        ->and($response->json('comments.1.can_reply'))->toBeFalse()
        ->and($response->json('comments.2.can_reply'))->toBeFalse();
});

test('a staff message written fresh reaches every client, each seeing only their own replies', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    shareFileWith($file, $alice);
    shareFileWith($file, $bob);

    $this->actingAs($this->admin)->postJson("/files/{$file->id}/comments", [
        'body' => 'New version attached.',
        'visibility' => 'clients',
    ])->assertCreated();

    // Both read it — it is written by staff and names no client, so seeing
    // it tells neither of them that the other is there.
    foreach ([$alice, $bob] as $client) {
        expect($this->actingAs($client)->getJson("/files/{$file->id}/comments")->json('comments'))
            ->toHaveCount(1);
    }

    // Alice answers it. Her reply is hers alone.
    $broadcast = FileComment::query()->sole();
    $this->actingAs($alice)->postJson("/files/{$file->id}/comments", [
        'body' => 'Got it, thanks',
        'visibility' => 'clients',
        'reply_to' => $broadcast->id,
    ])->assertCreated();

    expect($this->actingAs($alice)->getJson("/files/{$file->id}/comments")->json('comments'))->toHaveCount(2)
        ->and($this->actingAs($bob)->getJson("/files/{$file->id}/comments")->json('comments'))->toHaveCount(1);
});

test('a reply to a comment the viewer was never shown is treated as a fresh comment', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    shareFileWith($file, $alice);
    shareFileWith($file, $bob);

    $hers = FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id]);

    // Bob cannot see Alice's comment, so naming its id must not put his
    // words into her conversation — nor tell him it exists.
    $this->actingAs($bob)->postJson("/files/{$file->id}/comments", [
        'body' => 'Hello',
        'visibility' => 'clients',
        'reply_to' => $hers->id,
    ])->assertCreated();

    expect(FileComment::query()->where('author_id', $bob->id)->sole()->client_context_id)->toBe($bob->id);
});

test('a client posting lands in their own thread whatever they claim', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    shareFileWith($file, $alice);

    $this->actingAs($alice)->postJson("/files/{$file->id}/comments", [
        'body' => 'Is this the final version?',
        'visibility' => CommentVisibility::Clients->value,
        // A hand-rolled attempt to write into Bob's conversation.
        'client_context_id' => $bob->id,
    ])->assertCreated();

    expect(FileComment::query()->sole()->client_context_id)->toBe($alice->id);
});

test('a staff member cannot reply into a thread their library scope excludes', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $stranger = User::factory()->client()->create();
    shareFileWith($file, $stranger);

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach(User::factory()->client()->create()->id);

    $this->actingAs($manager)->postJson("/files/{$file->id}/comments", [
        'body' => 'Hello',
        'visibility' => CommentVisibility::Clients->value,
        'client_context_id' => $stranger->id,
    ])->assertForbidden();

    expect(FileComment::query()->count())->toBe(0);
});

test('a visibility the settings do not allow is refused by the endpoint too', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->postJson("/files/{$file->id}/comments", [
        'body' => 'Hello world',
        'visibility' => CommentVisibility::Everyone->value,
    ])->assertForbidden();
});

test('someone with no access to the file cannot read or write its comments', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $outsider = User::factory()->client()->create();

    $this->actingAs($outsider)->getJson("/files/{$file->id}/comments")->assertForbidden();
    $this->actingAs($outsider)->postJson("/files/{$file->id}/comments", [
        'body' => 'Hello',
        'visibility' => CommentVisibility::Clients->value,
    ])->assertForbidden();
});

test('an author can edit and delete their own comment inside the window', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $comment = FileComment::factory()->for($file)->create(['author_id' => $this->admin->id, 'body' => 'First']);

    $this->actingAs($this->admin)->patchJson("/comments/{$comment->id}", ['body' => 'Second'])->assertOk();

    expect($comment->fresh()->body)->toBe('Second');

    $this->actingAs($this->admin)->deleteJson("/comments/{$comment->id}")->assertOk();

    expect(FileComment::query()->count())->toBe(0);
});

test('nobody can edit somebody else\'s comment, moderators included', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    $comment = FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id]);

    // The seeded administrator holds moderate_comments by construction —
    // which lets them remove a comment, never rewrite one.
    $this->actingAs($this->admin)->patchJson("/comments/{$comment->id}", ['body' => 'Rewritten'])->assertForbidden();
    $this->actingAs($this->admin)->deleteJson("/comments/{$comment->id}")->assertOk();
});

test('the edit window closes', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $comment = FileComment::factory()->for($file)->create([
        'author_id' => $this->admin->id,
        'created_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($this->admin)->patchJson("/comments/{$comment->id}", ['body' => 'Too late'])->assertForbidden();
});

test('a client cannot read a comment thread through another file\'s id', function () {
    $mine = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $theirs = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($mine, $client);

    FileComment::factory()->for($theirs)->everyone()->create(['author_id' => $this->admin->id]);

    $this->actingAs($client)->getJson("/files/{$theirs->id}/comments")->assertForbidden();
});

test('a comment notification sends staff and clients to different places', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    // The file's own page, the same place the activity log's "View" link
    // lands — one destination for "show me this conversation".
    $this->actingAs($this->admin)->get("/comments/go/{$file->id}")
        ->assertRedirect(route('files.edit', [$file, 'tab' => 'comments']));

    $this->actingAs($client)->get("/comments/go/{$file->id}")
        ->assertRedirect(route('my-files.index', ['comments' => $file->id]));
});
