<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/*
 * A staff member limited to some clients sees only the folders and files
 * of those clients. The counts on a folder row must follow the same rule:
 * they used to count everything in the folder, which told a scoped
 * manager how many files, subfolders and shares they were not allowed to
 * know about.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $this->mine = User::factory()->client()->create();
    $this->stranger = User::factory()->client()->create();
    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->manager->assignedClients()->sync([$this->mine->id]);

    // A folder the manager made, so it is in their library.
    $this->actingAs($this->manager);
    $this->folder = app(FolderService::class)->create('Projects', null);
    auth()->logout();
});

test('a scoped manager\'s folder row counts only what they may see', function () {
    // Things the manager may not know about, put there by an admin.
    File::factory()->create(['uploaded_by' => $this->admin->id, 'folder_id' => $this->folder->id]);
    $this->actingAs($this->admin);
    app(FolderService::class)->create('Hidden subfolder', $this->folder);
    FolderAssignment::query()->create([
        'folder_id' => $this->folder->id,
        'assignable_type' => (new User)->getMorphClass(),
        'assignable_id' => $this->stranger->id,
    ]);

    // And one thing they may: their own upload. (Sharing the folder with
    // their own client would be no test: it makes everything inside it
    // visible to that client, and so, rightly, to the manager.)
    File::factory()->create(['uploaded_by' => $this->manager->id, 'folder_id' => $this->folder->id]);

    $this->actingAs($this->manager)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('folders.0.name', 'Projects')
            ->where('folders.0.files_count', 1)
            ->where('folders.0.children_count', 0)
            ->where('folders.0.shared_count', 0),
    );
});

test('an unscoped admin still sees every count', function () {
    File::factory()->count(2)->create(['uploaded_by' => $this->admin->id, 'folder_id' => $this->folder->id]);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('folders.0.files_count', 2),
    );
});

test('a share the manager may know about is still counted', function () {
    FolderAssignment::query()->create([
        'folder_id' => $this->folder->id,
        'assignable_type' => (new User)->getMorphClass(),
        'assignable_id' => $this->mine->id,
    ]);
    FolderAssignment::query()->create([
        'folder_id' => $this->folder->id,
        'assignable_type' => (new User)->getMorphClass(),
        'assignable_id' => $this->stranger->id,
    ]);

    $this->actingAs($this->manager)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('folders.0.shared_count', 1),
    );
    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('folders.0.shared_count', 2),
    );
});
