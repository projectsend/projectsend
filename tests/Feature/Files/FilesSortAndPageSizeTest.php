<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
 * Issue #1840: the staff library sorts by clicking a column, and lets the
 * viewer pick how many rows a page holds.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * @param  array<string, array{size?: int, created_at?: string}>  $files  name => attributes
 * @return array<string, int> name => id
 */
function sortTestFiles(User $uploader, array $files): array
{
    $ids = [];
    foreach ($files as $name => $attributes) {
        $ids[$name] = File::factory()->create([
            'uploaded_by' => $uploader->id,
            'name' => $name,
            'original_name' => "{$name}.pdf",
            'mime_type' => 'application/pdf',
            'size' => $attributes['size'] ?? 100,
            'created_at' => isset($attributes['created_at']) ? Carbon::parse($attributes['created_at']) : now(),
        ])->id;
    }

    return $ids;
}

/**
 * @return list<int>
 */
function manySortTestFiles(User $uploader, int $count, int $size = 100): array
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $ids[] = File::factory()->create([
            'uploaded_by' => $uploader->id,
            'name' => sprintf('file-%03d', $i),
            'original_name' => sprintf('file-%03d.pdf', $i),
            'mime_type' => 'application/pdf',
            'size' => $size,
        ])->id;
    }

    return $ids;
}

test('without a chosen sort a folder lists its newest file first', function () {
    sortTestFiles($this->admin, [
        'old' => ['created_at' => '2026-01-01'],
        'new' => ['created_at' => '2026-03-01'],
        'middle' => ['created_at' => '2026-02-01'],
    ]);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'new')
            ->where('files.1.name', 'middle')
            ->where('files.2.name', 'old')
            ->where('sort', 'date')
            ->where('direction', 'desc')
            ->where('sort_chosen', false),
    );
});

test('files sort by size in either direction', function () {
    sortTestFiles($this->admin, [
        'medium' => ['size' => 500],
        'small' => ['size' => 10],
        'large' => ['size' => 9000],
    ]);

    $this->actingAs($this->admin)->get('/files?sort=size&direction=asc')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'small')
            ->where('files.1.name', 'medium')
            ->where('files.2.name', 'large')
            ->where('sort', 'size')
            ->where('direction', 'asc')
            ->where('sort_chosen', true),
    );

    $this->actingAs($this->admin)->get('/files?sort=size&direction=desc')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'large')
            ->where('files.2.name', 'small'),
    );
});

test('sorting by name in reverse reverses the folders too, and other sorts keep them A to Z', function () {
    $folders = app(FolderService::class);
    $folders->create('Alpha', null);
    $folders->create('Zulu', null);
    sortTestFiles($this->admin, ['a-file' => [], 'z-file' => []]);

    $this->actingAs($this->admin)->get('/files?sort=name&direction=desc')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('folders.0.name', 'Zulu')
            ->where('folders.1.name', 'Alpha')
            ->where('files.0.name', 'z-file')
            ->where('files.1.name', 'a-file'),
    );

    $this->actingAs($this->admin)->get('/files?sort=size&direction=desc')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('folders.0.name', 'Alpha')
            ->where('folders.1.name', 'Zulu'),
    );
});

test('a sort applies to search results as well', function () {
    sortTestFiles($this->admin, [
        'report-b' => ['size' => 10],
        'report-a' => ['size' => 20],
    ]);

    $this->actingAs($this->admin)->get('/files?search=report')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.name', 'report-a')
            ->where('sort', 'name'),
    );

    $this->actingAs($this->admin)->get('/files?search=report&sort=size&direction=desc')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.name', 'report-a'),
    );

    $this->actingAs($this->admin)->get('/files?search=report&sort=size&direction=asc')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.name', 'report-b'),
    );
});

test('an unknown sort is refused', function () {
    $this->actingAs($this->admin)->get('/files?sort=password')->assertSessionHasErrors('sort');
});

test('rows with the same size keep one order across pages, so none repeats and none is skipped', function () {
    $ids = manySortTestFiles($this->admin, 30, size: 100);

    $seen = [];
    foreach ([1, 2] as $pageNumber) {
        $this->actingAs($this->admin)->get("/files?sort=size&direction=asc&page={$pageNumber}")->assertInertia(
            function (AssertableInertia $page) use (&$seen) {
                foreach ($page->toArray()['props']['files'] as $row) {
                    $seen[] = $row['id'];
                }
            },
        );
    }

    expect($seen)->toHaveCount(30)
        ->and(array_unique($seen))->toHaveCount(30)
        ->and(collect($seen)->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all());
});

test('a page holds as many rows as the viewer picked, and the pager says so', function () {
    manySortTestFiles($this->admin, 60);

    $this->actingAs($this->admin)->get('/files?per_page=50')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 50)
            ->where('pagination.per_page', 50)
            ->where('pagination.per_page_options', [25, 50, 100, 200])
            ->where('pagination.last_page', 2),
    );

    $this->actingAs($this->admin)->get('/files?per_page=100')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 60)
            ->where('pagination.last_page', 1),
    );
});

test('a page size that is not offered falls back to the default', function (string $perPage) {
    manySortTestFiles($this->admin, 30);

    $this->actingAs($this->admin)->get("/files?per_page={$perPage}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 25)
            ->where('pagination.per_page', 25),
    );
})->with(['100000', '0', '-5', '30', 'abc']);

test('a page past the end goes back to the last page and keeps the sort and the page size', function () {
    manySortTestFiles($this->admin, 60);

    $this->actingAs($this->admin)
        ->get('/files?sort=size&direction=desc&per_page=50&page=9')
        ->assertRedirect(route('files.index', ['sort' => 'size', 'direction' => 'desc', 'per_page' => 50, 'page' => 2]));
});

test('lists with a fixed page size do not offer the choice', function () {
    $this->actingAs($this->admin)->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->missing('pagination.per_page'),
    );
});
