<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

/**
 * The props behind the comment icon on a staff library row.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
});

test('a file row carries its comment count and whether any are waiting', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id]);
    FileComment::factory()->for($file)->fromGuest()->pending()->create();

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('comments_enabled', true)
            // A moderator sees the pending one, so it counts toward both.
            ->where('files.0.comments_count', 2)
            ->where('files.0.pending_comments_count', 1)
    );
});

test('a staff member who cannot moderate is never shown a pending badge', function () {
    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    FileComment::factory()->for($file)->fromGuest()->pending()->create();
    FileComment::factory()->for($file)->everyone()->create(['author_id' => $this->admin->id]);

    $staff = staffWithPermissions(['upload', 'edit_others_files']);

    $this->actingAs($staff)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page
            // The pending one is invisible to them, so it is in neither count.
            ->where('files.0.comments_count', 1)
            ->where('files.0.pending_comments_count', 0)
    );
});

test('the count is what this staff member may see, not what exists', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $client = User::factory()->client()->create();
    shareFileWith($file, $client);

    // A client's private note is theirs alone, staff included.
    FileComment::factory()->for($file)->onlyMe()->create(['author_id' => $client->id]);
    FileComment::factory()->for($file)->inThreadOf($client)->create(['author_id' => $client->id]);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.comments_count', 1)
    );
});

test('turning commenting off removes the affordance from every row', function () {
    File::factory()->create(['uploaded_by' => $this->admin->id]);
    $this->settings->set(Setting::CommentsScope, 'none');

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_enabled', false)
    );
});

test('the file\'s own page offers the conversation, and says so in its props', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_enabled', true)
    );

    $this->settings->set(Setting::CommentsScope, 'none');

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_enabled', false)
    );
});
