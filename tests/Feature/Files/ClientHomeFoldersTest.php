<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\ClientAccounts;
use App\Modules\Files\Folders\ClientHomeFolders;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * A folder per client, standing in for the root.
 *
 * The rule this file exists to hold still is in the third test: the home is
 * where a client's own things go, NOT a wall around them. Every share that
 * worked before has to keep working, or this stopped being a tidying-up
 * feature and became a permissions change.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    // Explicitly, never assumed: the Settings cache outlives a
    // RefreshDatabase rollback, so a test that wants the default has to say
    // so. See the settings-cache note in the test helpers.
    app(Settings::class)->set(Setting::ClientsHomeFolders, false);
});

function enableHomeFolders(): void
{
    app(Settings::class)->set(Setting::ClientsHomeFolders, true);
}

function shareFolderWithClient(Folder $folder, User $client): void
{
    FolderAssignment::query()->create([
        'folder_id' => $folder->id,
        'assignable_type' => $client->getMorphClass(),
        'assignable_id' => $client->id,
    ]);
}

function clientWhoCanUpload(string $name = 'Acme Ltd'): User
{
    $client = User::factory()->client()->create(['name' => $name]);

    foreach ([Permission::Upload, Permission::CreateOwnFolders] as $permission) {
        RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => $permission->value]);
    }

    return $client->refresh();
}

test('a new client gets a folder named after them, and only when the setting is on', function () {
    $before = User::factory()->client()->create(['name' => 'Before Ltd']);
    expect(app(ClientHomeFolders::class)->for($before))->toBeNull();

    enableHomeFolders();

    $after = User::factory()->client()->create(['name' => 'After Ltd']);
    $home = app(ClientHomeFolders::class)->for($after);

    expect($home)->not->toBeNull()
        ->and($home->name)->toBe('After Ltd')
        // At the root, which is the entire point: this is what an
        // administrator opening /files is meant to see.
        ->and($home->parent_id)->toBeNull()
        // Owned by the client, because that is how scopeVisibleToClient
        // grants them a folder -- no assignment row to keep in step.
        ->and($home->created_by)->toBe($after->id);

    // Staff who made the account did not accidentally become the owner.
    expect($home->created_by)->not->toBe($this->admin->id);
});

test('the folder is created however the account was made, not just on one screen', function () {
    enableHomeFolders();

    // Through the service the staff screens, the API and the control plane
    // all share -- a path that never calls a controller.
    $client = app(ClientAccounts::class)->create(
        name: 'Service Made',
        email: 'service@example.test',
        password: 'a-sufficiently-long-password',
    );

    expect(app(ClientHomeFolders::class)->for($client))->not->toBeNull();
});

test('the home is a default location, never a boundary', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();

    // A folder staff shared with this client, nowhere near their home.
    $shared = app(FolderService::class)->create('Contracts', null);
    shareFolderWithClient($shared, $client);

    $this->actingAs($client)->get('/my-files')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page
            // The shared folder is still right there. If this ever fails,
            // the feature has started revoking access rather than
            // organising it.
            ->where('folders', fn ($folders) => collect($folders)->pluck('name')->contains('Contracts')),
    );
});

test('the client sees inside their folder, not the folder itself', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    $home = app(ClientHomeFolders::class)->for($client);

    $inside = app(FolderService::class)->create('Invoices', $home);
    $inside->update(['created_by' => $client->id]);

    $this->actingAs($client)->get('/my-files')->assertOk()->assertInertia(function (AssertableInertia $page) {
        $names = collect($page->toArray()['props']['folders'])->pluck('name');

        // Their own name is not information to them.
        expect($names)->toContain('Invoices')
            ->and($names)->not->toContain('Acme Ltd');
    });
});

test('a folder the client creates without choosing a parent lands in their home', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    $home = app(ClientHomeFolders::class)->for($client);

    $this->actingAs($client)->post('/my-folders', ['name' => 'Receipts'])->assertRedirect();

    expect(Folder::query()->where('name', 'Receipts')->sole()->parent_id)->toBe($home->id);
});

test('the client cannot rename or delete their own home folder', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    $home = app(ClientHomeFolders::class)->for($client);

    // They own it -- created_by is them -- so without the guard both of
    // these would be allowed by ownership alone.
    expect($home->isOwnedBy($client))->toBeTrue();

    $this->actingAs($client)->patch("/my-folders/{$home->id}", ['name' => 'Something else'])->assertForbidden();
    $this->actingAs($client)->delete("/my-folders/{$home->id}")->assertForbidden();

    expect($home->refresh()->name)->toBe('Acme Ltd');
});

test('renaming the client renames the folder, even over a hand-typed name', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    $home = app(ClientHomeFolders::class)->for($client);

    // Somebody renamed it by hand. The product decision is that the
    // account still wins: a folder called "Acme Ltd" under an account now
    // called something else misleads the administrator it exists for.
    $home->update(['name' => 'Hand typed']);

    $client->update(['name' => 'Acme Holdings']);

    expect($home->refresh()->name)->toBe('Acme Holdings');
});

test('the backfill is idempotent and reports what it did', function () {
    User::factory()->client()->count(3)->create();
    enableHomeFolders();
    $fresh = User::factory()->client()->create();

    $first = app(ClientHomeFolders::class)->backfill();

    // Four clients; the one created after the switch already had one.
    expect($first['total'])->toBe(4)
        ->and($first['created'])->toBe(3)
        ->and($first['existing'])->toBe(1);

    // Pressing the button twice must converge, not accumulate.
    $second = app(ClientHomeFolders::class)->backfill();

    expect($second['created'])->toBe(0)
        ->and($second['existing'])->toBe(4)
        ->and(Folder::query()->whereNotNull('home_for_user_id')->count())->toBe(4);

    expect(app(ClientHomeFolders::class)->for($fresh))->not->toBeNull();
});

test('the backfill button is refused while the setting is off', function () {
    User::factory()->client()->create();

    $this->actingAs($this->admin)->post('/system/settings/clients/home-folders')->assertForbidden();

    expect(Folder::query()->whereNotNull('home_for_user_id')->count())->toBe(0);
});

test('the settings screen says how many clients are still without a folder', function () {
    enableHomeFolders();
    User::factory()->client()->count(2)->create();
    // Created while the setting was on, so it already has one.
    expect(Folder::query()->whereNotNull('home_for_user_id')->count())->toBe(2);

    User::factory()->client()->count(3)->create();
    Folder::query()->whereNotNull('home_for_user_id')->limit(3)->delete();

    $this->actingAs($this->admin)->get('/system/settings/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->where('clients_without_home', 3),
    );
});

test('a client upload with no folder chosen lands in their home', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    $home = app(ClientHomeFolders::class)->for($client);

    $this->actingAs($client);

    // The real intake path, with no folder_id in the body -- which is what
    // the portal sends when the client just picks a file and uploads.
    $session = $this->postJson('/uploads', [
        'filename' => 'note.txt',
        'size' => 11,
        'type' => 'text/plain',
    ])->assertOk()->json('uploadId');

    // Parts go to a signed URL, not to the bare path -- see the portal's
    // own upload flow.
    $url = $this->getJson("/uploads/{$session}/parts/1/sign")->assertOk()->json('url');
    $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'hello-world');
    $this->postJson("/uploads/{$session}/complete")->assertOk();

    expect(File::query()->where('uploaded_by', $client->id)->sole()->folder_id)->toBe($home->id);
});

test('turning the setting off leaves an existing home working', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    $home = app(ClientHomeFolders::class)->for($client);

    app(Settings::class)->set(Setting::ClientsHomeFolders, false);

    // The folder is real and holds real files. Pretending it is gone would
    // strand them somewhere no listing looks.
    expect(app(ClientHomeFolders::class)->for($client->refresh())->id)->toBe($home->id);
});

test('a client editing their own file cannot send it to the library root', function () {
    // Reported by binghuo: choosing "No folder" in the portal's file editor
    // moved the file out of the client's home and into the administrator's
    // root, beside the staff folders — the exact mess the home folder
    // exists to end. Uploading and creating a folder already resolved an
    // absent folder to the home; the editor did not.
    enableHomeFolders();
    $client = clientWhoCanUpload();
    RolePermission::query()->create(['role_id' => $client->role_id, 'permission' => Permission::EditFiles->value]);
    $home = app(ClientHomeFolders::class)->for($client);

    $file = File::factory()->create(['uploaded_by' => $client->id, 'folder_id' => $home->id, 'name' => 'Theirs']);

    $this->actingAs($client)->patch("/my-files/{$file->id}", [
        'name' => 'Theirs',
        'folder_id' => null,
    ])->assertSessionHasNoErrors();

    expect($file->refresh()->folder_id)->toBe($home->id);
});

test('with no home folder, no folder still means no folder', function () {
    // The installation that never turned this on keeps what it had: a
    // client's file sits at the root of the library because that is where
    // every client's file sits there.
    app(Settings::class)->set(Setting::ClientsHomeFolders, false);
    $client = clientWhoCanUpload();
    RolePermission::query()->create(['role_id' => $client->role_id, 'permission' => Permission::EditFiles->value]);
    $folder = app(FolderService::class)->create('Somewhere', null);
    shareFolderWithClient($folder, $client);

    $file = File::factory()->create(['uploaded_by' => $client->id, 'folder_id' => $folder->id]);

    $this->actingAs($client)->patch("/my-files/{$file->id}", [
        'name' => 'Theirs',
        'folder_id' => null,
    ])->assertSessionHasNoErrors();

    expect($file->refresh()->folder_id)->toBeNull();
});

test('the editor offers no "no folder" where the client has a home', function () {
    enableHomeFolders();
    $client = clientWhoCanUpload();
    RolePermission::query()->create(['role_id' => $client->role_id, 'permission' => Permission::EditFiles->value]);
    $home = app(ClientHomeFolders::class)->for($client);

    $file = File::factory()->create(['uploaded_by' => $client->id, 'folder_id' => null]);

    $this->actingAs($client)->get("/my-files/{$file->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/edit-file')
            // The page hides the option and preselects this, so the form
            // cannot post the root even by accident.
            ->where('home_folder_id', $home->id),
    );
});
