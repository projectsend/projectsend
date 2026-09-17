<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('the users list paginates at 25 and filters by search, role and status', function () {
    $this->actingAs($this->admin);

    User::factory()->count(30)->create();
    // A nonce-like token, not an English word: Faker's randomly generated
    // names/emails for the 30 users above could otherwise coincidentally
    // contain a plain word like "unique" and break this count assertion.
    User::factory()->create(['name' => 'Zqxlvw9k Persson', 'email' => 'zqxlvw9k@example.test']);

    // Page 1 caps at 25; the total counts every staff member (incl. the admin).
    $this->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users', 25)
        ->where('pagination.total', 32)
        ->where('pagination.last_page', 2));

    // Search matches name or email.
    $this->get('/users?search=zqxlvw9k')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users', 1)
        ->where('users.0.name', 'Zqxlvw9k Persson'));

    // Status filter.
    User::factory()->create(['name' => 'Dormant', 'active' => false]);
    $this->get('/users?status=inactive&search=Dormant')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users', 1)
        ->where('users.0.name', 'Dormant'));

    // Role filter.
    $role = Role::query()->create(['name' => 'Special']);
    User::factory()->create(['name' => 'Roled', 'role_id' => $role->id]);
    $this->get("/users?role={$role->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users', 1)
        ->where('users.0.name', 'Roled'));
});

test('the clients list filters by search and status', function () {
    $this->actingAs($this->admin);

    User::factory()->client()->create(['name' => 'Acme Corp', 'email' => 'billing@acme.test']);
    User::factory()->client()->create(['name' => 'Globex', 'active' => false]);

    $this->get('/clients?search=acme')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('clients', 1)
        ->where('clients.0.name', 'Acme Corp'));

    $this->get('/clients?status=inactive')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('clients', 1)
        ->where('clients.0.name', 'Globex'));
});

test('the groups list filters by visibility and search', function () {
    $this->actingAs($this->admin);

    Group::query()->create(['name' => 'Public Team', 'public' => true]);
    Group::query()->create(['name' => 'Secret Team', 'public' => false]);

    $this->get('/groups?visibility=private')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 1)
        ->where('groups.0.name', 'Secret Team'));

    $this->get('/groups?search=Public')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 1)
        ->where('groups.0.name', 'Public Team'));
});

test('the files list searches globally and flat across folders, paginated', function () {
    $this->actingAs($this->admin);

    $folder = app(FolderService::class)->create('Archive', null);
    $this->post('/files', [
        'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        'name' => 'AlphaReport', 'description' => '', 'folder_id' => $folder->id,
    ]);
    $this->post('/files', [
        'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        'name' => 'BetaDoc', 'description' => '',
    ]);

    // Browsing the root does not show the file nested in Archive.
    $this->get('/files')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('searching', false)
        ->has('files', 1)
        ->where('files.0.name', 'BetaDoc'));

    // Searching flattens the whole library: the nested file surfaces, the
    // breadcrumb is dropped, and pagination metadata is present.
    $this->get('/files?search=Alpha')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('searching', true)
        ->where('breadcrumb', [])
        ->has('files', 1)
        ->where('files.0.name', 'AlphaReport')
        ->has('pagination'));
});

test('the files list filters by uploader, and by the uploader\'s role', function () {
    $this->actingAs($this->admin);

    // Through the factory state rather than a name lookup: the built-in
    // roles are materialized on demand, so querying for one by name in a
    // fresh database returns null -- which made the role filter fall
    // through as "no filter" and quietly pass on the wrong rows.
    $editor = User::factory()->role(SystemRole::Uploader)->create();
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'ByAdmin']);
    File::factory()->create(['uploaded_by' => $editor->id, 'name' => 'ByEditor']);

    $this->get("/files?uploader={$editor->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('searching', true)
        ->has('files', 1)
        ->where('files.0.name', 'ByEditor'));

    // The role filter reaches the same file through who uploaded it rather
    // than through the file itself -- a different join, so it gets its own
    // assertion instead of being assumed from the one above.
    $this->get("/files?role={$editor->role_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 1)
        ->where('files.0.name', 'ByEditor'));

    // Both dropdowns are built from files this viewer can see, so both
    // uploaders are offered and each role appears once.
    $this->get('/files')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('uploader_options', 2)
        ->has('role_options', 2));
});

test('the files list filters by public and private, counting a public folder as public', function () {
    $this->actingAs($this->admin);

    $publicFolder = app(FolderService::class)->create('Brochures', null);
    $publicFolder->update(['public' => true]);

    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'FlaggedPublic', 'public' => true]);
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'InPublicFolder', 'public' => false, 'folder_id' => $publicFolder->id]);
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'PlainPrivate', 'public' => false]);

    // The file in the public folder counts as public even though its own
    // flag is false -- the same rule the row's own badge uses. Filtering on
    // the column alone would have hidden a file this screen labels Public.
    $this->get('/files?visibility=public')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 2)
        ->where('files.0.name', 'FlaggedPublic')
        ->where('files.1.name', 'InPublicFolder'));

    // And the private half must not lose the file sitting at the library
    // root: `folder_id NOT IN (...)` is never true for a NULL folder_id.
    $this->get('/files?visibility=private')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 1)
        ->where('files.0.name', 'PlainPrivate'));
});

test('the files list separates files that were never downloaded from those that were', function () {
    $this->actingAs($this->admin);

    $downloaded = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Grabbed']);
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Untouched']);

    ActivityLog::query()->create([
        'actor_id' => $this->admin->id,
        'action' => Action::FileDownloaded,
        'subject_type' => $downloaded->getMorphClass(),
        'subject_id' => $downloaded->id,
        'created_at' => now(),
    ]);

    $this->get('/files?downloads=none')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 1)
        ->where('files.0.name', 'Untouched'));

    $this->get('/files?downloads=any')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 1)
        ->where('files.0.name', 'Grabbed'));
});

test('the files list separates current versions from outdated ones', function () {
    $this->actingAs($this->admin);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'DraftOne']);
    File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'DraftTwo',
        'previous_file_id' => $original->id,
        'version_root_id' => $original->id,
    ]);
    File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'NeverVersioned']);

    // A file nothing replaced is current, and that includes one that was
    // never versioned at all -- it is the current version of itself.
    $this->get('/files?version=current')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 2)
        ->where('files.0.name', 'DraftTwo')
        ->where('files.1.name', 'NeverVersioned'));

    $this->get('/files?version=outdated')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 1)
        ->where('files.0.name', 'DraftOne'));
});

test('the account and membership request queues are searchable', function () {
    $this->actingAs($this->admin);

    User::factory()->client()->create(['name' => 'Wanda Waiting', 'email' => 'wanda@example.test', 'active' => false, 'account_requested' => true]);
    User::factory()->client()->create(['name' => 'Other Pending', 'active' => false, 'account_requested' => true]);

    $this->get('/account-requests?search=wanda')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('requests', 1)
        ->where('requests.0.name', 'Wanda Waiting'));

    $group = Group::query()->create(['name' => 'Design Guild', 'public' => false]);
    $client = User::factory()->client()->create(['name' => 'Joiner Jones']);
    MembershipRequest::query()->create([
        'group_id' => $group->id,
        'user_id' => $client->id,
        'status' => MembershipRequest::STATUS_PENDING,
    ]);

    $this->get('/membership-requests?search=Design')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('requests', 1)
        ->where('requests.0.group_name', 'Design Guild'));
});
