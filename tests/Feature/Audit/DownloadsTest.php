<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('the installation-wide download history paginates across every file', function () {
    $fileA = uploadImageFile($this->admin, 'a.jpg');
    $fileB = uploadImageFile($this->admin, 'b.jpg');

    for ($i = 0; $i < 15; $i++) {
        ActivityLog::create([
            'action' => Action::FileDownloaded,
            'subject_type' => $fileA->getMorphClass(),
            'subject_id' => $fileA->id,
            'subject_name' => $fileA->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'created_at' => now(),
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        ActivityLog::create([
            'action' => Action::FileDownloaded,
            'subject_type' => $fileB->getMorphClass(),
            'subject_id' => $fileB->id,
            'subject_name' => $fileB->name,
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => 'staff',
            'created_at' => now(),
        ]);
    }

    $this->actingAs($this->admin)->get('/downloads')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('activity/downloads')
            ->has('entries', 25)
            ->where('pagination.total', 27)
            ->where('pagination.last_page', 2)
            ->missing('subject_name')
            ->missing('back_url'),
    );

    $this->actingAs($this->admin)->get('/downloads?page=2')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 2),
    );
});

test('download history links to the file when it still exists and hides the link once deleted', function () {
    $file = uploadImageFile($this->admin, 'linked.jpg');

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

    $this->actingAs($this->admin)->get('/downloads')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries.0.file_name', $file->name)
            ->where('entries.0.file_url', "/files/{$file->id}"),
    );

    $file->forceDelete();

    $this->actingAs($this->admin)->get('/downloads')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries.0.file_name', $file->name)
            ->where('entries.0.file_url', null),
    );
});

test('download history labels public downloads without a real actor', function () {
    $file = uploadImageFile($this->admin, 'public.jpg');

    ActivityLog::create([
        'action' => Action::ShareLinkDownloaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'subject_name' => $file->name,
        'created_at' => now(),
    ]);
    ActivityLog::create([
        'action' => Action::PublicFileDownloaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'subject_name' => $file->name,
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin)->get('/downloads')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries.0.actor_name', __('Public listing'))
            ->where('entries.1.actor_name', __('Public link')),
    );
});

test('clients cannot view the installation-wide download history', function () {
    User::factory()->create();

    $this->actingAs(User::factory()->client()->create())
        ->get('/downloads')->assertRedirect(route('dashboard'));
});

test('a staff member without view_actions_log cannot open the download history page', function () {
    $role = Role::query()->create(['name' => 'No Downloads Log', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $restricted = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($restricted)->get('/downloads')->assertForbidden();
});

// An activity row carries the subject's name — and a download row adds who
// fetched it and from where — so `view_actions_log` alone is not the whole
// answer for a client-scoped viewer. The Client Manager role ships with
// that permission, so this is the default configuration.
test('a client-scoped viewer only sees log entries for content in their scope', function () {
    $secret = uploadImageFile($this->admin, 'board-minutes-confidential.jpg');
    $this->actingAs($this->admin)->get("/files/{$secret->id}/download")->assertOk();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([]);

    // Baseline: the file itself is genuinely out of reach.
    $this->actingAs($manager)->get("/files/{$secret->id}/download")->assertForbidden();

    $downloads = $this->actingAs($manager)->get('/downloads')->assertOk();
    $rows = json_decode(json_encode($downloads->viewData('page')), true)['props']['entries'];
    expect($rows)->toBe([]);

    $activity = $this->actingAs($manager)->get('/activity')->assertOk();
    $subjects = collect(json_decode(json_encode($activity->viewData('page')), true)['props']['entries'])
        ->pluck('replacements.subject');
    expect($subjects)->not->toContain('board-minutes-confidential');

    // The same page is unchanged for an unscoped viewer.
    $adminRows = json_decode(json_encode(
        $this->actingAs($this->admin)->get('/downloads')->viewData('page')
    ), true)['props']['entries'];
    expect(collect($adminRows)->pluck('file_name'))->toContain('board-minutes-confidential');
});

test('a client-scoped viewer does see entries for their own clients and their own actions', function () {
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $shared = uploadImageFile($this->admin, 'quarterly-report.jpg');
    $this->actingAs($this->admin)->post("/files/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->get("/files/{$shared->id}/download")->assertOk();

    $downloads = $this->actingAs($manager)->get('/downloads')->assertOk();
    $rows = collect(json_decode(json_encode($downloads->viewData('page')), true)['props']['entries']);

    expect($rows->pluck('file_name'))->toContain('quarterly-report')
        // In scope, so the row links — the link check uses the scope, not
        // permission alone, so it never points at a 403.
        ->and($rows->firstWhere('file_name', 'quarterly-report')['file_url'])->not->toBeNull();
});
