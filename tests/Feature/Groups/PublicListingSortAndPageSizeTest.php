<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/*
 * Issue #1840: visitors on a public folder, group or the public index can
 * sort the list and choose how many files a page holds.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->uploader = User::factory()->create();

    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');
    app(Settings::class)->set(Setting::Theme, 'default');

    $this->actingAs($this->uploader);
    $this->folder = app(FolderService::class)->create('Drawings', null);
    $this->folder->update(['public' => true]);
    auth()->logout();
});

/**
 * @param  array<string, int>  $sizes  name => size
 */
function publicSortFiles(User $uploader, Folder $folder, array $sizes): void
{
    foreach ($sizes as $name => $size) {
        File::factory()->create([
            'uploaded_by' => $uploader->id,
            'folder_id' => $folder->id,
            'name' => $name,
            'original_name' => "{$name}.pdf",
            'mime_type' => 'application/pdf',
            'size' => $size,
        ]);
    }
}

function manyPublicSortFiles(User $uploader, Folder $folder, int $count): void
{
    $sizes = [];
    for ($i = 1; $i <= $count; $i++) {
        $sizes[sprintf('drawing-%03d', $i)] = 100;
    }
    publicSortFiles($uploader, $folder, $sizes);
}

test('a public folder lists by name unless the visitor picks another order', function () {
    publicSortFiles($this->uploader, $this->folder, ['b-medium' => 500, 'a-large' => 9000, 'c-small' => 10]);

    $this->get("/public/folders/{$this->folder->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'a-large')
            ->where('sort', 'name')
            ->where('direction', 'asc'),
    );

    $this->get("/public/folders/{$this->folder->slug}?sort=size&direction=asc")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'c-small')
            ->where('files.1.name', 'b-medium')
            ->where('files.2.name', 'a-large'),
    );

    $this->get("/public/folders/{$this->folder->slug}?sort=name&direction=desc")->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.name', 'c-small'),
    );
});

test('a mangled sort in the address shows the folder in its default order instead of an error', function () {
    publicSortFiles($this->uploader, $this->folder, ['b' => 1, 'a' => 2]);

    $this->get("/public/folders/{$this->folder->slug}?sort=secret_column&direction=sideways")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'a')
            ->where('sort', 'name')
            ->where('direction', 'asc'),
    );
});

test('a visitor picks how many files a public folder page holds', function () {
    manyPublicSortFiles($this->uploader, $this->folder, 60);

    $this->get("/public/folders/{$this->folder->slug}?per_page=50")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 50)
            ->where('pagination.per_page', 50)
            ->where('pagination.last_page', 2),
    );

    $this->get("/public/folders/{$this->folder->slug}?per_page=9999")->assertInertia(
        fn (AssertableInertia $page) => $page->has('files', 25),
    );
});

test('a page past the end of a public folder keeps the order and page size', function () {
    manyPublicSortFiles($this->uploader, $this->folder, 60);

    $this->get("/public/folders/{$this->folder->slug}?sort=size&direction=desc&per_page=50&page=8")
        ->assertRedirect(route('public.folder', ['public', $this->folder->slug, 'sort' => 'size', 'direction' => 'desc', 'per_page' => 50, 'page' => 2]));
});

test('the public index sorts its files and takes a page size', function () {
    foreach (['small' => 10, 'large' => 9000] as $name => $size) {
        File::factory()->create([
            'uploaded_by' => $this->uploader->id,
            'name' => $name,
            'original_name' => "{$name}.pdf",
            'mime_type' => 'application/pdf',
            'size' => $size,
            'public' => true,
        ]);
    }

    $this->get('/public?sort=size&direction=desc&per_page=100')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'large')
            ->where('files.1.name', 'small')
            ->where('pagination.per_page', 100)
            ->where('sort', 'size'),
    );
});
