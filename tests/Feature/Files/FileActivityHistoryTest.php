<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('the details panel activity tab is capped at 20 entries and reports the true total', function () {
    $file = uploadImageFile($this->admin);

    // The upload itself already logged one entry; pad past the cap.
    for ($i = 0; $i < 24; $i++) {
        ActivityLog::create([
            'action' => Action::FileUpdated,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/activity");

    $response->assertOk();
    expect($response->json('entries'))->toHaveCount(20)
        ->and($response->json('total'))->toBe(25);
});

test('the full activity history page paginates every entry for a file', function () {
    $file = uploadImageFile($this->admin);

    for ($i = 0; $i < 29; $i++) {
        ActivityLog::create([
            'action' => Action::FileUpdated,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'created_at' => now(),
        ]);
    }

    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('activity/subject')
            ->has('entries', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2)
            ->where('subject_name', $file->name),
    );

    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?page=2")->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 5),
    );
});

test('the full activity history page paginates every entry for a folder', function () {
    $this->actingAs($this->admin)->post('/folders', ['name' => 'History Folder'])->assertRedirect();
    $folder = Folder::query()->where('name', 'History Folder')->sole();

    for ($i = 0; $i < 5; $i++) {
        ActivityLog::create([
            'action' => Action::FolderRenamed,
            'subject_type' => $folder->getMorphClass(),
            'subject_id' => $folder->id,
            'subject_name' => $folder->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'created_at' => now(),
        ]);
    }

    // Folder creation itself logs one entry, so 5 padding entries plus that one.
    $this->actingAs($this->admin)->get("/folders/{$folder->id}/activity/history")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('activity/subject')
            ->has('entries', 6)
            ->where('subject_name', $folder->name),
    );
});

test('a staff member without view_actions_log cannot open a file activity history page', function () {
    $file = uploadImageFile($this->admin);

    $role = Role::query()->create(['name' => 'No Activity Log', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $restricted = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($restricted)->get("/files/{$file->id}/activity/history")->assertForbidden();
});

test('the full download history page paginates every download and labels public downloads', function () {
    $file = uploadImageFile($this->admin);

    for ($i = 0; $i < 24; $i++) {
        ActivityLog::create([
            'action' => Action::FileDownloaded,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);
    }
    ActivityLog::create([
        'action' => Action::ShareLinkDownloaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'subject_name' => $file->name,
        'ip_address' => '10.0.0.2',
        'created_at' => now(),
    ]);
    ActivityLog::create([
        'action' => Action::PublicFileDownloaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'subject_name' => $file->name,
        'ip_address' => '10.0.0.3',
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin)->get("/files/{$file->id}/downloads/history")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('activity/downloads')
            ->has('entries', 25)
            ->where('pagination.total', 26)
            ->where('pagination.last_page', 2)
            ->where('subject_name', $file->name)
            // Same-second timestamps tie-break on id desc, and the two
            // public-download rows were inserted last, so they lead.
            ->where('entries.0.actor_name', __('Public listing'))
            ->where('entries.1.actor_name', __('Public link')),
    );

    $this->actingAs($this->admin)->get("/files/{$file->id}/downloads/history?page=2")->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 1),
    );
});

test('a staff member without view_actions_log cannot open a file download history page', function () {
    $file = uploadImageFile($this->admin);

    $role = Role::query()->create(['name' => 'No Activity Log 2', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $restricted = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($restricted)->get("/files/{$file->id}/downloads/history")->assertForbidden();
});

test('the details panel downloads summary is bounded but reports the true total', function () {
    $file = uploadImageFile($this->admin);

    for ($i = 0; $i < 502; $i++) {
        ActivityLog::create([
            'action' => Action::FileDownloaded,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/downloads");

    $response->assertOk();
    // The grouped summary caps the raw rows it considers, so a single
    // heavy downloader's group count saturates at that cap — but the
    // top-level total (and the full download history page) stay accurate.
    expect($response->json('total'))->toBe(502)
        ->and($response->json('downloaders'))->toHaveCount(1)
        ->and($response->json('downloaders.0.count'))->toBe(500);
});
