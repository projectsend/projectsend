<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * What the public listing's comment endpoint may serve a reader who
 * happens to be signed in — and what it may not, because being signed in
 * is not the same as being allowed to open the file.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::PublicListingEnabled, true);
    $this->settings->set(Setting::PublicListingSlug, 'public');
    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);
    $this->settings->set(Setting::CaptchaProvider, 'none');

    $this->group = Group::query()->create(['name' => 'Showcase', 'public' => true]);
    $this->file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWithGroup($this->file, $this->group);

    // The conversation on that file: one line anybody may read, one note
    // for staff, and one message from staff to every client on it.
    $this->everyone = FileComment::factory()->for($this->file)->everyone()
        ->create(['author_id' => $this->admin->id, 'body' => 'Nice work']);
    $this->staffNote = FileComment::factory()->for($this->file)->staffOnly()
        ->create(['author_id' => $this->admin->id, 'body' => 'Internal only']);
    $this->broadcast = FileComment::factory()->for($this->file)->toAllClients()
        ->create(['author_id' => $this->admin->id, 'body' => 'For our clients']);
});

/** The thread as $viewer reads it from the public listing. */
function publicThreadFor(?User $viewer): array
{
    $test = test();
    $request = $viewer === null ? $test : $test->actingAs($viewer);

    return $request->getJson("/public/files/{$test->file->slug}/comments")->assertOk()->json('comments');
}

test('a staff member the file gate does not admit reads no staff-only note there', function () {
    // A client-scoped manager whose assigned client has nothing to do
    // with this file: GET /files/{file}/comments answers them 403, and
    // the public page must not be the way around that.
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([User::factory()->client()->create()->id]);

    $this->actingAs($manager)->getJson("/files/{$this->file->id}/comments")->assertForbidden();

    $bodies = array_column(publicThreadFor($manager), 'body');

    expect($bodies)->toBe(['Nice work']);
});

test('a staff member with no file permissions at all reads no staff-only note there either', function () {
    // The other half of the same door: FilePolicy::view wants one of
    // upload / edit_files / edit_others_files before it looks at scope,
    // so a staff account holding none of them cannot open this file.
    $staff = staffWithPermissions(['view_news']);

    $this->actingAs($staff)->getJson("/files/{$this->file->id}/comments")->assertForbidden();

    expect(array_column(publicThreadFor($staff), 'body'))->toBe(['Nice work']);
});

test('a client the file was never shared with reads no message to its clients', function () {
    $stranger = User::factory()->client()->create();

    $this->actingAs($stranger)->getJson("/files/{$this->file->id}/comments")->assertForbidden();

    expect(array_column(publicThreadFor($stranger), 'body'))->toBe(['Nice work']);
});

test('a client the file was shared with still reads their whole conversation', function () {
    // The fix is not deny-everything: this reader passes the file's own
    // gate, so the public page shows them exactly what their portal does.
    $client = User::factory()->client()->create();
    $client->memberOfGroups()->attach($this->group->id);

    FileComment::factory()->for($this->file)->inThreadOf($client)
        ->create(['author_id' => $this->admin->id, 'body' => 'Just for you']);

    $this->actingAs($client)->getJson("/files/{$this->file->id}/comments")->assertOk();

    expect(array_column(publicThreadFor($client), 'body'))
        ->toContain('Nice work', 'For our clients', 'Just for you')
        ->not->toContain('Internal only');
});

test('a signed-in visitor is still served as themselves', function () {
    // The endpoint's own promise, and the one thing narrowing must not
    // take away: their own writing is theirs wherever they read it.
    $stranger = User::factory()->client()->create();

    $mine = FileComment::factory()->for($this->file)->inThreadOf($stranger)
        ->create(['author_id' => $stranger->id, 'body' => 'I wrote this']);
    // And held stays held. The approval rule is shared with the
    // authenticated reading rather than restated, so this endpoint keeps
    // answering it exactly as it did.
    $held = FileComment::factory()->for($this->file)->everyone()->pending()
        ->create(['author_id' => $stranger->id, 'body' => 'Mine, waiting']);

    $ids = array_column(publicThreadFor($stranger), 'id');

    expect($ids)->toContain($mine->id, $this->everyone->id)
        ->not->toContain($held->id)
        ->not->toContain($this->staffNote->id)
        ->not->toContain($this->broadcast->id);
});

test('a visitor with no account reads exactly what they read before', function () {
    expect(array_column(publicThreadFor(null), 'body'))->toBe(['Nice work']);

    // Including their own comment while it waits, which rides on the
    // session rather than on an account.
    $this->postJson("/public/files/{$this->file->slug}/comments", [
        'body' => 'Passing through',
        'guest_name' => 'A visitor',
    ])->assertCreated();

    expect(array_column(publicThreadFor(null), 'body'))->toBe(['Nice work', 'Passing through']);
});

test('editing a comment posted through the public listing does not hand back the private thread', function () {
    $stranger = User::factory()->client()->create();

    $this->actingAs($stranger)->postJson("/public/files/{$this->file->slug}/comments", ['body' => 'Hello'])
        ->assertCreated();

    $mine = FileComment::query()->where('author_id', $stranger->id)->sole();

    $bodies = array_column(
        $this->actingAs($stranger)->patchJson("/comments/{$mine->id}", ['body' => 'Hello again'])
            ->assertOk()->json('comments'),
        'body',
    );

    expect($bodies)->toBe(['Nice work', 'Hello again']);
});

test('deleting one does not either', function () {
    $stranger = User::factory()->client()->create();

    $this->actingAs($stranger)->postJson("/public/files/{$this->file->slug}/comments", ['body' => 'Hello'])
        ->assertCreated();

    $mine = FileComment::query()->where('author_id', $stranger->id)->sole();

    $bodies = array_column(
        $this->actingAs($stranger)->deleteJson("/comments/{$mine->id}")->assertOk()->json('comments'),
        'body',
    );

    expect($bodies)->toBe(['Nice work']);
});

test('the file-bound endpoint is untouched for a reader who may see the file', function () {
    // The routes that bind a file authorize `view` before they present
    // anything, so nothing about them changes — this pins that the shared
    // presenter still gives them the full authenticated reading.
    $bodies = array_column(
        $this->actingAs($this->admin)->getJson("/files/{$this->file->id}/comments")->assertOk()->json('comments'),
        'body',
    );

    expect($bodies)->toContain('Nice work', 'Internal only', 'For our clients');
});
