<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * Guest-facing version links.
 *
 * For an anonymous visitor "can see both files" reduces to "both are
 * effectively public and unexpired" — the same predicate showFile() 404s on
 * — so a link shown here can never point at a page that would refuse to
 * load. That equivalence is what these assert.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
    app(Settings::class)->set(Setting::Theme, 'default');
    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $this->group = Group::query()->create(['name' => 'Downloads', 'public' => true]);
});

test('a public file page names a public newer version', function () {
    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q1']);
    $revision = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q2']);

    shareFileWithGroup($original, $this->group);
    $this->versions->link($revision, $original, $this->admin);

    $this->get("/public/files/{$original->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page->component('public/themes/default/file')
            ->where('file.version.next.name', 'Pricelist Q2'),
    );
});

test('a public file page says nothing about a private newer version', function () {
    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q1']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q2']);

    shareFileWithGroup($original, $this->group);
    $this->versions->link($revision, $original, $this->admin);

    // A visitor could not open Q2, so telling them it exists leaks a file
    // that was never published.
    $this->get("/public/files/{$original->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.version.next', null),
    );
});

test('a public file page says nothing about an expired newer version', function () {
    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q1']);
    $revision = File::factory()->public()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Pricelist Q2',
        'expires_at' => now()->subDay(),
    ]);

    shareFileWithGroup($original, $this->group);
    $this->versions->link($revision, $original, $this->admin);

    // The public route 404s past expiry, so a link here would be dead.
    $this->get("/public/files/{$original->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.version.next', null),
    );
});

test('the public group listing carries the version links on each row', function () {
    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q1']);
    $revision = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Pricelist Q2']);

    shareFileWithGroup($original, $this->group);
    $this->versions->link($revision, $original, $this->admin);

    $this->get("/public/{$this->group->slug}")->assertInertia(
        function (AssertableInertia $page) {
            $files = collect($page->toArray()['props']['files']);

            expect($files->firstWhere('name', 'Pricelist Q1')['version']['next']['name'])->toBe('Pricelist Q2');
        },
    );
});

test('every public theme receives the version prop on the file page', function (string $theme) {
    app(Settings::class)->set(Setting::Theme, $theme);

    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);

    shareFileWithGroup($original, $this->group);
    $this->versions->link($revision, $original, $this->admin);

    $this->get("/public/files/{$original->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page->component("public/themes/{$theme}/file")
            ->has('file.version'),
    );
})->with(['default', 'compact', 'drive', 'gallery']);

test('every public theme receives the version prop on a group listing row', function (string $theme) {
    app(Settings::class)->set(Setting::Theme, $theme);

    $original = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);

    shareFileWithGroup($original, $this->group);
    $this->versions->link($revision, $original, $this->admin);

    $this->get("/public/{$this->group->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page->component("public/themes/{$theme}/group")
            ->has('files.0.version'),
    );
})->with(['default', 'compact', 'drive', 'gallery']);
