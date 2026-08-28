<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Storage;

/**
 * `file_comments.client_context_id` and `author_id` are both
 * cascadeOnDelete, and neither cascade ever fires: users are
 * soft-deleted, so the row survives and the column goes on pointing at
 * it. Everything that asked the *relation* instead of the column read
 * that as "there is no client here" — and for client_context_id, "no
 * client" is the branch every client on the file reads.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->file = File::factory()->create(['name' => 'Quote']);
    $this->alice = User::factory()->client()->create(['name' => 'Alice Ltd']);
    $this->bob = User::factory()->client()->create(['name' => 'Bob GmbH']);

    shareFileWith($this->file, $this->alice);
    shareFileWith($this->file, $this->bob);

    $this->fromAlice = FileComment::factory()->for($this->file)->inThreadOf($this->alice)->create([
        'author_id' => $this->alice->id,
        'body' => 'What is your best price?',
    ]);
});

function readableBy(User $viewer, File $file): array
{
    return app(VisibleCommentScope::class)->for($viewer, $file)->pluck('id')->map(intval(...))->all();
}

test('a reply into a deleted client\'s thread is refused, not broadcast', function () {
    $this->alice->delete();

    $this->actingAs($this->admin)
        ->postJson("/files/{$this->file->id}/comments", [
            'body' => 'For you only: 40% off.',
            'visibility' => CommentVisibility::Clients->value,
            'reply_to' => $this->fromAlice->id,
        ])
        ->assertForbidden();

    expect(FileComment::query()->where('body', 'For you only: 40% off.')->exists())->toBeFalse()
        ->and(readableBy($this->bob, $this->file))->toBe([]);
});

test('a staff message to everybody still reaches everybody', function () {
    // The null context is a real branch, not only a failure mode: staff
    // writing fresh address every client on the file, and that must keep
    // working.
    $this->actingAs($this->admin)
        ->postJson("/files/{$this->file->id}/comments", [
            'body' => 'The catalogue is attached.',
            'visibility' => CommentVisibility::Clients->value,
        ])
        ->assertCreated();

    $broadcast = FileComment::query()->where('body', 'The catalogue is attached.')->sole();

    expect($broadcast->client_context_id)->toBeNull()
        ->and(readableBy($this->bob, $this->file))->toContain($broadcast->id);
});

test('a reply into a live client\'s thread still lands in that thread alone', function () {
    $this->actingAs($this->admin)
        ->postJson("/files/{$this->file->id}/comments", [
            'body' => 'For you only: 40% off.',
            'visibility' => CommentVisibility::Clients->value,
            'reply_to' => $this->fromAlice->id,
        ])
        ->assertCreated();

    $reply = FileComment::query()->where('body', 'For you only: 40% off.')->sole();

    expect($reply->client_context_id)->toBe($this->alice->id)
        ->and(readableBy($this->alice, $this->file))->toContain($reply->id)
        ->and(readableBy($this->bob, $this->file))->not->toContain($reply->id);
});

test('a deleted client\'s comment keeps their name instead of reading as a guest', function () {
    $this->alice->delete();

    expect($this->fromAlice->fresh()->authorName())->toBe('Alice Ltd');
});

test('a genuine guest comment is still anonymous', function () {
    $guest = FileComment::factory()->for($this->file)->fromGuest()->create();

    expect($guest->authorName())->not->toBe('Alice Ltd')
        ->and($guest->author_id)->toBeNull();
});
