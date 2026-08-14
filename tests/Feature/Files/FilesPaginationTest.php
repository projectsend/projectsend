<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function paginationFolders(int $count, string $prefix = 'Folder'): void
{
    for ($i = 1; $i <= $count; $i++) {
        app(FolderService::class)->create(sprintf('%s %02d', $prefix, $i), null);
    }
}

/**
 * @return list<int>
 */
function paginationFiles(User $uploader, int $count, string $prefix = 'file'): array
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $file = File::factory()->create([
            'uploaded_by' => $uploader->id,
            'name' => sprintf('%s-%02d', $prefix, $i),
            'original_name' => sprintf('%s-%02d.pdf', $prefix, $i),
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
        $ids[] = $file->id;
    }

    return $ids;
}

test('a directory with more folders than the page size splits folders across pages', function () {
    paginationFolders(30);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('folders', 25)
            ->has('files', 0)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2),
    );

    $this->actingAs($this->admin)->get('/files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('folders', 5)
            ->has('files', 0),
    );
});

test('a folder count that exactly fills a page leaves files entirely for the next page', function () {
    paginationFolders(25);
    paginationFiles($this->admin, 10);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('folders', 25)
            ->has('files', 0)
            ->where('pagination.total', 35),
    );

    $this->actingAs($this->admin)->get('/files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('folders', 0)
            ->has('files', 10),
    );
});

test('a transition mid-page splits between folders and files with no gap, overlap, or duplicate', function () {
    paginationFolders(10);
    $fileIds = paginationFiles($this->admin, 30);

    $seenFileIds = [];
    $capture = function ($files) use (&$seenFileIds) {
        foreach ($files as $file) {
            $seenFileIds[] = $file['id'];
        }

        return true;
    };

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 10)->has('files', 15)->where('files', $capture),
    );

    $this->actingAs($this->admin)->get('/files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 15)->where('files', $capture),
    );

    expect($seenFileIds)->toHaveCount(30)
        ->and(array_unique($seenFileIds))->toHaveCount(30)
        ->and(collect($seenFileIds)->sort()->values()->all())->toBe(collect($fileIds)->sort()->values()->all());
});

test('a directory with no folders paginates files exactly as before', function () {
    paginationFiles($this->admin, 30);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 25),
    );

    $this->actingAs($this->admin)->get('/files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 5),
    );
});

test('search results are no longer capped at 50 folder matches — every match is reachable by paging', function () {
    paginationFolders(60, 'Zeta');

    $seenNames = [];
    $capture = function ($folders) use (&$seenNames) {
        foreach ($folders as $folder) {
            $seenNames[] = $folder['name'];
        }

        return true;
    };

    foreach ([1, 2, 3] as $pageNumber) {
        $this->actingAs($this->admin)->get("/files?search=Zeta&page={$pageNumber}")->assertInertia(
            fn (AssertableInertia $page) => $page->where('folders', $capture),
        );
    }

    expect($seenNames)->toHaveCount(60)
        ->and(array_unique($seenNames))->toHaveCount(60);
});

test('requesting a page beyond the last one redirects to the real last page', function () {
    paginationFolders(5);
    paginationFiles($this->admin, 5);

    $this->actingAs($this->admin)->get('/files?page=99')->assertRedirect('/files');
});

test('an out-of-range page redirect preserves the current search term', function () {
    paginationFolders(1, 'Invoices');

    $this->actingAs($this->admin)->get('/files?search=Invoices&page=99')
        ->assertRedirect('/files?search=Invoices');
});

test('pagination.total reflects folders plus files, not files alone', function () {
    paginationFolders(3);
    paginationFiles($this->admin, 4);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('pagination.total', 7),
    );
});
