<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // A staff user must exist or every request 302s to /setup.
    User::factory()->create();
});

function myFilesPaginationFolders(User $client, int $count, string $prefix = 'Folder'): void
{
    test()->actingAs($client);
    for ($i = 1; $i <= $count; $i++) {
        app(FolderService::class)->create(sprintf('%s %02d', $prefix, $i), null);
    }
}

/**
 * @return list<int>
 */
function myFilesPaginationFiles(User $client, int $count, string $prefix = 'file'): array
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $file = File::factory()->create([
            'uploaded_by' => $client->id,
            'name' => sprintf('%s-%02d', $prefix, $i),
            'original_name' => sprintf('%s-%02d.pdf', $prefix, $i),
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
        FileAssignment::query()->create([
            'file_id' => $file->id,
            'assignable_type' => (new User)->getMorphClass(),
            'assignable_id' => $client->id,
        ]);
        $ids[] = $file->id;
    }

    return $ids;
}

test('a directory with more folders than the page size splits folders across pages', function () {
    $client = User::factory()->client()->create();
    myFilesPaginationFolders($client, 30);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('folders', 25)
            ->has('files', 0)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2),
    );

    $this->actingAs($client)->get('/my-files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 5)->has('files', 0),
    );
});

test('a transition mid-page splits between folders and files with no gap, overlap, or duplicate', function () {
    $client = User::factory()->client()->create();
    myFilesPaginationFolders($client, 10);
    $fileIds = myFilesPaginationFiles($client, 30);

    $seenFileIds = [];
    $capture = function ($files) use (&$seenFileIds) {
        foreach ($files as $file) {
            $seenFileIds[] = $file['id'];
        }

        return true;
    };

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 10)->has('files', 15)->where('files', $capture),
    );

    $this->actingAs($client)->get('/my-files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 15)->where('files', $capture),
    );

    expect($seenFileIds)->toHaveCount(30)
        ->and(array_unique($seenFileIds))->toHaveCount(30)
        ->and(collect($seenFileIds)->sort()->values()->all())->toBe(collect($fileIds)->sort()->values()->all());
});

test('a directory with no folders paginates files exactly as before', function () {
    $client = User::factory()->client()->create();
    myFilesPaginationFiles($client, 30);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 25),
    );

    $this->actingAs($client)->get('/my-files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 5),
    );
});

test('requesting a page beyond the last one redirects to the real last page, preserving sort/direction', function () {
    $client = User::factory()->client()->create();
    myFilesPaginationFolders($client, 5);
    myFilesPaginationFiles($client, 5);

    $this->actingAs($client)->get('/my-files?sort=name&direction=asc&page=99')
        ->assertRedirect('/my-files?sort=name&direction=asc');
});

test('pagination.total reflects folders plus files, not files alone', function () {
    $client = User::factory()->client()->create();
    myFilesPaginationFolders($client, 3);
    myFilesPaginationFiles($client, 4);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('pagination.total', 7),
    );
});
