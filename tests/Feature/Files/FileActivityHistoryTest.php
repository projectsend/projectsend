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

test('the file activity history filters by action, and offers only the actions that happened', function () {
    $file = uploadImageFile($this->admin);
    $client = User::factory()->client()->create(['name' => 'Downloading Client']);

    $log = function (Action $action, ?User $actor = null) use ($file): void {
        ActivityLog::create([
            'action' => $action,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_type' => $actor === null ? null : 'client',
            'created_at' => now(),
        ]);
    };

    $log(Action::FileDownloaded, $client);
    $log(Action::FileDownloaded, $client);
    $log(Action::ShareLinkDownloaded);
    $log(Action::FilePreviewed, $client);

    $options = $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('activity/subject'))
        ->viewData('page')['props']['action_options'];

    $byKey = collect($options)->keyBy('key');

    // The upload happened; the eighty-odd actions that cannot apply to a
    // file are not offered at all.
    expect($byKey->keys()->all())->toEqualCanonicalizing(['downloads', 'file.uploaded', 'file.downloaded', 'file.previewed', 'share_link.downloaded'])
        ->and($byKey['downloads']['count'])->toBe(3)
        ->and($byKey['file.downloaded']['count'])->toBe(2);

    // A file whose log holds only one flavour of download gets no group:
    // it would filter to exactly what its single member already offers.
    $simple = uploadImageFile($this->admin, 'simple.jpg');
    ActivityLog::create([
        'action' => Action::FileDownloaded,
        'subject_type' => $simple->getMorphClass(),
        'subject_id' => $simple->id,
        'subject_name' => $simple->name,
        'actor_id' => $this->admin->id,
        'actor_name' => $this->admin->name,
        'actor_type' => 'staff',
        'created_at' => now(),
    ]);

    $simpleOptions = $this->actingAs($this->admin)->get("/files/{$simple->id}/activity/history")
        ->viewData('page')['props']['action_options'];

    expect(collect($simpleOptions)->pluck('key')->all())->toEqualCanonicalizing(['file.uploaded', 'file.downloaded']);

    // The group covers all three download flavours, one of them anonymous.
    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?action=downloads")->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 3)->where('filters.action', 'downloads'),
    );

    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?action=file.previewed")->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 1)->where('entries.0.actor_name', 'Downloading Client'),
    );

    // Narrowing by who acted, and by day, works the same as the main log.
    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?actor=Downloading")->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 3),
    );

    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?from=".now()->addDay()->toDateString())->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 0),
    );

    // An action that exists but never touched this file filters to nothing
    // rather than erroring; a value that is neither action nor group is
    // rejected outright.
    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?action=user.created")->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 0),
    );
    $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?action=nonsense")->assertSessionHasErrors('action');
});

test('the access endpoint answers who downloaded and who previewed the file', function () {
    $file = uploadImageFile($this->admin);
    $client = User::factory()->client()->create(['name' => 'Acme Design']);

    $log = function (Action $action, ?User $actor = null, ?string $ip = null) use ($file): void {
        ActivityLog::create([
            'action' => $action,
            'subject_type' => $file->getMorphClass(),
            'subject_id' => $file->id,
            'subject_name' => $file->name,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_type' => $actor === null ? null : 'client',
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    };

    $log(Action::FileDownloaded, $client, '10.0.0.1');
    $log(Action::ShareLinkDownloaded, null, '10.0.0.2');
    $log(Action::PublicFileDownloaded);
    $log(Action::FilePreviewed, $client);
    // Neither a download nor a preview: this tab is not the activity log.
    $log(Action::FileUpdated, $this->admin);

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/access")->assertOk();

    expect($response->json('downloads_total'))->toBe(3)
        ->and($response->json('previews_total'))->toBe(1)
        // Four rows, not five: the edit entry (and the upload) are excluded.
        ->and($response->json('entries'))->toHaveCount(4)
        ->and($response->json('entries.0.template'))->toBe('Previewed the file ":subject"')
        ->and($response->json('entries.0.actor_name'))->toBe('Acme Design')
        ->and($response->json('entries.3.ip_address'))->toBe('10.0.0.1');

    // The buttons carry the filter the history page understands: a group
    // for downloads, the action itself for previews (which have no group
    // while only one action records them).
    expect($response->json('downloads_url'))->toBe("/files/{$file->id}/activity/history?action=downloads")
        ->and($response->json('previews_url'))->toBe("/files/{$file->id}/activity/history?action=file.previewed");

    // Following either one lands on a history page filtered to just that.
    $this->actingAs($this->admin)->get($response->json('downloads_url'))->assertInertia(
        fn (AssertableInertia $page) => $page->component('activity/subject')->has('entries', 3),
    );
    $this->actingAs($this->admin)->get($response->json('previews_url'))->assertInertia(
        fn (AssertableInertia $page) => $page->has('entries', 1),
    );
});

test('the access endpoint is capped at 20 rows and honours the activity-log permission', function () {
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
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/access")->assertOk();
    expect($response->json('entries'))->toHaveCount(20)
        ->and($response->json('downloads_total'))->toBe(24);

    $role = Role::query()->create(['name' => 'No Access Log', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([['role_id' => $role->id, 'permission' => 'upload']]);
    $restricted = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($restricted)->getJson("/files/{$file->id}/access")->assertForbidden();
});

test('a filter arrived at from a button stays visible in the dropdown at a count of zero', function () {
    $file = uploadImageFile($this->admin);
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

    // One download flavour, so the group would normally be left out — but
    // the "View all downloads" button links straight to it, and a select
    // whose value is missing from its own options renders blank.
    $options = $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?action=downloads")
        ->viewData('page')['props']['action_options'];
    expect(collect($options)->firstWhere('key', 'downloads'))->not->toBeNull();

    // Same for a single action the file has never seen.
    $options = $this->actingAs($this->admin)->get("/files/{$file->id}/activity/history?action=file.previewed")
        ->viewData('page')['props']['action_options'];
    expect(collect($options)->firstWhere('key', 'file.previewed'))->toMatchArray(['count' => 0]);
});
