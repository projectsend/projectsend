<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Identity\Models\Role;
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
