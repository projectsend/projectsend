<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;

/**
 * The rules in Access\VisibleCommentScope, asserted directly against the
 * scope rather than through HTTP — every surface funnels into this one
 * class, so this is where the privacy model is either right or wrong.
 *
 * The cross-client cases are the reason the feature is shaped this way at
 * all: "people with access" must never mean "every client this file
 * happens to be shared with", because that leaks the existence of one
 * customer to another.
 */
beforeEach(function () {
    // Every HTTP feature test needs a staff user or EnsureSetupIsComplete
    // redirects; kept here too since these build users either way.
    $this->admin = User::factory()->create();
    $this->scope = app(VisibleCommentScope::class);
});

/**
 * @return list<int>
 */
function visibleCommentIds(?User $viewer, File $file): array
{
    return app(VisibleCommentScope::class)->for($viewer, $file)->pluck('id')->map(intval(...))->all();
}

test('one client never sees another client\'s thread on a file shared with both', function () {
    $file = File::factory()->create();
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();

    $fromAlice = FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id]);
    $fromBob = FileComment::factory()->for($file)->inThreadOf($bob)->create(['author_id' => $bob->id]);

    expect(visibleCommentIds($alice, $file))->toBe([$fromAlice->id])
        ->and(visibleCommentIds($bob, $file))->toBe([$fromBob->id]);
});

test('a staff reply into one client\'s thread stays out of the other\'s', function () {
    $file = File::factory()->create();
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();

    $reply = FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $this->admin->id]);

    expect(visibleCommentIds($alice, $file))->toBe([$reply->id])
        ->and(visibleCommentIds($bob, $file))->toBe([]);
});

test('unscoped staff see every thread on a file, including internal notes', function () {
    $file = File::factory()->create();
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();

    $fromAlice = FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id]);
    $fromBob = FileComment::factory()->for($file)->inThreadOf($bob)->create(['author_id' => $bob->id]);
    // No client context at all: a staff-only note.
    $note = FileComment::factory()->for($file)->create(['author_id' => $this->admin->id]);

    expect(visibleCommentIds($this->admin, $file))
        ->toEqualCanonicalizing([$fromAlice->id, $fromBob->id, $note->id]);
});

test('an internal note is invisible to the client whose file it is on', function () {
    $file = File::factory()->create();
    $client = User::factory()->client()->create();

    FileComment::factory()->for($file)->create(['author_id' => $this->admin->id]);

    expect(visibleCommentIds($client, $file))->toBe([]);
});

test('an "only me" comment is invisible to everyone but its author, staff included', function () {
    $file = File::factory()->create();
    $client = User::factory()->client()->create();

    $note = FileComment::factory()->for($file)->onlyMe()->create(['author_id' => $client->id]);

    expect(visibleCommentIds($client, $file))->toBe([$note->id])
        ->and(visibleCommentIds($this->admin, $file))->toBe([])
        ->and(visibleCommentIds(null, $file))->toBe([]);
});

test('a public comment reaches guests only while the file is still public', function () {
    $file = File::factory()->public()->create();
    $comment = FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);

    expect(visibleCommentIds(null, $file))->toBe([$comment->id]);

    $file->forceFill(['public' => false])->save();

    // Publicness is re-derived per read, so retracting the file retracts
    // the comment with it — for guests. Staff keep their history.
    expect(visibleCommentIds(null, $file->fresh()))->toBe([])
        ->and(visibleCommentIds($this->admin, $file->fresh()))->toBe([$comment->id]);
});

test('a guest sees nothing at all on a file that is not public', function () {
    $file = File::factory()->create();
    FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);

    expect(visibleCommentIds(null, $file))->toBe([]);
});

test('a pending comment is invisible until approved, except to moderators', function () {
    $file = File::factory()->public()->create();
    $client = User::factory()->client()->create();

    $pending = FileComment::factory()->for($file)->fromGuest()->pending()->create();

    expect(visibleCommentIds(null, $file))->toBe([])
        ->and(visibleCommentIds($client, $file))->toBe([])
        // The seeded administrator holds every permission by construction.
        ->and(visibleCommentIds($this->admin, $file))->toBe([$pending->id]);

    $pending->forceFill(['approved_at' => now()])->save();

    expect(visibleCommentIds(null, $file))->toBe([$pending->id]);
});

test('a staff member without moderation rights does not see pending comments', function () {
    $file = File::factory()->public()->create();

    $role = Role::query()->create(['name' => 'Files only', 'is_system' => false, 'is_administrator' => false]);
    RolePermission::query()->insert([['role_id' => $role->id, 'permission' => 'edit_others_files']]);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $approved = FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);
    FileComment::factory()->for($file)->fromGuest()->pending()->create();

    expect(visibleCommentIds($staff, $file))->toBe([$approved->id]);
});

test('a client-scoped staff member sees their client\'s thread and not the other\'s on the same file', function () {
    $file = File::factory()->create();
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->attach($mine->id);

    $visible = FileComment::factory()->for($file)->inThreadOf($mine)->create(['author_id' => $mine->id]);
    FileComment::factory()->for($file)->inThreadOf($theirs)->create(['author_id' => $theirs->id]);
    $note = FileComment::factory()->for($file)->create(['author_id' => $this->admin->id]);

    // Internal notes belong to no client, so they stay in scope.
    expect(visibleCommentIds($manager, $file))->toEqualCanonicalizing([$visible->id, $note->id]);
});

test('a soft-deleted comment disappears from every viewer', function () {
    $file = File::factory()->public()->create();
    $comment = FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);

    $comment->delete();

    expect(visibleCommentIds($this->admin, $file))->toBe([])
        ->and(visibleCommentIds(null, $file))->toBe([]);
});

test('the visibility column round-trips as an enum', function () {
    $comment = FileComment::factory()->onlyMe()->create();

    expect($comment->fresh()->visibility)->toBe(CommentVisibility::OnlyMe);
});

test('a message to the clients reaches the team as well as the clients', function () {
    $file = File::factory()->create();
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();
    $colleague = User::factory()->create();

    // No conversation of its own: staff addressing everyone on the file.
    // The composer calls this "Staff and clients", so this is the test
    // that the label is true.
    $message = FileComment::factory()->for($file)->toAllClients()->create(['author_id' => $this->admin->id]);

    expect(visibleCommentIds($this->admin, $file))->toBe([$message->id])
        ->and(visibleCommentIds($colleague, $file))->toBe([$message->id])
        ->and(visibleCommentIds($alice, $file))->toBe([$message->id])
        ->and(visibleCommentIds($bob, $file))->toBe([$message->id])
        // Still not the open web.
        ->and(visibleCommentIds(null, $file))->toBe([]);
});

test('a client writing to that same audience reaches the team and nobody else', function () {
    $file = File::factory()->create();
    $alice = User::factory()->client()->create();
    $bob = User::factory()->client()->create();

    // The same stored audience, written from the other end: a client's
    // comment carries their own conversation, so it never fans out to the
    // other clients. Which is why they see the option called "Staff".
    $question = FileComment::factory()->for($file)->inThreadOf($alice)->create(['author_id' => $alice->id]);

    expect(visibleCommentIds($this->admin, $file))->toBe([$question->id])
        ->and(visibleCommentIds($alice, $file))->toBe([$question->id])
        ->and(visibleCommentIds($bob, $file))->toBe([]);
});
