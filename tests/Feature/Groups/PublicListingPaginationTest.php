<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * @return array<string, mixed>
 */
function publicListingPaginationProps(TestResponse $response): array
{
    $page = json_decode(json_encode($response->viewData('page')), true);

    return $page['props'];
}

function paginationPublicGroups(int $count, string $prefix = 'Group'): void
{
    for ($i = 1; $i <= $count; $i++) {
        Group::query()->create(['name' => sprintf('%s %02d', $prefix, $i), 'public' => true]);
    }
}

function paginationPublicFolders(int $count, string $prefix = 'Folder'): void
{
    for ($i = 1; $i <= $count; $i++) {
        $folder = app(FolderService::class)->create(sprintf('%s %02d', $prefix, $i), null);
        $folder->update(['public' => true]);
    }
}

function paginationStandalonePublicFiles(int $count, string $prefix = 'file'): void
{
    for ($i = 1; $i <= $count; $i++) {
        File::factory()->create([
            'uploaded_by' => User::factory()->create()->id,
            'name' => sprintf('%s-%02d', $prefix, $i),
            'original_name' => sprintf('%s-%02d.pdf', $prefix, $i),
            'mime_type' => 'application/pdf',
            'size' => 100,
            'public' => true,
        ]);
    }
}

beforeEach(function () {
    Storage::fake('files');
    // EnsureSetupIsComplete redirects every guest request to /setup until
    // a staff account exists.
    User::factory()->create();

    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');
});

test('the index splits three sequences (groups, folders, files) across pages with no repeats', function () {
    paginationPublicGroups(15);
    paginationPublicFolders(15);
    paginationStandalonePublicFiles(15);

    $page1 = publicListingPaginationProps($this->get('/public'));
    expect($page1['groups'])->toHaveCount(15)
        ->and($page1['folders'])->toHaveCount(10)
        ->and($page1['files'])->toHaveCount(0)
        ->and($page1['pagination']['total'])->toBe(45)
        ->and($page1['pagination']['last_page'])->toBe(2);

    $page2 = publicListingPaginationProps($this->get('/public?page=2'));
    expect($page2['groups'])->toHaveCount(0)
        ->and($page2['folders'])->toHaveCount(5)
        ->and($page2['files'])->toHaveCount(15);
});

test('the index paginates fine with only files and no groups or folders', function () {
    paginationStandalonePublicFiles(30);

    $page1 = publicListingPaginationProps($this->get('/public'));
    expect($page1['groups'])->toHaveCount(0)
        ->and($page1['folders'])->toHaveCount(0)
        ->and($page1['files'])->toHaveCount(25);

    $page2 = publicListingPaginationProps($this->get('/public?page=2'));
    expect($page2['files'])->toHaveCount(5);
});

test('the index redirects an out-of-range page to the real last page', function () {
    paginationPublicGroups(2);

    $this->get('/public?page=99')->assertRedirect('/public');
});

test("a public group's own file list paginates instead of dumping every file unbounded", function () {
    $group = Group::query()->create(['name' => 'Big Group', 'public' => true]);
    for ($i = 1; $i <= 30; $i++) {
        $file = File::factory()->create([
            'uploaded_by' => User::factory()->create()->id,
            'name' => sprintf('file-%02d', $i),
            'original_name' => sprintf('file-%02d.pdf', $i),
            'mime_type' => 'application/pdf',
            'size' => 100,
            'public' => true,
        ]);
        FileAssignment::query()->create([
            'file_id' => $file->id,
            'assignable_type' => (new Group)->getMorphClass(),
            'assignable_id' => $group->id,
        ]);
    }

    $page1 = publicListingPaginationProps($this->get("/public/{$group->slug}"));
    expect($page1['files'])->toHaveCount(25)
        ->and($page1['pagination']['total'])->toBe(30)
        ->and($page1['pagination']['last_page'])->toBe(2);

    $page2 = publicListingPaginationProps($this->get("/public/{$group->slug}?page=2"));
    expect($page2['files'])->toHaveCount(5);

    $this->get("/public/{$group->slug}?page=99")->assertRedirect("/public/{$group->slug}?page=2");
});

test("a public folder's own file list paginates instead of dumping every file unbounded", function () {
    $folder = app(FolderService::class)->create('Big Folder', null);
    $folder->update(['public' => true]);

    for ($i = 1; $i <= 30; $i++) {
        File::factory()->create([
            'uploaded_by' => User::factory()->create()->id,
            'folder_id' => $folder->id,
            'name' => sprintf('file-%02d', $i),
            'original_name' => sprintf('file-%02d.pdf', $i),
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
    }

    $page1 = publicListingPaginationProps($this->get("/public/folders/{$folder->slug}"));
    expect($page1['files'])->toHaveCount(25)
        ->and($page1['pagination']['total'])->toBe(30)
        ->and($page1['pagination']['last_page'])->toBe(2);

    $page2 = publicListingPaginationProps($this->get("/public/folders/{$folder->slug}?page=2"));
    expect($page2['files'])->toHaveCount(5);

    $this->get("/public/folders/{$folder->slug}?page=99")->assertRedirect("/public/folders/{$folder->slug}?page=2");
});
