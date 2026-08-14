<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
});

/**
 * A file created directly (bypassing the upload endpoint) and assigned to a
 * client, so filter/sort fixtures can control size/uploader/created_at
 * precisely without needing a real upload per row.
 */
function directFileForClient(User $client, string $name, int $sizeBytes, User $uploader, ?Carbon $createdAt = null): File
{
    $file = File::factory()->create([
        'name' => $name,
        'original_name' => "{$name}.pdf",
        'path' => "test/{$name}.pdf",
        'mime_type' => 'application/pdf',
        'size' => $sizeBytes,
        'checksum' => str_repeat('0', 64),
        'uploaded_by' => $uploader->id,
        'public' => false,
    ]);

    if ($createdAt !== null) {
        // Eloquent's create() always stamps created_at with now(), so a
        // precise fixture timestamp needs a raw update afterward.
        DB::table('files')->where('id', $file->id)->update(['created_at' => $createdAt]);
    }

    FileAssignment::query()->create([
        'file_id' => $file->id,
        'assignable_type' => (new User)->getMorphClass(),
        'assignable_id' => $client->id,
    ]);

    return $file;
}

test('uploading stores the file with persisted metadata and an audit entry', function () {
    $file = uploadDocumentFile($this->admin);

    expect($file->name)->toBe('contract')
        ->and($file->original_name)->toBe('contract.pdf')
        ->and($file->size)->toBe(512 * 1024)
        ->and($file->mime_type)->toBe('application/pdf')
        ->and(strlen($file->checksum))->toBe(64)
        ->and($file->uploaded_by)->toBe($this->admin->id);

    Storage::disk('files')->assertExists($file->path);
    expect(ActivityLog::query()->where('action', Action::FileUploaded)->where('subject_name', 'contract')->exists())->toBeTrue();
});

test('downloads answer with X-Accel-Redirect and never the bytes', function () {
    $file = uploadDocumentFile($this->admin);

    $response = $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $response->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path)
        ->assertHeader('Content-Disposition', 'attachment; filename="contract.pdf"');

    expect($response->getContent())->toBe('')
        ->and(ActivityLog::query()->where('action', Action::FileDownloaded)->exists())->toBeTrue();
});

test('the files listing shows how many times each file has been downloaded', function () {
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download");
    $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.downloads_count', 2),
    );
});

test('the files listing flags public files', function () {
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.public', false),
    );

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'public' => true, 'slug' => 'contract-public'])
        ->assertRedirect();

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.public', true),
    );
});

test('the files listing carries the uploader\'s name, account type, and role, distinguishing staff from clients', function () {
    uploadDocumentFile($this->admin);
    $staffFile = File::query()->latest('id')->firstOrFail();

    $client = User::factory()->client()->create(['name' => 'Jane Client']);
    $clientFile = File::factory()->create([
        'uploaded_by' => $client->id,
        'name' => 'client-doc',
        'original_name' => 'client-doc.pdf',
        'path' => 'test/client-doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
    ]);

    $response = $this->actingAs($this->admin)->get('/files');
    $props = json_decode(json_encode($response->viewData('page')), true)['props'];
    $rows = collect($props['files'])->keyBy('id');

    expect($rows->get($staffFile->id)['uploader'])->toBe([
        'name' => $this->admin->name,
        'type' => 'staff',
        'role' => 'System Administrator',
    ]);
    expect($rows->get($clientFile->id)['uploader'])->toBe([
        'name' => 'Jane Client',
        'type' => 'client',
        'role' => 'Client',
    ]);
});

test('the files listing carries a public_url only when the file has a real, live public page', function () {
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $file = uploadDocumentFile($this->admin);
    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.public_url', null),
    );

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'public' => true, 'slug' => 'contract-public'])
        ->assertRedirect();

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.public_url', route('public.file', ['public', 'contract-public'])),
    );

    // Expired public files 404 on their public page, so no link is offered.
    $file->update(['expires_at' => now()->subDay()]);
    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('files.0.public_url', null),
    );
});

test('the files listing carries a folder\'s public_url only for its own public flag, not one merely inherited', function () {
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $parent = makeFolder('Parent');
    $this->actingAs($this->admin)->patch("/folders/{$parent->id}", ['name' => 'Parent', 'public' => true, 'slug' => 'parent-folder'])
        ->assertRedirect();

    $child = makeFolder('Child', $parent);

    $response = $this->actingAs($this->admin)->get('/files?folder='.$parent->id);
    $props = json_decode(json_encode($response->viewData('page')), true)['props'];
    $childRow = collect($props['folders'])->firstWhere('name', 'Child');

    // Effectively public (badge-worthy) via the parent, but no page of its own.
    expect($childRow['public'])->toBeTrue()
        ->and($childRow['public_url'])->toBeNull();

    $this->actingAs($this->admin)->get('/files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('folders.0.public_url', route('public.folder', ['public', 'parent-folder'])),
    );
});

test('clients can only download files assigned to them, directly or via group', function () {
    $file = uploadDocumentFile($this->admin);
    $direct = User::factory()->client()->create();
    $viaGroup = User::factory()->client()->create();
    $outsider = User::factory()->client()->create();

    $group = Group::query()->create(['name' => 'Recipients', 'public' => false]);
    $group->members()->attach($viaGroup->id);

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $direct->id]);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $this->actingAs($direct)->get("/files/{$file->id}/download")->assertOk();
    $this->actingAs($viaGroup)->get("/files/{$file->id}/download")->assertOk();
    $this->actingAs($outsider)->get("/files/{$file->id}/download")->assertForbidden();
});

test('the client portal lists assigned files without ever naming groups', function () {
    $file = uploadDocumentFile($this->admin);
    $client = User::factory()->client()->create();
    $secretGroup = Group::query()->create(['name' => 'Slow Payers', 'public' => false]);
    $secretGroup->members()->attach($client->id);

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'group', 'id' => $secretGroup->id]);

    $response = $this->actingAs($client)->get('/my-files');

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/themes/default/my-files')
            ->has('files', 1)
            ->where('files.0.name', 'contract'),
    );

    // The private group's name must not appear anywhere in the payload.
    expect((string) $response->getContent())->not->toContain('Slow Payers');
});

test('the client portal filters by category', function () {
    $client = User::factory()->client()->create();
    $tagged = directFileForClient($client, 'tagged', 100, $this->admin);
    $plain = directFileForClient($client, 'plain', 100, $this->admin);

    $category = Category::query()->create(['name' => 'Contracts']);
    $tagged->categories()->attach($category->id);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('files', 2)->where('category', null),
    );

    $this->actingAs($client)->get("/my-files?category={$category->id}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('category', $category->id)
            ->has('files', 1)
            ->where('files.0.name', 'tagged'),
    );

    expect($plain)->not->toBeNull();
});

test('the client portal filters by owner: uploaded by me vs shared with me', function () {
    $client = User::factory()->client()->create();
    $ownFile = directFileForClient($client, 'own-upload', 100, $client);
    $sharedFile = directFileForClient($client, 'from-staff', 100, $this->admin);

    $this->actingAs($client)->get('/my-files?owner=mine')->assertInertia(
        fn (AssertableInertia $page) => $page->where('owner', 'mine')->has('files', 1)->where('files.0.name', 'own-upload'),
    );

    $this->actingAs($client)->get('/my-files?owner=shared')->assertInertia(
        fn (AssertableInertia $page) => $page->where('owner', 'shared')->has('files', 1)->where('files.0.name', 'from-staff'),
    );

    expect($ownFile->is($ownFile))->toBeTrue()->and($sharedFile->is($sharedFile))->toBeTrue();
});

test('the client portal sorts by name, size, and date in both directions', function () {
    $client = User::factory()->client()->create();
    directFileForClient($client, 'bravo', 300, $this->admin, now()->subDays(2));
    directFileForClient($client, 'alpha', 100, $this->admin, now()->subDays(1));
    directFileForClient($client, 'charlie', 200, $this->admin, now());

    $assertOrder = function (string $query, array $expectedNames) use ($client): void {
        $this->actingAs($client)->get("/my-files?{$query}")->assertInertia(function (AssertableInertia $page) use ($expectedNames) {
            foreach ($expectedNames as $index => $name) {
                $page->where("files.{$index}.name", $name);
            }

            return $page;
        });
    };

    $assertOrder('sort=name&direction=asc', ['alpha', 'bravo', 'charlie']);
    $assertOrder('sort=name&direction=desc', ['charlie', 'bravo', 'alpha']);
    $assertOrder('sort=size&direction=asc', ['alpha', 'charlie', 'bravo']);
    $assertOrder('sort=size&direction=desc', ['bravo', 'charlie', 'alpha']);
    $assertOrder('sort=date&direction=asc', ['bravo', 'alpha', 'charlie']);
    $assertOrder('sort=date&direction=desc', ['charlie', 'alpha', 'bravo']);
});

test('the client portal my-files page paginates and exposes filter/sort state', function () {
    $client = User::factory()->client()->create();
    for ($i = 1; $i <= 30; $i++) {
        directFileForClient($client, "file-{$i}", 100, $this->admin);
    }

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('files', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2)
            ->where('sort', 'date')
            ->where('direction', 'desc')
            ->where('owner', null)
            ->has('categories'),
    );

    $this->actingAs($client)->get('/my-files?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('files', 5)->where('pagination.page', 2),
    );
});

test('selecting the compact theme renders the compact my-files component', function () {
    app(Settings::class)->set(Setting::Theme, 'compact');
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->component('portal/themes/compact/my-files'),
    );
});

test('selecting the drive theme renders the drive my-files component', function () {
    app(Settings::class)->set(Setting::Theme, 'drive');
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->component('portal/themes/drive/my-files'),
    );
});

test('an unknown or unavailable stored theme falls back to default for the portal too', function () {
    app(Settings::class)->set(Setting::Theme, 'does-not-exist');
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->component('portal/themes/default/my-files'),
    );
});

test('selecting the gallery theme renders the gallery components for the portal', function () {
    app(Settings::class)->set(Setting::Theme, 'gallery');
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->component('portal/themes/gallery/my-files'),
    );
});

test('ownership permissions split own from others files', function () {
    $uploader = User::factory()->role(SystemRole::Uploader)->create();
    $own = uploadDocumentFile($uploader, 'mine.pdf');
    $others = uploadDocumentFile($this->admin, 'theirs.pdf');

    $this->actingAs($uploader);

    // The index row's can_delete mirrors the same split, own vs others.
    $this->get('/files')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('files', fn ($files) => collect($files)->firstWhere('id', $own->id)['can_delete'] === true
            && collect($files)->firstWhere('id', $others->id)['can_delete'] === false));

    // Uploader: edit_files + delete_files but not the *_others pair.
    $this->patch("/files/{$own->id}", ['name' => 'Renamed Mine', 'description' => null])->assertRedirect();
    $this->patch("/files/{$others->id}", ['name' => 'Nope', 'description' => null])->assertForbidden();
    $this->delete("/files/{$others->id}")->assertForbidden();
    $this->delete("/files/{$own->id}")->assertRedirect();

    expect(File::query()->find($own->id))->toBeNull()
        ->and(File::withTrashed()->find($own->id))->not->toBeNull();
});

test('a file\'s public flag persists independently of its group assignments', function () {
    $file = uploadDocumentFile($this->admin);
    expect($file->public)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'public' => true, 'slug' => 'contract-public'])
        ->assertRedirect();

    expect($file->refresh()->public)->toBeTrue();

    // Omitting the fields on an update (e.g. a caller that only touches
    // other fields) leaves both public and slug alone rather than
    // resetting/regenerating them from the new name.
    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => 'Still Public', 'description' => null])
        ->assertRedirect();

    expect($file->refresh()->public)->toBeTrue()
        ->and($file->name)->toBe('Still Public')
        ->and($file->slug)->toBe('contract-public');
});

test('marking a file public requires upload_public; edit_files alone is not enough', function () {
    $role = Role::query()->create(['name' => 'File Editor Only', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
        ['role_id' => $role->id, 'permission' => 'edit_files'],
    ]);
    $staff = User::factory()->create(['role_id' => $role->id]);
    expect($staff->can('upload_public'))->toBeFalse();

    $file = uploadDocumentFile($staff);

    $this->actingAs($staff)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'public' => true, 'slug' => 'attempted-public'])
        ->assertRedirect();

    expect($file->refresh()->public)->toBeFalse();
});

test('a file\'s slug is required only when public, but always unique', function () {
    $first = uploadDocumentFile($this->admin, 'first.pdf');
    $second = uploadDocumentFile($this->admin, 'second.pdf');

    $this->actingAs($this->admin)
        ->patch("/files/{$first->id}", ['name' => $first->name, 'description' => null, 'public' => true, 'slug' => 'shared-file-slug'])
        ->assertRedirect();

    // Required once the file is public.
    $this->actingAs($this->admin)
        ->patch("/files/{$second->id}", ['name' => $second->name, 'description' => null, 'public' => true])
        ->assertSessionHasErrors('slug');

    // Uniqueness is enforced regardless of public status.
    $this->actingAs($this->admin)
        ->patch("/files/{$second->id}", ['name' => $second->name, 'description' => null, 'public' => false, 'slug' => 'shared-file-slug'])
        ->assertSessionHasErrors('slug');

    // A file can keep its own slug on update without tripping the
    // uniqueness rule against itself.
    $this->actingAs($this->admin)
        ->patch("/files/{$first->id}", ['name' => $first->name, 'description' => null, 'public' => true, 'slug' => 'shared-file-slug'])
        ->assertRedirect();
});

test('uploading a file falls back to a slug derived from its name', function () {
    $file = uploadDocumentFile($this->admin, 'contract.pdf');

    expect($file->slug)->toBe('contract');

    $duplicate = uploadDocumentFile($this->admin, 'contract.pdf');
    expect($duplicate->slug)->toBe('contract-2');
});

test('the edit page carries a public_url only when the file is public', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback) — reset explicitly rather than assuming
    // nothing else in the suite has touched this setting.
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.public_url', null),
    );

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'public' => true, 'slug' => 'contract-public'])
        ->assertRedirect();

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.public_url', route('public.file', ['public', 'contract-public'])),
    );
});

test('assignments reject staff targets and unassign removes access', function () {
    $file = uploadDocumentFile($this->admin);
    $staffer = User::factory()->role(SystemRole::Uploader)->create();
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin);

    $this->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $staffer->id])
        ->assertSessionHasErrors('id');

    $this->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($client)->get("/files/{$file->id}/download")->assertOk();

    $this->actingAs($this->admin)
        ->delete("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertRedirect();

    $this->actingAs($client)->get("/files/{$file->id}/download")->assertForbidden();
    expect(ActivityLog::query()->where('action', Action::FileUnassigned)->sole()->context)
        ->toBe(['target' => $client->name]);
});

test('clients cannot reach staff file management', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client);
    $this->get('/files')->assertRedirect(route('dashboard'));
    $this->post('/files', [])->assertForbidden();

    $file = uploadDocumentFile($this->admin);
    $this->actingAs($client)->patch("/files/{$file->id}", ['name' => 'Hack'])->assertForbidden();
    $this->actingAs($client)->delete("/files/{$file->id}")->assertForbidden();
});

test('granting a client staff-ish permissions never opens staff surfaces', function () {
    // An admin may add keys to the Client role (e.g. "upload" for the
    // portal-uploads feature). Staff areas must stay closed: being staff
    // is required, permissions only refine.
    $client = User::factory()->client()->create();
    foreach (['upload', 'manage_clients', 'view_actions_log', 'edit_settings', 'manage_groups'] as $key) {
        RolePermission::query()->firstOrCreate([
            'role_id' => $client->role_id,
            'permission' => $key,
        ]);
    }

    $file = uploadDocumentFile($this->admin);
    $this->actingAs($client)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($client);
    // Navigation is sent home; mutations and JSON stay hard 403s.
    // /uploads is deliberately excluded from this list — it's the shared
    // chunked-upload mechanism both staff and clients use for their own
    // self-uploads (gated by can:upload + per-session ownership, not a
    // staff surface), see ChunkedUploadsTest.php for its own coverage.
    $this->get('/files')->assertRedirect(route('dashboard'));
    $this->get('/files/upload')->assertRedirect(route('dashboard'));
    $this->get("/files/{$file->id}")->assertRedirect(route('dashboard'));
    $this->get('/clients')->assertRedirect(route('dashboard'));
    $this->get('/groups')->assertRedirect(route('dashboard'));
    $this->get('/activity')->assertRedirect(route('dashboard'));
    $this->get('/system/settings/general')->assertRedirect(route('dashboard'));

    // Their legitimate client surfaces keep working.
    $this->get('/my-files')->assertOk();
    $this->get('/dashboard')->assertOk();
});

test('file log entries link to the file for authorized staff', function () {
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->get('/activity?action=file.uploaded')->assertInertia(
        fn (AssertableInertia $page) => $page->where('entries.0.subject_url', "/files/{$file->id}"),
    );
});

test('the files index filters to a flat list of expired files, and flags an expired row even outside the filter', function () {
    $expired = uploadDocumentFile($this->admin, 'stale.pdf');
    $expired->update(['expires_at' => now()->subDay()]);
    $fresh = uploadDocumentFile($this->admin, 'fresh.pdf');
    $fresh->update(['expires_at' => now()->addDay()]);

    // The frontend checkbox sends the literal string "true" — not "1" —
    // which a 'boolean' validation rule would reject (it only accepts
    // true/false/0/1/'0'/'1'), silently redirecting the filter away.
    $this->actingAs($this->admin)->get('/files?expired=true')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('searching', true) // flat mode, same as the category filter
            ->where('expired', true)
            ->has('files', 1)
            ->where('files.0.name', 'stale')
            ->where('files.0.expired', true),
    );

    // Outside the filter (plain folder browsing), the expired file still
    // carries the flag so the row can show its badge.
    $response = $this->actingAs($this->admin)->get('/files');
    $props = json_decode(json_encode($response->viewData('page')), true)['props'];
    $rows = collect($props['files'])->keyBy('name');
    expect($rows->get('stale')['expired'])->toBeTrue()
        ->and($rows->get('fresh')['expired'])->toBeFalse();
});

test('staff without upload cannot see the files section', function () {
    // A custom role with no file permissions at all.
    $role = Role::query()->create(['name' => 'Viewer']);
    $viewer = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($viewer)->get('/files')->assertForbidden();
});

test('an expired file disappears from the client portal and its direct download, but stays visible to staff', function () {
    $client = User::factory()->client()->create();
    $expired = directFileForClient($client, 'expired', 100, $this->admin);
    $expired->update(['expires_at' => now()->subDay()]);
    $stillGood = directFileForClient($client, 'still-good', 100, $this->admin);
    $stillGood->update(['expires_at' => now()->addDay()]);

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->has('files', 1)->where('files.0.name', 'still-good'),
    );

    $this->actingAs($client)->get("/files/{$expired->id}/download")->assertForbidden();
    $this->actingAs($client)->get("/files/{$stillGood->id}/download")->assertOk();

    // Staff keeps full access to the expired file regardless.
    $this->actingAs($this->admin)->get("/files/{$expired->id}/download")->assertOk();
    $this->actingAs($this->admin)->get("/files/{$expired->id}")->assertOk();
});

test('setting a file\'s expiration date requires set_file_expiration_date', function () {
    $role = Role::query()->create(['name' => 'File Editor Only', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
        ['role_id' => $role->id, 'permission' => 'edit_files'],
    ]);
    $staff = User::factory()->create(['role_id' => $role->id]);
    expect($staff->can('set_file_expiration_date'))->toBeFalse();

    $file = uploadDocumentFile($staff);

    $this->actingAs($staff)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'expires_at' => '2020-01-01'])
        ->assertRedirect();

    expect($file->refresh()->expires_at)->toBeNull();

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'expires_at' => '2020-01-01'])
        ->assertRedirect();

    expect($file->refresh()->expires_at?->toDateString())->toBe('2020-01-01');

    // Omitting the field (permitted user) clears it back to never-expires.
    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null])
        ->assertRedirect();

    expect($file->refresh()->expires_at)->toBeNull();
});

test('the editor\'s expired flag flips as soon as a save changes the expiry date, no separate reload needed', function () {
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.expired', false),
    );

    // The redirect the update endpoint returns is what the browser's
    // Inertia client follows to refresh this same page's props — so the
    // very next response already reflects the new expiry, without any
    // separate/manual reload.
    $this->actingAs($this->admin)
        ->from("/files/{$file->id}")
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'expires_at' => '2020-01-01'])
        ->assertRedirect("/files/{$file->id}");

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.expired', true),
    );

    // Clearing it back out removes the flag again.
    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'description' => null, 'expires_at' => now()->addDay()->toDateString()])
        ->assertRedirect();

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.expired', false),
    );
});

// original_name is echoed into the Content-Disposition header of every
// download/preview/thumbnail response, and a CR/LF there is header
// injection — PHP refuses to emit it, so it fails closed as a 500, but a
// filename should not be able to break the response at all.
test('control characters are stripped from an uploaded filename', function () {
    $user = User::factory()->create();

    $file = app(StoreUploadedFile::class)->create(
        uploader: $user,
        originalName: "report\r\nX-Injected: yes.pdf",
        path: '2026/08/x.pdf',
        mimeType: 'application/pdf',
        size: 10,
        checksum: str_repeat('a', 64),
    );

    expect($file->original_name)->toBe('reportX-Injected: yes.pdf')
        ->and($file->original_name)->not->toContain("\r")
        ->and($file->original_name)->not->toContain("\n");

    $this->actingAs($user)->get("/files/{$file->id}/download")
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="reportX-Injected: yes.pdf"');
});
