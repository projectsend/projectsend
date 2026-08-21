<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('the file details endpoint returns metadata and current shares', function () {
    $client = User::factory()->client()->create(['name' => 'Shared Client']);
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
        'name' => 'Report', 'description' => 'A test',
    ]);
    $file = File::query()->sole();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($this->admin)->getJson("/files/{$file->id}/details")->assertOk()->assertJson([
        'type' => 'file',
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'can_view_activity' => true,
        'categories' => [],
        'shares' => ['clients' => [['name' => 'Shared Client']]],
    ]);

    $category = Category::query()->create(['name' => 'Invoices', 'color' => 'red']);
    $file->categories()->attach($category->id);

    $this->actingAs($this->admin)->getJson("/files/{$file->id}/details")->assertOk()
        ->assertJson(['categories' => [['id' => $category->id, 'name' => 'Invoices', 'color' => 'red']]]);
});

test('the file activity endpoint returns the file log and honors the permission', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'name' => '', 'description' => '',
    ]);
    $file = File::query()->sole();

    $this->actingAs($this->admin)->getJson("/files/{$file->id}/activity")->assertOk()->assertJsonPath('entries.0.template', 'Uploaded the file ":subject"');

    // An uploader has view_actions_log too; a role without it is 403.
    $role = Role::query()->create(['name' => 'No Log']);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'upload']);
    $noLog = User::factory()->create(['role_id' => $role->id]);
    $this->actingAs($noLog)->getJson("/files/{$file->id}/activity")->assertForbidden();
});

test('the folder details endpoint returns counts and shares', function () {
    $folder = app(FolderService::class)->create('Docs', null);

    $this->actingAs($this->admin)->getJson("/folders/{$folder->id}/details")->assertOk()->assertJson([
        'type' => 'folder',
        'name' => 'Docs',
        'files_count' => 0,
    ]);
});

test('the folder details endpoint is read-only: it links to the edit page instead of exposing assignment endpoints', function () {
    $folder = app(FolderService::class)->create('Docs', null);

    // Sharing (and every other editable field) is only ever changed from
    // the folder's own edit page (files/folder.tsx) — the info panel must
    // not carry assign/unassign URLs or the "available to share with"
    // rosters that would let it render live editing controls, matching
    // how a file's details response already behaves.
    $this->actingAs($this->admin)->getJson("/folders/{$folder->id}/details")
        ->assertOk()
        ->assertJson(['edit_url' => route('folders.share', $folder, false)])
        ->assertJsonMissingPath('assign_url')
        ->assertJsonMissingPath('unassign_url')
        ->assertJsonMissingPath('shares.available_clients')
        ->assertJsonMissingPath('shares.available_groups');
});

test('the file downloads endpoint groups downloads by actor with per-download IPs', function () {
    $client = User::factory()->client()->create(['name' => 'Shared Client']);
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
        'name' => 'Report', 'description' => '',
    ]);
    $file = File::query()->sole();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download", ['REMOTE_ADDR' => '203.0.113.1']);
    $this->actingAs($client)->get("/files/{$file->id}/download", ['REMOTE_ADDR' => '198.51.100.2']);
    $this->actingAs($client)->get("/files/{$file->id}/download", ['REMOTE_ADDR' => '198.51.100.3']);

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/downloads")->assertOk();

    $response->assertJson(['total' => 3]);
    $downloaders = $response->json('downloaders');
    expect($downloaders)->toHaveCount(2);

    $clientEntry = collect($downloaders)->firstWhere('actor_name', 'Shared Client');
    expect($clientEntry['count'])->toBe(2)
        ->and($clientEntry['actor_type'])->toBe('client')
        ->and(collect($clientEntry['downloads'])->pluck('ip_address')->all())->toBe(['198.51.100.3', '198.51.100.2']);

    $adminEntry = collect($downloaders)->firstWhere('actor_name', $this->admin->name);
    expect($adminEntry['count'])->toBe(1)
        ->and($adminEntry['downloads'][0]['ip_address'])->toBe('203.0.113.1');
});

test('a public share-link download is bucketed as "Public link", not "deleted account"', function () {
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
    $link = ShareLink::query()->create(['shareable_type' => $file->getMorphClass(), 'shareable_id' => $file->id, 'token' => Str::random(32)]);

    // No actingAs() at all — this must be a genuine guest request.
    $this->get("/s/{$link->token}/download")->assertOk();

    $downloaders = $this->actingAs($this->admin)->getJson("/files/{$file->id}/downloads")->assertOk()->json('downloaders');

    expect($downloaders)->toHaveCount(1);
    expect($downloaders[0]['actor_id'])->toBeNull()
        ->and($downloaders[0]['actor_name'])->toBe('Public link')
        ->and($downloaders[0]['actor_type'])->toBeNull()
        ->and($downloaders[0]['count'])->toBe(1);
});

test('a public group listing download is bucketed as "Public listing", not missing entirely', function () {
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'public' => true,
    ]);
    $file->assignments()->create(['assignable_type' => Group::class, 'assignable_id' => $group->id]);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    // No actingAs() at all — this must be a genuine guest request.
    $this->get("/public/files/{$file->slug}/download")->assertOk();

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/downloads")->assertOk();
    $response->assertJson(['total' => 1]);

    $downloaders = $response->json('downloaders');
    expect($downloaders)->toHaveCount(1);
    expect($downloaders[0]['actor_id'])->toBeNull()
        ->and($downloaders[0]['actor_name'])->toBe('Public listing')
        ->and($downloaders[0]['actor_type'])->toBeNull()
        ->and($downloaders[0]['count'])->toBe(1);
});

test('a role without view_actions_log cannot see the downloads tab', function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'name' => '', 'description' => '',
    ]);
    $file = File::query()->sole();

    $role = Role::query()->create(['name' => 'No Log']);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'upload']);
    $noLog = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($noLog)->getJson("/files/{$file->id}/downloads")->assertForbidden();
});

test('clients cannot reach the details endpoints', function () {
    $folder = app(FolderService::class)->create('X', null);
    $client = User::factory()->client()->create();

    // Staff-gated: client GET redirects home.
    $this->actingAs($client)->get("/folders/{$folder->id}/details")->assertRedirect(route('dashboard'));
});

test('the details endpoint carries expiry and the download limit', function () {
    // Both were missing from this payload, so the panel that exists to
    // answer "why can this client not download it?" could not.
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'expires_at' => now()->addWeek(),
        'download_limit' => 3,
    ]);

    $this->actingAs($this->admin)->getJson("/files/{$file->id}/details")->assertOk()->assertJson([
        'expired' => false,
        'download_limit' => 3,
        'download_limit_scope' => 'total',
        'downloads_used' => 0,
        // The uploader is exempt from their own file's limit, so their
        // own button stays live.
        'download_allowance' => ['limit' => null, 'left' => null, 'blocked' => false],
    ])->assertJsonPath('expires_at', fn (?string $value): bool => $value !== null);
});

test('the details endpoint reports a limit that is spent for the viewer', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'download_limit' => 1]);
    $staff = staffWithPermissions(['upload', 'edit_files', 'edit_others_files']);

    ActivityLog::query()->create([
        'actor_id' => $staff->id,
        'action' => Action::FileDownloaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'created_at' => now(),
    ]);

    $this->actingAs($staff)->getJson("/files/{$file->id}/details")->assertOk()->assertJson([
        'download_limit' => 1,
        'downloads_used' => 1,
        'download_allowance' => ['limit' => 1, 'left' => 0, 'blocked' => true],
    ]);
});

test('a file with no limit says so rather than reporting a zero', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->getJson("/files/{$file->id}/details")->assertOk()
        ->assertJsonPath('download_limit', null)
        ->assertJsonPath('expires_at', null)
        ->assertJsonPath('expired', false);
});

test("the file's own page carries an activity tab, behind the same permission", function () {
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'name' => '', 'description' => '',
    ]);
    $file = File::query()->sole();

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->component('files/edit')->where('can_view_activity', true),
    );

    // The uploader of *this* file, but with no view_actions_log: the page
    // still loads, without the tab that would show the log.
    $role = Role::query()->create(['name' => 'No Log']);
    foreach (['upload', 'edit_own_files'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }
    $noLog = User::factory()->create(['role_id' => $role->id]);
    $file->update(['uploaded_by' => $noLog->id]);

    $this->actingAs($noLog)->get("/files/{$file->id}")->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->component('files/edit')->where('can_view_activity', false),
    );
});
