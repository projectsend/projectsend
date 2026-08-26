<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // A folder shared with a client the manager below is not assigned to:
    // in their library it does not exist, and everything in its subtree
    // belongs to that other client.
    $this->stranger = User::factory()->client()->create(['name' => 'Not Mine']);
    $this->strangersFolder = makeFolder('Strangers');
    $this->actingAs($this->admin)
        ->post("/folders/{$this->strangersFolder->id}/assignments", ['type' => 'client', 'id' => $this->stranger->id]);

    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->manager->assignedClients()->sync([$this->mine->id]);

    $this->myFolder = makeFolder('Mine');
    $this->actingAs($this->admin)
        ->post("/folders/{$this->myFolder->id}/assignments", ['type' => 'client', 'id' => $this->mine->id]);
});

/** The opening request of the resumable flow, which is where the folder is named. */
function beginChunkedUpload(int $folderId): array
{
    return ['filename' => 'resumable.pdf', 'size' => 2048, 'type' => 'application/pdf', 'folder_id' => $folderId];
}

/** @return list<string> */
function folderOptionNames(mixed $options): array
{
    return collect($options)->pluck('name')->all();
}

test('a scoped manager cannot upload into a folder outside their library over the web route', function () {
    $this->actingAs($this->manager)->post('/files', [
        'file' => UploadedFile::fake()->create('smuggled.pdf', 12, 'application/pdf'),
        'name' => 'Smuggled',
        'description' => '',
        'folder_id' => $this->strangersFolder->id,
    ])->assertForbidden();

    expect(File::query()->where('name', 'Smuggled')->exists())->toBeFalse();
});

test('a scoped manager cannot upload into a folder outside their library over the API', function () {
    $token = $this->manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->create('smuggled.pdf', 12, 'application/pdf'),
        'name' => 'Smuggled',
        'folder_id' => $this->strangersFolder->id,
    ], ['Accept' => 'application/json'])->assertForbidden();

    expect(File::query()->where('name', 'Smuggled')->exists())->toBeFalse();
});

test('a scoped manager cannot open a chunked upload into a folder outside their library', function () {
    // The production path for both surfaces: files/create.tsx posts here,
    // not to files.store.
    $this->actingAs($this->manager)
        ->postJson('/uploads', beginChunkedUpload($this->strangersFolder->id))
        ->assertForbidden();

    $token = $this->manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/uploads', beginChunkedUpload($this->strangersFolder->id))
        ->assertForbidden();
});

test('a file landing in that folder would be the stranger content, which is why the folder is the check', function () {
    // The stranger is never assigned anything here. Landing a file inside
    // a folder shared with them is the whole of it: File::scopeVisibleToClient
    // reads the folder subtree, so the file is theirs the moment it is
    // written, without an assignment row ever being touched. This is the
    // outcome the three refusals above prevent.
    $planted = uploadNamedFile($this->admin, 'planted', $this->strangersFolder->id);

    expect(File::query()->whereKey($planted->id)->visibleToClient($this->stranger)->exists())->toBeTrue();
});

test('a scoped manager still uploads into a folder of their own client, on every path', function () {
    $this->actingAs($this->manager)->post('/files', [
        'file' => UploadedFile::fake()->create('ok.pdf', 12, 'application/pdf'),
        'name' => 'Allowed',
        'description' => '',
        'folder_id' => $this->myFolder->id,
    ])->assertRedirect();

    expect(File::query()->where('name', 'Allowed')->value('folder_id'))->toBe($this->myFolder->id);

    $this->actingAs($this->manager)->postJson('/uploads', beginChunkedUpload($this->myFolder->id))->assertOk();

    // And their own folder, which is theirs by creation rather than
    // through any client assignment.
    $this->actingAs($this->manager);
    $ownFolder = app(FolderService::class)->create('My Own', null);

    $this->actingAs($this->manager)->postJson('/uploads', beginChunkedUpload($ownFolder->id))->assertOk();

    // Token last: a bearer request swaps the default guard for the rest of
    // the test, which a following session request cannot recover from.
    $token = $this->manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->create('ok.pdf', 12, 'application/pdf'),
        'name' => 'Allowed via API',
        'folder_id' => $this->myFolder->id,
    ], ['Accept' => 'application/json'])->assertStatus(201);
});

test('an unscoped staff member reaches every folder exactly as before', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('ok.pdf', 12, 'application/pdf'),
        'name' => 'Admin Upload',
        'description' => '',
        'folder_id' => $this->strangersFolder->id,
    ])->assertRedirect();

    expect(File::query()->where('name', 'Admin Upload')->value('folder_id'))->toBe($this->strangersFolder->id);

    $this->actingAs($this->admin)->postJson('/uploads', beginChunkedUpload($this->strangersFolder->id))->assertOk();
});

test('the client side of uploadableBy is untouched', function () {
    // The same helper answers for clients, whose branch is a different
    // rule entirely: ownership, or a public folder opting into client
    // uploads. A client is never client-scoped, so nothing the staff
    // branch now asks can reach them. Both directions pinned.
    $client = User::factory()->client()->create();

    $this->actingAs($client);
    $ownFolder = app(FolderService::class)->create('Client Folder', null);

    expect(Folder::uploadableBy($client, $ownFolder))->toBeTrue()
        ->and(Folder::uploadableBy($client, $this->strangersFolder))->toBeFalse();

    $this->actingAs($client)->postJson('/uploads', beginChunkedUpload($ownFolder->id))->assertOk();
    $this->actingAs($client)->postJson('/uploads', beginChunkedUpload($this->strangersFolder->id))->assertForbidden();
});

test('the folder pickers offer a scoped manager only their own folders', function () {
    $this->actingAs($this->manager)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'folder_options',
            fn ($options) => in_array('Mine', folderOptionNames($options), true)
                && ! in_array('Strangers', folderOptionNames($options), true),
        ),
    );

    $file = uploadNamedFile($this->manager, 'mine-to-edit');

    $this->actingAs($this->manager)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'folder_options',
            fn ($options) => in_array('Mine', folderOptionNames($options), true)
                && ! in_array('Strangers', folderOptionNames($options), true),
        ),
    );
});

test('an unscoped staff member still sees the whole tree in both pickers', function () {
    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'folder_options',
            fn ($options) => in_array('Mine', folderOptionNames($options), true)
                && in_array('Strangers', folderOptionNames($options), true),
        ),
    );

    $file = uploadNamedFile($this->admin, 'admins');

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'folder_options',
            fn ($options) => in_array('Mine', folderOptionNames($options), true)
                && in_array('Strangers', folderOptionNames($options), true),
        ),
    );
});
