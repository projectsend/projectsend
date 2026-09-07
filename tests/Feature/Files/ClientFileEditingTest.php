<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

/** A client whose role carries exactly the given permission keys. */
function clientWithPermissions(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Client Role '.Str::random(6)]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->client()->create(['role_id' => $role->id]);
}

/** A stored file owned by $owner, with real bytes on the fake disk. */
function ownedFile(User $owner, array $overrides = []): File
{
    $path = 'uploads/'.Str::uuid()->toString().'.pdf';
    Storage::disk('files')->put($path, 'hello-world');

    return File::factory()->create([
        'uploaded_by' => $owner->id,
        'name' => 'report',
        'original_name' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 11,
        ...$overrides,
        'path' => $path,
        'disk' => 'files',
    ]);
}

/** The full form payload the portal editor posts, overridable per test. */
function clientEditPayload(array $overrides = []): array
{
    return array_merge(['name' => 'renamed'], $overrides);
}

/*
|--------------------------------------------------------------------------
| The rule
|--------------------------------------------------------------------------
|
| A client owns what they uploaded, and owning it is what lets them edit
| and delete it — subject to the same per-field keys staff are subject to.
*/

test('a client with edit_files can rename their own upload', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['description' => 'now with a description']))
        ->assertRedirect();

    expect($file->refresh()->name)->toBe('renamed')
        ->and($file->description)->toBe('now with a description');
});

test('a client with delete_files can delete their own upload, and the bytes go with it', function () {
    $client = clientWithPermissions(['delete_files']);
    $file = ownedFile($client);

    expect(app(ClientStorageUsage::class)->usedBytes($client))->toBe(11);

    $this->actingAs($client)->delete("/my-files/{$file->id}")->assertRedirect('/my-files');

    expect(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue();
    Storage::disk('files')->assertMissing($file->path);

    // The quota frees by exactly what the disk did. These two have to agree
    // or a client pays rent on bytes that are gone — nothing ever
    // forceDelete()s a File row, so "temporarily" would mean forever.
    expect(app(ClientStorageUsage::class)->usedBytes($client))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Ownership is the boundary
|--------------------------------------------------------------------------
*/

test('a client cannot edit or delete another client\'s file', function () {
    $client = clientWithPermissions(['edit_files', 'delete_files']);
    $stranger = User::factory()->client()->create();
    $file = ownedFile($stranger);

    $this->actingAs($client)->patch("/my-files/{$file->id}", clientEditPayload())->assertForbidden();
    $this->actingAs($client)->delete("/my-files/{$file->id}")->assertForbidden();

    expect($file->refresh()->name)->toBe('report')
        ->and($file->trashed())->toBeFalse();
});

// The keys exist for staff, where "others' files" is a real category. A
// client has no others' files — only files somebody showed them — so these
// two must buy nothing at all. FilePolicy's client branch never reads them.
test('edit_others_files and delete_others_files buy a client nothing', function () {
    $client = clientWithPermissions([
        'edit_files', 'delete_files', 'edit_others_files', 'delete_others_files',
    ]);
    $stranger = User::factory()->client()->create();
    $file = ownedFile($stranger);

    $this->actingAs($client)->patch("/my-files/{$file->id}", clientEditPayload())->assertForbidden();
    $this->actingAs($client)->delete("/my-files/{$file->id}")->assertForbidden();
});

// Being shown a file is not being given it. This is the case a client is
// most likely to try, because the file is sitting right there in their list.
test('a client cannot edit a file staff merely shared with them', function () {
    $client = clientWithPermissions(['edit_files', 'delete_files']);
    $file = ownedFile($this->admin);

    $this->actingAs($this->admin)
        ->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertRedirect();

    // Visible to them...
    $this->actingAs($client)->get('/my-files')->assertOk();

    // ...and still not theirs.
    $this->actingAs($client)->patch("/my-files/{$file->id}", clientEditPayload())->assertForbidden();
    $this->actingAs($client)->delete("/my-files/{$file->id}")->assertForbidden();
});

test('a client without edit_files cannot edit their own upload', function () {
    $client = clientWithPermissions([]);
    $file = ownedFile($client);

    $this->actingAs($client)->patch("/my-files/{$file->id}", clientEditPayload())->assertForbidden();

    expect($file->refresh()->name)->toBe('report');
});

test('a client without delete_files cannot delete their own upload', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);

    $this->actingAs($client)->delete("/my-files/{$file->id}")->assertForbidden();

    expect($file->refresh()->trashed())->toBeFalse();
});

test('staff cannot reach the portal routes, and a client cannot reach the staff ones', function () {
    $client = clientWithPermissions(['edit_files', 'delete_files']);
    $file = ownedFile($client);

    // The staff editor is `staff` middleware, not a permission — so holding
    // the key changes nothing. A GET is sent home rather than refused
    // (EnsureStaff: staff pages are not part of a client's world); the
    // writes are a hard 403.
    $this->actingAs($client)->get("/files/{$file->id}")->assertRedirect(route('dashboard'));
    $this->actingAs($client)->patch("/files/{$file->id}", clientEditPayload())->assertForbidden();
    $this->actingAs($client)->delete("/files/{$file->id}")->assertForbidden();

    // And the portal route refuses a staff account rather than quietly
    // giving it a second way to edit.
    $this->actingAs($this->admin)->patch("/my-files/{$file->id}", clientEditPayload())->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Per-field keys
|--------------------------------------------------------------------------
|
| Lacking the key leaves the field alone and the rest of the edit still
| saves — the rule the staff editor and the API already follow. A client
| must not be the one surface where a missing key fails the request.
*/

test('a client without upload_public cannot publish, and the rename still saves', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['public' => true]))
        ->assertRedirect();

    expect($file->refresh()->public)->toBeFalse()
        ->and($file->name)->toBe('renamed');
});

test('a client with upload_public can publish their own upload', function () {
    $client = clientWithPermissions(['edit_files', 'upload_public']);
    $file = ownedFile($client);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['public' => true]))
        ->assertRedirect();

    expect($file->refresh()->public)->toBeTrue()
        ->and($file->slug)->not->toBe('');
});

// The slug is derived, never chosen. An installation-wide unique slug a
// client picks is a name to squat and an oracle to probe with.
test('a client cannot choose the public slug', function () {
    $client = clientWithPermissions(['edit_files', 'upload_public']);
    $file = ownedFile($client);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload([
            'public' => true,
            'slug' => 'front-page',
        ]))
        ->assertRedirect();

    expect($file->refresh()->slug)->not->toBe('front-page');
});

test('a client without set_file_categories cannot categorise', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);
    $category = Category::query()->create(['name' => 'Docs']);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['categories' => [$category->id]]))
        ->assertRedirect();

    expect($file->refresh()->categories)->toHaveCount(0);
});

test('a client without set_file_expiration_date cannot set an expiry', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['expires_at' => now()->addWeek()->toDateString()]))
        ->assertRedirect();

    expect($file->refresh()->expires_at)->toBeNull();
});

test('a client without limit_downloads cannot cap downloads', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['download_limit' => 3]))
        ->assertRedirect();

    expect($file->refresh()->download_limit)->toBeNull();
});

test('a client holding the keys can set an expiry, categories and a download cap', function () {
    $client = clientWithPermissions([
        'edit_files', 'set_file_expiration_date', 'set_file_categories', 'limit_downloads',
    ]);
    $file = ownedFile($client);
    $category = Category::query()->create(['name' => 'Docs']);

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload([
            'expires_at' => now()->addWeek()->toDateString(),
            'categories' => [$category->id],
            'download_limit' => 3,
        ]))
        ->assertRedirect();

    $file->refresh();

    expect($file->expires_at)->not->toBeNull()
        ->and($file->categories)->toHaveCount(1)
        ->and($file->download_limit)->toBe(3);
});

/*
|--------------------------------------------------------------------------
| The folder trap
|--------------------------------------------------------------------------
|
| StaffLibraryScope::allowsFolder() returns true for anyone who is not
| client-*scoped* staff, and User::isClientScoped() is false for every
| client. A client reaching the staff guard would be handed every folder on
| the installation. These pin that they never do.
*/

test('a client cannot move their file into a folder they could not upload to', function () {
    $client = clientWithPermissions(['edit_files']);
    $file = ownedFile($client);
    $staffOnly = makeFolder('Internal');

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['folder_id' => $staffOnly->id]))
        ->assertForbidden();

    expect($file->refresh()->folder_id)->toBeNull();
});

test('a client cannot move their file into another client\'s folder', function () {
    $client = clientWithPermissions(['edit_files']);
    $stranger = User::factory()->client()->create();
    $file = ownedFile($client);

    $this->actingAs($stranger)->post('/my-folders', ['name' => 'Theirs'])->assertRedirect();
    $theirs = Folder::query()->where('name', 'Theirs')->sole();

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['folder_id' => $theirs->id]))
        ->assertForbidden();

    expect($file->refresh()->folder_id)->toBeNull();
});

test('a client can move their file into a folder they created', function () {
    $client = clientWithPermissions(['edit_files', 'create_own_folders', 'upload']);
    $file = ownedFile($client);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine'])->assertRedirect();
    $mine = Folder::query()->where('name', 'Mine')->sole();

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload(['folder_id' => $mine->id]))
        ->assertRedirect();

    expect($file->refresh()->folder_id)->toBe($mine->id);
});

/*
|--------------------------------------------------------------------------
| What the payload must not reach
|--------------------------------------------------------------------------
*/

test('a client cannot hand their file to somebody else, or repoint its bytes', function () {
    $client = clientWithPermissions(['edit_files']);
    $stranger = User::factory()->client()->create();
    $file = ownedFile($client);
    $originalPath = $file->path;

    $this->actingAs($client)
        ->patch("/my-files/{$file->id}", clientEditPayload([
            'uploaded_by' => $stranger->id,
            'path' => 'uploads/somebody-elses-file.pdf',
            'disk' => 'nonexistent-disk',
            'size' => 999999999,
        ]))
        ->assertRedirect();

    $file->refresh();

    expect($file->uploaded_by)->toBe($client->id)
        ->and($file->path)->toBe($originalPath)
        ->and($file->disk)->toBe('files')
        ->and($file->size)->toBe(11);
});

/*
|--------------------------------------------------------------------------
| The page and the rows
|--------------------------------------------------------------------------
|
| Hiding a control is a courtesy, never the enforcement — every assertion
| above already proves the server refuses. These pin that a client is not
| shown a switch that would silently do nothing.
*/

test('the editor opens for an owner and refuses everyone else', function () {
    $client = clientWithPermissions(['edit_files']);
    $stranger = User::factory()->client()->create();
    $file = ownedFile($client);

    $this->actingAs($client)->get("/my-files/{$file->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/edit-file')
            ->where('file.name', 'report'));

    $this->actingAs($stranger)->get("/my-files/{$file->id}/edit")->assertForbidden();

    // And a staff account gets the staff editor, not this one.
    $this->actingAs($this->admin)->get("/my-files/{$file->id}/edit")->assertNotFound();
});

test('the editor offers only the fields the role actually grants', function () {
    $bare = clientWithPermissions(['edit_files']);
    $file = ownedFile($bare);

    $this->actingAs($bare)->get("/my-files/{$file->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('can_publish', false)
            ->where('can_set_expiration', false)
            ->where('can_set_categories', false)
            ->where('can_limit_downloads', false)
            ->where('can_delete', false),
    );

    $full = clientWithPermissions([
        'edit_files', 'delete_files', 'upload_public',
        'set_file_expiration_date', 'set_file_categories', 'limit_downloads',
    ]);
    $theirs = ownedFile($full);

    $this->actingAs($full)->get("/my-files/{$theirs->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('can_publish', true)
            ->where('can_set_expiration', true)
            ->where('can_set_categories', true)
            ->where('can_limit_downloads', true)
            ->where('can_delete', true),
    );
});

// The folder picker must not offer a destination the save would refuse —
// otherwise a client picks a folder, saves, and gets a 403 for choosing
// something they were shown.
test('the folder picker offers only folders the client could upload to', function () {
    $client = clientWithPermissions(['edit_files', 'create_own_folders', 'upload']);
    $file = ownedFile($client);
    makeFolder('Internal');

    $this->actingAs($client)->post('/my-folders', ['name' => 'Mine'])->assertRedirect();

    $this->actingAs($client)->get("/my-files/{$file->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('folders', 1)
            ->where('folders.0.name', 'Mine'),
    );
});

test('file rows carry the same answer the server will give', function () {
    $client = clientWithPermissions(['edit_files', 'delete_files']);
    $own = ownedFile($client, ['name' => 'mine']);
    $shared = ownedFile($this->admin, ['name' => 'theirs']);

    $this->actingAs($this->admin)
        ->post("/files/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertRedirect();

    $this->actingAs($client)->get('/my-files')->assertInertia(function (AssertableInertia $page) {
        $files = collect($page->toArray()['props']['files'])->keyBy('name');

        expect($files['mine']['can_update'])->toBeTrue()
            ->and($files['mine']['can_delete'])->toBeTrue()
            // Shared with them, and still not theirs — the same answer the
            // PATCH gives, so the row never offers what the save refuses.
            ->and($files['theirs']['can_update'])->toBeFalse()
            ->and($files['theirs']['can_delete'])->toBeFalse();
    });
});

test('a client without the keys sees no controls on their own rows', function () {
    $client = clientWithPermissions([]);
    ownedFile($client, ['name' => 'mine']);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files.0.can_update', false)
            ->where('files.0.can_delete', false),
    );
});
