<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function fileInFolder(User $as, ?Folder $folder, string $name = 'doc.pdf'): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name, 20, 'application/pdf'),
        'name' => '', 'description' => '', 'folder_id' => $folder?->id,
    ]);

    return File::query()->latest('id')->firstOrFail();
}

test('folders are created with a materialized path and depth is capped', function () {
    $this->actingAs($this->admin);

    $this->post('/folders', ['name' => 'Root'])->assertRedirect();
    $root = Folder::query()->where('name', 'Root')->sole();
    expect($root->path)->toBe('/')
        ->and(ActivityLog::query()->where('action', Action::FolderCreated)->exists())->toBeTrue();

    $this->post('/folders', ['name' => 'Child', 'parent_id' => $root->id])->assertRedirect();
    $child = Folder::query()->where('name', 'Child')->sole();
    expect($child->path)->toBe("/{$root->id}/");

    // Build to the depth cap.
    $parent = $root;
    for ($i = 0; $i < 12; $i++) {
        $next = Folder::query()->where('parent_id', $parent->id)->where('name', 'L')->first();
        $response = $this->post('/folders', ['name' => 'L', 'parent_id' => $parent->id]);
        if ($response->getSession()->has('errors')) {
            expect($i)->toBeGreaterThan(5); // hit the cap somewhere reasonable
            break;
        }
        $parent = Folder::query()->where('parent_id', $parent->id)->where('name', 'L')->latest('id')->first();
    }
});

test('moving into a descendant is rejected and subtree paths are recomputed', function () {
    $this->actingAs($this->admin);
    $a = makeFolder('A');
    $b = makeFolder('B', $a);
    $c = makeFolder('C', $b);

    // A cannot move into C (its own descendant).
    $this->patch("/folders/{$a->id}/move", ['parent_id' => $c->id])->assertSessionHasErrors('parent_id');

    // Move B (with C under it) to root; paths update.
    $this->patch("/folders/{$b->id}/move", ['parent_id' => null])->assertRedirect();
    expect($b->refresh()->path)->toBe('/')
        ->and($c->refresh()->path)->toBe("/{$b->id}/");
});

test('a file is reparented by the drag-move endpoint without a name payload', function () {
    $this->actingAs($this->admin);
    $folder = makeFolder('Docs');
    $file = fileInFolder($this->admin, null); // loose at root

    // The drag sends only folder_id — the plain update() would reject this
    // for a missing name; move() accepts it.
    $this->patch("/files/{$file->id}/move", ['folder_id' => $folder->id])->assertRedirect();
    expect($file->refresh()->folder_id)->toBe($folder->id);

    // Drop on the Library breadcrumb (root) sends null.
    $this->patch("/files/{$file->id}/move", ['folder_id' => null])->assertRedirect();
    expect($file->refresh()->folder_id)->toBeNull();
});

test('deleting a folder cascade-soft-deletes its subtree and files together', function () {
    $this->actingAs($this->admin);
    $a = makeFolder('A');
    $b = makeFolder('B', $a);
    $fileA = fileInFolder($this->admin, $a);
    $fileB = fileInFolder($this->admin, $b);

    $this->delete("/folders/{$a->id}")->assertRedirect();

    expect(Folder::query()->find($a->id))->toBeNull()
        ->and(Folder::query()->find($b->id))->toBeNull()
        ->and(File::query()->find($fileA->id))->toBeNull()
        ->and(File::query()->find($fileB->id))->toBeNull()
        // All recoverable.
        ->and(Folder::withTrashed()->find($b->id))->not->toBeNull()
        ->and(File::withTrashed()->find($fileB->id))->not->toBeNull();
});

test('a client sees a folder only when it or an ancestor is shared, with live subtree access', function () {
    $client = User::factory()->client()->create();
    $parent = makeFolder('Parent');
    $child = makeFolder('Child', $parent);
    $before = fileInFolder($this->admin, $child, 'before.pdf');

    // Not shared yet: nothing visible.
    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 0)->has('files', 0),
    );
    $this->actingAs($client)->get("/files/{$before->id}/download")->assertForbidden();

    // Share the parent → child folder + its files become accessible.
    $this->actingAs($this->admin)->post("/folders/{$parent->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('folders', 1)->where('folders.0.name', 'Parent'),
    );
    $this->actingAs($client)->get('/my-files?folder='.$child->id)->assertInertia(
        fn (AssertableInertia $page) => $page->where('folder.name', 'Child')->has('files', 1),
    );
    $this->actingAs($client)->get("/files/{$before->id}/download")->assertOk();

    // Live: a file added AFTER sharing is immediately accessible.
    $after = fileInFolder($this->admin, $child, 'after.pdf');
    $this->actingAs($client)->get("/files/{$after->id}/download")->assertOk();

    // Un-sharing revokes the whole subtree.
    $this->actingAs($this->admin)->delete("/folders/{$parent->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($client)->get("/files/{$after->id}/download")->assertForbidden();
});

test('a directly-assigned file in an unshared folder shows loose, never leaking the folder name', function () {
    $client = User::factory()->client()->create();
    $secret = makeFolder('Internal Sorting');
    $file = fileInFolder($this->admin, $secret, 'invoice.pdf');

    // Assign the FILE directly (not the folder).
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $response = $this->actingAs($client)->get('/my-files');
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 1)
            ->where('files.0.name', 'invoice')
            ->has('folders', 0),
    );

    // Downloadable, but the folder name appears nowhere.
    $this->actingAs($client)->get("/files/{$file->id}/download")->assertOk();
    expect((string) $response->getContent())->not->toContain('Internal Sorting');
});

test('the client portal search is global, flat, and never leaks an unshared folder name', function () {
    $client = User::factory()->client()->create();

    // A shared folder with a file, and an unshared internal folder holding a
    // file assigned directly to the client.
    $shared = makeFolder('Shared Reports');
    fileInFolder($this->admin, $shared, 'quarterly-alpha.pdf');
    $this->actingAs($this->admin)->post("/folders/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $secret = makeFolder('Internal Sorting');
    $loose = fileInFolder($this->admin, $secret, 'invoice-beta.pdf');
    $this->actingAs($this->admin)->post("/files/{$loose->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    // Searching flattens across the shared subtree; breadcrumb + folder context drop.
    $this->actingAs($client)->get('/my-files?search=quarterly')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('searching', true)
        ->where('breadcrumb', [])
        ->has('files', 1)
        ->where('files.0.name', 'quarterly-alpha')
        ->has('pagination'));

    // The directly-assigned file is findable, but its unshared folder's name
    // is nowhere in the payload.
    $response = $this->actingAs($client)->get('/my-files?search=invoice');
    $response->assertInertia(fn (AssertableInertia $page) => $page->has('files', 1)->where('files.0.name', 'invoice-beta'));
    expect((string) $response->getContent())->not->toContain('Internal Sorting');
});

test('folder ownership splits own from others for edit and delete', function () {
    $uploader = User::factory()->role(SystemRole::Uploader)->create();
    $own = makeFolder('Uploader Folder');
    $own->forceFill(['created_by' => $uploader->id])->save();
    $others = makeFolder('Admin Folder');
    $others->forceFill(['created_by' => $this->admin->id])->save();

    $this->actingAs($uploader);
    $this->patch("/folders/{$own->id}", ['name' => 'Renamed'])->assertRedirect();
    $this->patch("/folders/{$others->id}", ['name' => 'Nope'])->assertForbidden();
    $this->delete("/folders/{$others->id}")->assertForbidden();
});

// Issue #1645, end to end through the screen the report came from: a deleted
// folder used to keep its public URL, so the name could never be used again
// and the error named a row the interface will not show.
test('a deleted folder gives its name and its public URL back', function () {
    $this->actingAs($this->admin);

    $this->post('/folders', ['name' => 'Quarterly', 'public' => true, 'slug' => 'quarterly'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $first = Folder::query()->where('name', 'Quarterly')->sole();
    $this->delete("/folders/{$first->id}")->assertRedirect();

    $this->post('/folders', ['name' => 'Quarterly', 'public' => true, 'slug' => 'quarterly'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $second = Folder::query()->where('name', 'Quarterly')->sole();
    expect($second->id)->not->toBe($first->id)
        ->and($second->slug)->toBe('quarterly');
});

test('creating folders requires create_own_folders', function () {
    // Account Manager lacks create_own_folders in the v1 default set.
    $manager = User::factory()->role(SystemRole::AccountManager)->create();
    $this->actingAs($manager)->post('/folders', ['name' => 'X'])->assertForbidden();
});

test('creating folders also requires upload, even for staff', function () {
    // A folder nobody can put anything in isn't useful on its own — same
    // rule the client portal's MyFoldersController::store() enforces.
    $role = Role::query()->create(['name' => 'Folders Without Upload', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'create_own_folders']);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->post('/folders', ['name' => 'Nope'])->assertForbidden();
    expect(Folder::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('the staff library scope is the single visibility filter (returns all today)', function () {
    makeFolder('One');
    makeFolder('Two');
    $scope = app(StaffLibraryScope::class);

    expect($scope->folders($this->admin)->count())->toBe(2);
});

test('clients cannot reach folder management; GET redirects home, mutations 403', function () {
    $client = User::factory()->client()->create();
    $folder = makeFolder('Locked');

    $this->actingAs($client);
    $this->get("/folders/{$folder->id}")->assertRedirect(route('dashboard'));
    $this->post('/folders', ['name' => 'Nope'])->assertForbidden();
    $this->delete("/folders/{$folder->id}")->assertForbidden();
});

test('folder log entries link to the folder view for authorized staff', function () {
    $this->actingAs($this->admin)->post('/folders', ['name' => 'Linked']);
    $folder = Folder::query()->where('name', 'Linked')->sole();

    $this->get('/activity?action=folder.created')->assertInertia(
        fn (AssertableInertia $page) => $page->where('entries.0.subject_url', '/files?folder='.$folder->id),
    );
});
