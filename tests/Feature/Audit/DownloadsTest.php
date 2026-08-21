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

test('the installation-wide download history filters by file, by account and by date', function () {
    $report = uploadImageFile($this->admin, 'quarterly-report.jpg');
    $photo = uploadImageFile($this->admin, 'holiday-photo.jpg');
    $client = User::factory()->client()->create(['name' => 'Acme Design']);

    $log = function ($file, ?User $actor, string $when) {
        ActivityLog::create([
            'action' => $actor === null ? Action::ShareLinkDownloaded : Action::FileDownloaded,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_type' => $actor === null ? null : 'client',
            'created_at' => $when,
        ]);
    };

    $log($report, $client, '2026-08-10 09:00:00');
    $log($report, $this->admin, '2026-08-12 09:00:00');
    $log($photo, $client, '2026-08-14 09:00:00');
    // Anonymous: no account name to match, so the user filter can never
    // return it — which is what the column already says on screen.
    $log($photo, null, '2026-08-16 09:00:00');

    $entries = fn (string $query) => $this->actingAs($this->admin)->get("/downloads{$query}")->assertOk();

    $entries('?file=report')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 2)->where('filters.file', 'report'),
    );

    $entries('?user=Acme')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 2)->where('entries.0.actor_name', 'Acme Design'),
    );

    // Both filters at once narrow to the one row that satisfies each.
    $entries('?file=report&user=Acme')->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 1));

    $entries('?from=2026-08-13')->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 2));
    $entries('?to=2026-08-11')->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 1));
    $entries('?from=2026-08-11&to=2026-08-15')->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 2));

    // A range that ends before it starts is rejected rather than silently
    // returning nothing.
    $this->actingAs($this->admin)->get('/downloads?from=2026-08-15&to=2026-08-11')->assertSessionHasErrors('to');
});

test('a client-scoped viewer cannot widen the download history with a filter', function () {
    $secret = uploadImageFile($this->admin, 'board-minutes-confidential.jpg');
    $this->actingAs($this->admin)->get("/files/{$secret->id}/download")->assertOk();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([]);

    // Out of this viewer's library scope, so it stays invisible however
    // precisely it is searched for: the filters narrow the scoped query,
    // they do not replace it.
    $this->actingAs($manager)->get('/downloads?file=board-minutes')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 0),
    );

    $this->actingAs($this->admin)->get('/downloads?file=board-minutes')->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 1),
    );
});
