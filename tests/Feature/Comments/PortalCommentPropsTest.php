<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

/**
 * The props every portal theme reads to draw its comment affordance —
 * see docs/theming-files-checklist.md. A theme cannot compute these for
 * itself, so if the controller stops sending them every theme loses the
 * feature silently.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->client = User::factory()->client()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
    $this->settings->set(Setting::PublicCommentsEnabled, false);
});

test('every file row carries a comment count narrowed to what this client may see', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $other = User::factory()->client()->create();
    shareFileWith($file, $this->client);

    FileComment::factory()->for($file)->inThreadOf($this->client)->create(['author_id' => $this->client->id]);
    // Another client's thread, and a staff internal note: neither is
    // theirs to see, so neither may be counted.
    FileComment::factory()->for($file)->inThreadOf($other)->create(['author_id' => $other->id]);
    FileComment::factory()->for($file)->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('comments_enabled', true)
            ->where('files.0.comments_count', 1)
            ->where('files.0.unread_comments_count', 0)
    );
});

test('turning commenting off tells every theme to draw nothing', function () {
    File::factory()->create(['uploaded_by' => $this->admin->id]);
    $this->settings->set(Setting::CommentsScope, 'none');

    $this->actingAs($this->client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_enabled', false)
    );
});

test('the unread count comes from the viewer\'s own unread notifications', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $this->client);

    // A staff message to the clients on this file notifies them, which is
    // what the unread badge counts — no separate read-state table.
    $this->actingAs($this->admin)->postJson("/files/{$file->id}/comments", [
        'body' => 'An update',
        'visibility' => 'clients',
    ])->assertCreated();

    $this->actingAs($this->client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.comments_count', 1)
            ->where('files.0.unread_comments_count', 1)
    );
});

test('every theme receives the comment props', function (string $theme) {
    app(Settings::class)->set(Setting::Theme, $theme);

    File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component("portal/themes/{$theme}/my-files")
            ->has('comments_enabled')
    );
})->with(['default', 'compact', 'drive', 'gallery']);
