<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\InAppNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function sharedNotificationsFor(User $client): Collection
{
    return InAppNotification::query()
        ->where('user_id', $client->id)
        ->where('type', 'file_shared')
        ->get();
}

test('sharing a file with a client notifies them', function () {
    $file = File::factory()->create(['name' => 'Report']);
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $notifications = sharedNotificationsFor($client);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['itemName'])->toBe('Report')
        ->and($notifications->first()->subject_id)->toBe($file->id);
});

// Folder shares used to send nothing at all: FolderAssignmentsController had
// no Notifier, so a client was granted live access to a whole subtree without
// being told. The registered type's own label — "A file or folder was shared
// with you" — shows folders were always meant to be covered.
test('sharing a folder with a client notifies them too', function () {
    $folder = Folder::query()->create(['name' => 'Brand Kit', 'path' => '/']);
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $notifications = sharedNotificationsFor($client);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['itemName'])->toBe('Brand Kit')
        ->and($notifications->first()->subject_id)->toBe($folder->id)
        ->and($notifications->first()->subject_type)->toBe($folder->getMorphClass());
});

test('sharing with a group notifies every member, for both subjects', function (string $type) {
    $group = Group::query()->create(['name' => 'Design Team']);
    $first = User::factory()->client()->create();
    $second = User::factory()->client()->create();
    $group->members()->sync([$first->id, $second->id]);

    $subject = $type === 'files'
        ? File::factory()->create(['name' => 'Report'])
        : Folder::query()->create(['name' => 'Brand Kit', 'path' => '/']);

    $this->actingAs($this->admin)->post("/{$type}/{$subject->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    expect(sharedNotificationsFor($first))->toHaveCount(1)
        ->and(sharedNotificationsFor($second))->toHaveCount(1);
})->with(['files', 'folders']);

// firstOrCreate makes re-posting an existing assignment a no-op on the row,
// but the notification is sent outside that check, so a repeat share notifies
// again. That is long-standing behaviour on the file side; what this pins is
// that folders now do exactly the same thing. If the repeat notification is
// ever judged wrong, it should stop being sent for both at once — which is
// the point of them sharing one implementation.
test('a repeat share notifies again, identically for files and folders', function () {
    $file = File::factory()->create(['name' => 'Report']);
    $folder = Folder::query()->create(['name' => 'Brand Kit', 'path' => '/']);
    $fileClient = User::factory()->client()->create();
    $folderClient = User::factory()->client()->create();

    foreach ([1, 2] as $ignored) {
        $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $fileClient->id]);
        $this->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $folderClient->id]);
    }

    expect(sharedNotificationsFor($fileClient)->count())
        ->toBe(sharedNotificationsFor($folderClient)->count())
        ->and(sharedNotificationsFor($fileClient))->toHaveCount(2);
});

test('unsharing does not notify', function (string $type) {
    $subject = $type === 'files'
        ? File::factory()->create(['name' => 'Report'])
        : Folder::query()->create(['name' => 'Brand Kit', 'path' => '/']);
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post("/{$type}/{$subject->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->delete("/{$type}/{$subject->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    expect(sharedNotificationsFor($client))->toHaveCount(1);
})->with(['files', 'folders']);

/*
|--------------------------------------------------------------------------
| Linking a revision moves recipients; it must not re-announce the root
|--------------------------------------------------------------------------
*/

function notificationTypesFor(User $client): array
{
    return InAppNotification::query()
        ->where('user_id', $client->id)
        ->orderBy('id')
        ->pluck('type')
        ->all();
}

test('linking a revision does not re-announce the root to somebody who already had it', function () {
    $client = User::factory()->client()->create();
    $root = File::factory()->create(['name' => 'Report']);
    $revision = File::factory()->create(['name' => 'Report v2']);

    $this->actingAs($this->admin)->post("/files/{$root->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/files/{$revision->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    InAppNotification::query()->where('user_id', $client->id)->delete();

    app(FileVersions::class)->link($revision->refresh(), $root->refresh(), $this->admin);

    // One action, one notification. file_new_version is the right one:
    // link() resolves that audience before the merge precisely so the
    // people who could already see both are told once, and here they hold
    // the root itself, so nothing was shared with them.
    expect(notificationTypesFor($client))->toBe(['file_new_version'])
        ->and(FileAssignment::query()->where('file_id', $root->id)->count())->toBe(1);
});

test('linking a revision still announces the root to somebody who is gaining it', function () {
    $client = User::factory()->client()->create();
    $root = File::factory()->create(['name' => 'Report']);
    $revision = File::factory()->create(['name' => 'Report v2']);

    // Only the revision, so the merge really does hand them the root.
    $this->actingAs($this->admin)->post("/files/{$revision->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    InAppNotification::query()->where('user_id', $client->id)->delete();

    app(FileVersions::class)->link($revision->refresh(), $root->refresh(), $this->admin);

    // Not file_new_version: they could not see the root before, so they are
    // not part of sharedAudience() — see its INTERSECTION docblock.
    expect(notificationTypesFor($client))->toBe(['file_shared'])
        ->and(FileAssignment::query()->where('file_id', $root->id)->count())->toBe(1);
});

test('a group already on the root is skipped the same way a client is', function () {
    $member = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Design Team']);
    $group->members()->sync([$member->id]);

    $root = File::factory()->create(['name' => 'Report']);
    $revision = File::factory()->create(['name' => 'Report v2']);

    $this->actingAs($this->admin)->post("/files/{$root->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    $this->actingAs($this->admin)->post("/files/{$revision->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    InAppNotification::query()->where('user_id', $member->id)->delete();

    app(FileVersions::class)->link($revision->refresh(), $root->refresh(), $this->admin);

    expect(notificationTypesFor($member))->toBe(['file_new_version'])
        ->and(FileAssignment::query()->where('file_id', $root->id)->count())->toBe(1);
});
