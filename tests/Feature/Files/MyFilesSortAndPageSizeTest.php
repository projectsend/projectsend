<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use Inertia\Testing\AssertableInertia;

/*
 * Issue #1840: a client chooses how many files a page of My files holds,
 * and the order stays fixed across pages.
 */

beforeEach(function () {
    // A staff user must exist or every request 302s to /setup.
    User::factory()->create();
    $this->client = User::factory()->client()->create();
});

/**
 * @return list<int>
 */
function portalSortFiles(User $client, int $count, int $size = 100): array
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $file = File::factory()->create([
            'uploaded_by' => $client->id,
            'name' => sprintf('file-%03d', $i),
            'original_name' => sprintf('file-%03d.pdf', $i),
            'mime_type' => 'application/pdf',
            'size' => $size,
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

test('a client picks how many files a page holds', function () {
    portalSortFiles($this->client, 60);

    $this->actingAs($this->client)->get('/my-files?per_page=50')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 50)
            ->where('pagination.per_page', 50)
            ->where('pagination.per_page_options', [25, 50, 100, 200])
            ->where('pagination.last_page', 2),
    );
});

test('a page size that is not offered falls back to the default', function () {
    portalSortFiles($this->client, 30);

    $this->actingAs($this->client)->get('/my-files?per_page=5000')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 25)
            ->where('pagination.per_page', 25),
    );
});

test('a page past the end keeps the page size on the way back', function () {
    portalSortFiles($this->client, 60);

    $this->actingAs($this->client)
        ->get('/my-files?per_page=50&page=7')
        ->assertRedirect(route('my-files.index', ['per_page' => 50, 'page' => 2]));
});

test('sorting by name in reverse reverses the folders too', function () {
    $this->actingAs($this->client);
    app(FolderService::class)->create('Alpha', null);
    app(FolderService::class)->create('Zulu', null);

    $this->get('/my-files?sort=name&direction=desc')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('folders.0.name', 'Zulu')
            ->where('folders.1.name', 'Alpha'),
    );

    $this->get('/my-files?sort=size&direction=desc')->assertInertia(
        fn (AssertableInertia $page) => $page->where('folders.0.name', 'Alpha'),
    );
});

test('files of the same size keep one order across pages', function () {
    $ids = portalSortFiles($this->client, 30, size: 100);

    $seen = [];
    foreach ([1, 2] as $pageNumber) {
        $this->actingAs($this->client)->get("/my-files?sort=size&direction=asc&page={$pageNumber}")->assertInertia(
            function (AssertableInertia $page) use (&$seen) {
                foreach ($page->toArray()['props']['files'] as $row) {
                    $seen[] = $row['id'];
                }
            },
        );
    }

    expect(array_unique($seen))->toHaveCount(30)
        ->and(collect($seen)->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all());
});
