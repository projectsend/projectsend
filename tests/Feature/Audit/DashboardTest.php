<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

test('the transfers window is read in UTC, whatever zone the viewer is in', function () {
    // The boundaries are built in the viewer's zone on purpose, but the
    // column is UTC and the query builder formats a Carbon in its own zone
    // with the offset thrown away. For Asia/Tokyo that asks for
    // "2026-08-16 00:00:00" where the viewer's window really begins at
    // 2026-08-15 15:00:00Z — the first nine hours of their week are
    // missing from the chart.
    $viewer = User::factory()->create(['timezone' => 'Asia/Tokyo']);
    $file = File::factory()->create(['uploaded_by' => $viewer->id, 'name' => 'early upload']);

    // 11:00 on the 22nd in Tokyo. "Last week" is their 16th to their 22nd.
    $this->travelTo(Carbon::parse('2026-08-22 02:00:00', 'UTC'));

    // 01:00 on the 16th in Tokyo: the first hour of the window, and before
    // "2026-08-16 00:00:00" read as UTC.
    ActivityLog::query()->create([
        'actor_id' => $viewer->id, 'actor_name' => $viewer->name, 'actor_type' => 'staff',
        'action' => Action::FileUploaded, 'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id, 'subject_name' => $file->name,
        'created_at' => Carbon::parse('2026-08-15 16:00:00', 'UTC'),
    ]);

    $props = $this->actingAs($viewer)->get('/dashboard?range=last_week')->assertOk()->viewData('page')['props'];

    expect(collect($props['transfers'])->sum('uploads'))->toBe(1)
        ->and(collect($props['transfers'])->firstWhere('date', '2026-08-16')['uploads'])->toBe(1);

    $this->travelBack();
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

test('the portal dashboard does not count a file the client can no longer open', function () {
    // File::scopeVisibleToClient ends in notExpired(), so /my-files stops
    // listing an expired file and the download is refused. The dashboard
    // restated the assignment half of that scope without its ending, and
    // went on offering the file's name.
    $client = User::factory()->client()->create();
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'old-offer',
        'expires_at' => now()->subDay(),
    ]);
    shareFileWith($file, $client);

    $this->actingAs($client)->get("/files/{$file->id}/download")->assertForbidden();

    $this->actingAs($client)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files_count', 0)
            ->has('latest_files', 0),
    );
});

test('the portal dashboard counts a file that reaches the client through a folder', function () {
    // The other direction of the same substitution: a shared folder and a
    // client's own upload are two of the three ways into
    // scopeVisibleToClient that the hand-rolled query left out, so the
    // number was under the truth as well as over it.
    $client = User::factory()->client()->create();
    $folder = makeFolder('Shared with them');
    FolderAssignment::query()->create([
        'folder_id' => $folder->id,
        'assignable_type' => $client->getMorphClass(),
        'assignable_id' => $client->id,
    ]);

    File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'in-the-folder',
        'folder_id' => $folder->id,
    ]);
    File::factory()->create(['uploaded_by' => $client->id, 'name' => 'their-own-upload']);

    $this->actingAs($client)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('files_count', 2)
            ->has('latest_files', 2),
    );
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

test('the recent-activity widget shows a scoped viewer only what their own log would', function () {
    // view_actions_log is not the whole answer for a client-scoped viewer:
    // an entry carries the subject's name, so an unscoped widget reads out
    // the name of every file in the installation to somebody who gets a
    // 403 on the files themselves. The Client Manager role ships with the
    // permission, so this is the default configuration, not an exotic one.
    $role = Role::query()->create(['name' => 'Scoped log reader', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'view_actions_log'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);

    $scoped = User::factory()->create(['role_id' => $role->id]);
    $client = User::factory()->client()->create();
    $scoped->assignedClients()->attach($client->id);

    $theirs = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Q3 delinquent accounts']);

    $mine = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Statement']);
    shareFileWith($mine, $client);

    foreach ([$theirs, $mine] as $file) {
        ActivityLog::query()->create([
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_type' => $this->admin->type->value,
            'action' => Action::FileUploaded,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'created_at' => now(),
        ]);
    }

    $this->actingAs($scoped)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('recent', 1)
            ->where('recent.0.replacements.subject', 'Statement'),
    );
});

test('an unscoped viewer still sees the whole installation in the widget', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Anything']);

    ActivityLog::query()->create([
        'actor_id' => $this->admin->id,
        'actor_name' => $this->admin->name,
        'actor_type' => $this->admin->type->value,
        'action' => Action::FileUploaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'subject_name' => 'Anything',
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->has('recent', 1)->where('recent.0.replacements.subject', 'Anything'),
    );
});

// The shipped Client Manager role is client-scoped and holds
// view_statistics, so these three widgets are the default configuration,
// not a custom one. A file's name is the part that leaks: "Q3 delinquent
// accounts" says plenty without ever being downloadable.
test('the statistics widgets name only files inside the viewer scope', function () {
    $role = Role::query()->create(['name' => 'Scoped stats', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'view_statistics'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);

    $scoped = User::factory()->create(['role_id' => $role->id]);
    $client = User::factory()->client()->create();
    $scoped->assignedClients()->attach($client->id);

    $mine = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Statement',
        'size' => 10_000,
    ]);
    shareFileWith($mine, $client);

    // Expired files reach a scoped viewer only through their own uploads:
    // File::scopeVisibleToClient ends in notExpired(), so an expired file
    // belonging to one of their clients is not in their library at all.
    $ownExpired = File::factory()->create([
        'uploaded_by' => $scoped->id,
        'name' => 'My Own Expired',
        'size' => 1_000,
        'expires_at' => now()->subDay(),
    ]);

    // Bigger, sooner-expired, and none of this viewer's business.
    File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Q3 delinquent accounts',
        'size' => 99_000_000,
        'expires_at' => now()->subDays(5),
    ]);

    $this->actingAs($scoped)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('largest_files', 2)
            ->where('largest_files.0.name', 'Statement')
            ->where('largest_files.1.name', 'My Own Expired')
            ->has('expired_files.files', 1)
            ->where('expired_files.files.0.name', 'My Own Expired')
            // The count has to agree with the list, or the number
            // describes files the list is not allowed to name.
            ->where('expired_files.count', 1)
            // And the widget has to say which list it is: a warning about
            // what is due to be deleted, showing only the viewer's own
            // uploads, reads as "nothing to worry about" otherwise.
            ->where('expired_files.scoped', true),
    );
});

test('an unscoped viewer still sees the whole installation in those widgets', function () {
    File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Anything',
        'size' => 5_000,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('largest_files.0.name', 'Anything')
            ->where('expired_files.count', 1)
            // Unscoped: the widget keeps its plain title and its plain
            // meaning, everything expired on the installation.
            ->where('expired_files.scoped', false),
    );
});

// The top-clients widget names clients, so the roster is the question,
// not the library. A stranger's upload can sit inside a scoped viewer's
// library — shared with a group one of their own clients is in — which
// put the stranger's name on the widget while the file itself was
// legitimately visible.
test('the top-clients widget names only clients on the viewer roster', function () {
    $role = Role::query()->create(['name' => 'Scoped stats roster', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'view_statistics'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);

    $viewer = User::factory()->create(['role_id' => $role->id]);
    $mine = User::factory()->client()->create(['name' => 'My Own Client']);
    $viewer->assignedClients()->attach($mine->id);

    $stranger = User::factory()->client()->create(['name' => 'Stranger Client Ltd']);

    // Both clients are in one group, and the stranger's upload is shared
    // with it — so my client may legitimately read the file, and the
    // file is legitimately inside my library.
    $group = Group::query()->create(['name' => 'Shared', 'slug' => 'shared-stats', 'public' => false]);
    $group->members()->syncWithoutDetaching([$mine->id, $stranger->id]);

    $file = File::factory()->create(['uploaded_by' => $stranger->id, 'name' => 'Theirs', 'size' => 5_000_000]);
    shareFileWithGroup($file, $group);

    // Something of my own client's, so the widget is not empty for the
    // wrong reason.
    File::factory()->create(['uploaded_by' => $mine->id, 'name' => 'Ours', 'size' => 1_000]);

    $this->actingAs($viewer)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('top_clients_by_storage', 1)
            ->where('top_clients_by_storage.0.name', 'My Own Client'),
    );
});
