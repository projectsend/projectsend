<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * @return array<string, mixed>
 */
function publicFolderPageProps(TestResponse $response): array
{
    $page = json_decode(json_encode($response->viewData('page')), true);

    return $page['props'];
}

function makePublicFolder(string $name, bool $public = true, ?Folder $parent = null): Folder
{
    $folder = app(FolderService::class)->create($name, $parent);
    $folder->update(['public' => $public]);

    return $folder;
}

function fileInPublicFolder(?Folder $folder, string $name = 'doc', bool $public = false): File
{
    return File::factory()->create([
        'uploaded_by' => User::factory()->create()->id,
        'folder_id' => $folder?->id,
        'name' => $name,
        'original_name' => "{$name}.pdf",
        'mime_type' => 'application/pdf',
        'size' => 2048,
        'public' => $public,
    ]);
}

beforeEach(function () {
    Storage::fake('files');
    User::factory()->create();
    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');
    app(Settings::class)->set(Setting::Theme, 'default');
});

test('every file in a public folder is reachable, even ones not marked public themselves, at any depth', function () {
    $parent = makePublicFolder('Shared Reports');
    $child = makePublicFolder('Q3', public: false, parent: $parent);

    $atRoot = fileInPublicFolder($parent, 'top-level');
    $nested = fileInPublicFolder($child, 'deep-file');

    $this->get("/public/folders/{$parent->slug}")->assertInertia(
        fn ($page) => $page->component('public/themes/default/folder')
            ->where('folder.name', 'Shared Reports')
            ->has('files', 2),
    );

    $this->get(route('public.file', ['public', $atRoot->slug]))->assertOk();
    $this->get(route('public.download', ['public', $nested->slug]))->assertOk();
});

test('a file inside a non-public folder is not public, even if a sibling folder is', function () {
    $public = makePublicFolder('Open');
    $private = makePublicFolder('Closed', public: false);

    $hiddenFile = fileInPublicFolder($private, 'secret');

    $this->get(route('public.file', ['public', $hiddenFile->slug]))->assertNotFound();

    // Sanity: the public sibling's own file is fine.
    $visibleFile = fileInPublicFolder($public, 'visible');
    $this->get(route('public.file', ['public', $visibleFile->slug]))->assertOk();
});

test('a public folder is not reachable when unpublished, and 404s on the wrong base slug', function () {
    $folder = makePublicFolder('Was Public', public: false);

    $this->get("/public/folders/{$folder->slug}")->assertNotFound();

    $folder->update(['public' => true]);
    $this->get("/wrong-base/folders/{$folder->slug}")->assertNotFound();
    $this->get("/public/folders/{$folder->slug}")->assertOk();
});

test('the directory lists only top-of-subtree public folders, not nested ones already reachable via their parent', function () {
    $top = makePublicFolder('Top');
    $nestedPublic = makePublicFolder('Nested', public: true, parent: $top);
    $standaloneTop = makePublicFolder('Standalone Top');

    $response = $this->get('/public');
    $response->assertInertia(fn ($page) => $page->has('folders', 2));

    $names = collect(publicFolderPageProps($response)['folders'])->pluck('name');
    expect($names)->toContain('Top')->toContain('Standalone Top')->not->toContain('Nested');
    // Still true even though Nested is itself flagged public.
    expect($nestedPublic->public)->toBeTrue();
});

test('a file reachable via its public folder is not duplicated in the standalone public files list', function () {
    $folder = makePublicFolder('Reports');
    $inFolder = fileInPublicFolder($folder, 'inside');
    $loose = fileInPublicFolder(null, 'loose', public: true);

    $response = $this->get('/public');
    $names = collect(publicFolderPageProps($response)['files'])->pluck('name');

    expect($names)->toContain('loose')->not->toContain('inside');
});

test('marking a folder public requires upload_public; create_own_folders alone is not enough', function () {
    $role = Role::query()->create(['name' => 'Folder Creator Only', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'create_own_folders'],
        // create_own_folders alone can't create a folder at all any more
        // (FoldersController::store() also requires upload) — irrelevant
        // to what this test actually checks (upload_public), so grant it
        // just to reach that check.
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $staff = User::factory()->create(['role_id' => $role->id]);
    expect($staff->can('upload_public'))->toBeFalse()
        ->and($staff->can('create_own_folders'))->toBeTrue();

    $this->actingAs($staff)->post('/folders', [
        'name' => 'Attempted Public',
        'public' => true,
        'slug' => 'attempted-public',
    ])->assertRedirect();

    $folder = Folder::query()->where('name', 'Attempted Public')->sole();
    expect($folder->public)->toBeFalse();
});

test('a staff member with upload_public can mark a folder public on create and update', function () {
    $role = Role::query()->create(['name' => 'Publisher', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'create_own_folders'],
        // create_own_folders alone can't create a folder at all any more
        // (FoldersController::store() also requires upload).
        ['role_id' => $role->id, 'permission' => 'upload'],
        ['role_id' => $role->id, 'permission' => 'upload_public'],
        // FolderPolicy::update() gates renaming/updating an owned folder on
        // edit_files (mirroring FilePolicy) — upload_public alone only
        // covers the public-state fields, not the update action itself.
        ['role_id' => $role->id, 'permission' => 'edit_files'],
    ]);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->post('/folders', [
        'name' => 'Real Public',
        'public' => true,
        'slug' => 'real-public',
    ])->assertRedirect();

    $folder = Folder::query()->where('name', 'Real Public')->sole();
    expect($folder->public)->toBeTrue()->and($folder->slug)->toBe('real-public');

    $this->actingAs($staff)->patch("/folders/{$folder->id}", [
        'name' => 'Real Public',
        'public' => false,
    ])->assertRedirect();

    expect($folder->refresh()->public)->toBeFalse();
});

test('a client can only upload into a public folder when both the folder allows it and they hold upload_to_public_folders', function () {
    $folder = makePublicFolder('Drop Box');
    $folder->update(['allow_client_uploads' => true]);

    // Both roles hold `upload` (so the uploads.* route middleware lets
    // them through at all) — only the second also holds
    // upload_to_public_folders, isolating that as the one thing under test.
    $plainRole = Role::query()->create(['name' => 'Plain Uploader Client', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $plainRole->id, 'permission' => 'upload']);
    $withoutPermission = User::factory()->client()->create(['role_id' => $plainRole->id]);

    $role = Role::query()->create(['name' => 'Public Uploader Client', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
        ['role_id' => $role->id, 'permission' => 'upload_to_public_folders'],
    ]);
    $withPermission = User::factory()->client()->create(['role_id' => $role->id]);

    expect(Folder::uploadableBy($withoutPermission, $folder))->toBeFalse()
        ->and(Folder::uploadableBy($withPermission, $folder))->toBeTrue();

    $this->actingAs($withoutPermission)->post('/uploads', [
        'filename' => 'x.pdf', 'size' => 10, 'type' => 'application/pdf', 'folder_id' => $folder->id,
    ])->assertForbidden();

    $this->actingAs($withPermission)->post('/uploads', [
        'filename' => 'x.pdf', 'size' => 10, 'type' => 'application/pdf', 'folder_id' => $folder->id,
    ])->assertOk();
});

test('a client may always upload into a folder they own themselves', function () {
    $role = Role::query()->create(['name' => 'Folder-Owning Client', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'create_own_folders'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $client = User::factory()->client()->create(['role_id' => $role->id]);
    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine'])->assertRedirect();
    $folder = Folder::query()->where('name', 'Mine')->sole();

    expect(Folder::uploadableBy($client, $folder))->toBeTrue();

    $this->actingAs($client)->post('/uploads', [
        'filename' => 'mine.pdf', 'size' => 10, 'type' => 'application/pdf', 'folder_id' => $folder->id,
    ])->assertOk();
});

test('a public folder listing carries each file\'s categories', function () {
    $category = Category::query()->create(['name' => 'Tenders', 'color' => 'blue']);
    $folder = makePublicFolder('Shared Reports');
    $tagged = fileInPublicFolder($folder, 'tagged');
    $tagged->categories()->attach($category->id);
    fileInPublicFolder($folder, 'untagged');

    $props = publicFolderPageProps($this->get("/public/folders/{$folder->slug}"));

    expect(collect($props['files'])->firstWhere('name', 'tagged')['categories'] ?? null)
        ->toBe([['id' => $category->id, 'name' => 'Tenders', 'color' => 'blue']])
        ->and(collect($props['files'])->firstWhere('name', 'untagged')['categories'] ?? null)->toBe([]);
});
