<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('an administrator sees counters, transfers, activity, and system info', function () {
    $client = User::factory()->client()->create();
    User::factory()->client()->create();
    Group::query()->create(['name' => 'G1']);

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        'name' => '', 'description' => '',
    ]);
    $file = File::query()->sole();

    // A client download and an anonymous share-link download — the
    // transfers widget now splits these into separate series.
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($client)->get("/files/{$file->id}/download");

    $shareLink = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
    ]);
    auth()->logout();
    $this->get("/s/{$shareLink->token}/download");

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('counters.files', 1)
            ->where('counters.files_bytes', 100 * 1024)
            ->where('counters.clients', 2)
            ->where('counters.groups', 1)
            ->where('counters.users', 1)
            ->has('transfers', 30)
            ->where('transfers.29.uploads', 1)
            ->where('transfers.29.downloads_clients', 1)
            ->where('transfers.29.downloads_anonymous', 1)
            ->where('transfers_range.preset', 'last_month')
            ->has('recent')
            ->where('system.version', config('projectsend.version'))
            ->where('system.storage_used_bytes', 100 * 1024)
            // No CheckForUpdatesCommand run has happened, so nothing is cached yet.
            ->where('system.update_available', false)
            ->where('system.latest_version', null),
    );
});

test('the System card reports an update once one is cached, and clears once caught up', function () {
    app(Settings::class)->set(Setting::LatestKnownVersion, '99.0.0');
    app(Settings::class)->set(Setting::LatestReleaseUrl, 'https://github.com/projectsend/projectsend/releases/tag/v99.0.0');

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('system.update_available', true)
            ->where('system.latest_version', '99.0.0')
            ->where('system.release_url', 'https://github.com/projectsend/projectsend/releases/tag/v99.0.0'),
    );

    app(Settings::class)->set(Setting::LatestKnownVersion, config('projectsend.version'));

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('system.update_available', false)
            ->where('system.latest_version', null)
            ->where('system.release_url', null),
    );
});

/**
 * The amber "update available" alert (dashboard.tsx's System card) is
 * rendered purely off `system.update_available` — this repo has no
 * JS/DOM test tooling, so a Pest test can't inspect the rendered
 * Tailwind classes or confirm a <div> is actually amber. What it can
 * (and must) pin down is the backend contract that alert depends on:
 * a staff member who can't see the System card at all (no
 * view_system_info) must never receive the data that would let a
 * client-side bug show it to them anyway.
 */
test('a staff member without view_system_info never receives the System card, even with a real update cached', function () {
    app(Settings::class)->set(Setting::LatestKnownVersion, '99.0.0');
    app(Settings::class)->set(Setting::LatestReleaseUrl, 'https://github.com/projectsend/projectsend/releases/tag/v99.0.0');

    $role = Role::query()->create(['name' => 'No System Info', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'view_dashboard_counters']);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('system', null),
    );
});

test('the transfers widget honors the range selector', function () {
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id, 'name' => 'old', 'original_name' => 'old.pdf',
        'path' => 'old.pdf', 'mime_type' => 'application/pdf', 'size' => 1024, 'checksum' => str_repeat('a', 64),
    ]);
    ActivityLog::query()->create([
        'actor_id' => $this->admin->id, 'actor_name' => $this->admin->name, 'actor_type' => 'staff',
        'action' => Action::FileUploaded, 'subject_type' => $file->getMorphClass(), 'subject_id' => $file->id,
        'subject_name' => $file->name, 'created_at' => now()->subDays(45),
    ]);

    // Default (last_month) window doesn't reach 45 days back.
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('transfers', 30)
            ->where('transfers_range.preset', 'last_month'),
    );

    // A custom range that does reach back 45 days picks it up.
    $from = now()->subDays(50)->toDateString();
    $to = now()->subDays(40)->toDateString();
    $this->actingAs($this->admin)->get("/dashboard?range=custom&from={$from}&to={$to}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('transfers', 11)
            ->where('transfers_range.preset', 'custom')
            ->where('transfers_range.from', $from)
            ->where('transfers_range.to', $to)
    );

    // last_week is a 7-day window.
    $this->actingAs($this->admin)->get('/dashboard?range=last_week')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('transfers', 7)
            ->where('transfers_range.preset', 'last_week'),
    );
});

test('the top-clients-by-storage widget ranks clients by usage against their effective quota', function () {
    app(Settings::class)->set(Setting::DefaultClientStorageQuotaMb, 200);

    $heavy = User::factory()->client()->create(['storage_quota_mb' => 0]); // inherits the 200 MB default
    $light = User::factory()->client()->create(['storage_quota_mb' => 500]); // explicit override

    File::factory()->create([
        'uploaded_by' => $heavy->id, 'name' => 'big', 'original_name' => 'big.pdf',
        'path' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 100 * 1024 * 1024, 'checksum' => str_repeat('a', 64),
    ]);
    File::factory()->create([
        'uploaded_by' => $light->id, 'name' => 'small', 'original_name' => 'small.pdf',
        'path' => 'b.pdf', 'mime_type' => 'application/pdf', 'size' => 10 * 1024 * 1024, 'checksum' => str_repeat('b', 64),
    ]);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('top_clients_by_storage', 2)
            ->where('top_clients_by_storage.0.id', $heavy->id)
            ->where('top_clients_by_storage.0.used_bytes', 100 * 1024 * 1024)
            ->where('top_clients_by_storage.0.quota_mb', 200) // inherited, not the raw 0
            ->where('top_clients_by_storage.1.id', $light->id)
            ->where('top_clients_by_storage.1.quota_mb', 500),
    );
});

test('the largest-files widget ranks files by size regardless of uploader, and survives a deleted uploader', function () {
    $client = User::factory()->client()->create();

    $small = File::factory()->create([
        'uploaded_by' => $this->admin->id, 'name' => 'small', 'original_name' => 'small.pdf',
        'path' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 1 * 1024 * 1024, 'checksum' => str_repeat('a', 64),
    ]);
    $big = File::factory()->create([
        'uploaded_by' => $client->id, 'name' => 'big', 'original_name' => 'big.pdf',
        'path' => 'b.pdf', 'mime_type' => 'application/pdf', 'size' => 900 * 1024 * 1024, 'checksum' => str_repeat('b', 64),
    ]);

    $client->delete();
    $client->forceDelete();

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('largest_files', 2)
            ->where('largest_files.0.id', $big->id)
            ->where('largest_files.0.size', 900 * 1024 * 1024)
            ->where('largest_files.0.uploader_name', null) // uploader was deleted — nullOnDelete
            ->where('largest_files.1.id', $small->id)
            ->where('largest_files.1.uploader_name', $this->admin->name),
    );
});

test('the largest-files widget only links to what the viewer is permitted to open', function () {
    $client = User::factory()->client()->create();
    File::factory()->create([
        'uploaded_by' => $client->id, 'name' => 'big', 'original_name' => 'big.pdf',
        'path' => 'c.pdf', 'mime_type' => 'application/pdf', 'size' => 5 * 1024 * 1024, 'checksum' => str_repeat('c', 64),
    ]);

    // The admin (System Administrator) can see and edit everything.
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('largest_files.0.edit_url', route('files.edit', File::query()->sole()->id, false))
            ->where('largest_files.0.download_url', route('files.download', File::query()->sole()->id, false))
            ->where('largest_files.0.uploader_edit_url', route('clients.edit', $client->id, false)),
    );

    // Uploader: can open/edit files, but has no edit_clients permission.
    $uploader = User::factory()->role(SystemRole::Uploader)->create();
    $this->actingAs($uploader)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('largest_files.0.edit_url', route('files.edit', File::query()->sole()->id, false))
            ->where('largest_files.0.uploader_edit_url', null),
    );
});

test('widgets follow the v1 permission split', function () {
    // Uploader: view_statistics + view_actions_log, but no counters or
    // system info in the default set.
    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($uploader)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('counters', null)
            ->where('system', null)
            ->has('transfers')
            ->has('recent'),
    );
});

test('system info is absent in the cloud edition even for administrators', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('system', null)->has('counters'),
    );
});

test('the expired-files widget lists expired files, respects view_statistics, and reflects the retention setting', function () {
    $expired = File::factory()->create([
        'uploaded_by' => $this->admin->id, 'name' => 'stale', 'original_name' => 'stale.pdf',
        'path' => 'stale.pdf', 'mime_type' => 'application/pdf', 'size' => 1024, 'checksum' => str_repeat('a', 64),
        'expires_at' => now()->subDay(),
    ]);
    File::factory()->create([
        'uploaded_by' => $this->admin->id, 'name' => 'fresh', 'original_name' => 'fresh.pdf',
        'path' => 'fresh.pdf', 'mime_type' => 'application/pdf', 'size' => 1024, 'checksum' => str_repeat('b', 64),
        'expires_at' => now()->addDay(),
    ]);

    app(Settings::class)->set(Setting::ExpiredFilesAutoDeleteEnabled, true);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('expired_files.count', 1)
            ->has('expired_files.files', 1)
            ->where('expired_files.files.0.id', $expired->id)
            ->where('expired_files.auto_delete_enabled', true)
            ->where('expired_files.files.0.edit_url', route('files.edit', $expired->id, false)),
    );

    // A staff member without view_statistics never receives the widget.
    $role = Role::query()->create(['name' => 'No Statistics', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'view_dashboard_counters']);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('expired_files', null),
    );
});

test('clients get the portal dashboard with their own numbers', function () {
    $client = User::factory()->client()->create(['storage_quota_mb' => 500]);
    $group = Group::query()->create(['name' => 'Mine', 'public' => true]);
    $secret = Group::query()->create(['name' => 'Hidden', 'public' => false]);
    $group->members()->attach($client->id);
    $secret->members()->attach($client->id);

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('shared.pdf', 10, 'application/pdf'),
        'name' => '', 'description' => '',
    ]);
    $file = File::query()->sole();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $response = $this->actingAs($client)->get('/dashboard');

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/dashboard')
            ->where('files_count', 1)
            // Only the public group counts — private stays invisible.
            ->where('groups_count', 1)
            // Usage counts the client's own uploads, not files merely
            // assigned to them (matches quota enforcement) — this file
            // was uploaded by the admin, so usage stays at 0.
            ->where('storage.used_bytes', 0)
            ->where('storage.quota_bytes', 500 * 1024 * 1024)
            ->has('latest_files', 1)
            ->where('latest_files.0.name', 'shared'),
    );

    expect((string) $response->getContent())->not->toContain('Hidden');
});

test('the recent-activity widget carries origin so an actorless entry is not mislabelled', function () {
    // An anonymous entry (a public/share-link download) and a system entry
    // are both actor_name null; only `origin` separates "Anonymous" from
    // "System" on the frontend. The dashboard used to rebuild the row inline
    // and drop it, so every actorless entry showed as "System".
    ActivityLog::query()->create([
        'actor_id' => null, 'actor_name' => null, 'actor_type' => null,
        'origin' => ActivityOrigin::Public,
        'action' => Action::PublicFileDownloaded,
        'subject_name' => 'report.pdf', 'created_at' => now(),
    ]);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('recent', 1)
            ->where('recent.0.origin', 'public')
            ->where('recent.0.actor_name', null),
    );
});
