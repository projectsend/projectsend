<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function ipLoggingTestFile(User $uploader): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

function ipLoggingShareLink(File $file): ShareLink
{
    return ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
    ]);
}

test('a download records an IP address by default', function () {
    // Settings are cached across tests (Cache::rememberForever survives
    // the per-test DB rollback) — reset explicitly rather than assuming
    // nothing else in the suite has touched this setting.
    app(Settings::class)->set(Setting::DownloadIpLogging, 'all');
    $file = ipLoggingTestFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $entry = ActivityLog::query()->where('action', Action::FileDownloaded)->sole();
    expect($entry->ip_address)->not->toBeNull();
});

test('setting download IP logging to none omits the IP for both authenticated and anonymous downloads', function () {
    app(Settings::class)->set(Setting::DownloadIpLogging, 'none');
    $file = ipLoggingTestFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download");
    $this->get('/s/'.ipLoggingShareLink($file)->token.'/download');

    $ips = ActivityLog::query()
        ->whereIn('action', [Action::FileDownloaded, Action::ShareLinkDownloaded])
        ->pluck('ip_address');

    expect($ips)->toHaveCount(2)->and($ips->filter()->count())->toBe(0);
});

test('anonymous_only records the IP only when there is no authenticated actor', function () {
    app(Settings::class)->set(Setting::DownloadIpLogging, 'anonymous_only');
    $file = ipLoggingTestFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download");
    $authenticatedEntry = ActivityLog::query()->where('action', Action::FileDownloaded)->sole();
    expect($authenticatedEntry->ip_address)->toBeNull();

    // actingAs() persists across requests within a test — logout so the
    // next request is genuinely anonymous, not still the admin.
    auth()->logout();
    $this->get('/s/'.ipLoggingShareLink($file)->token.'/download');
    $anonymousEntry = ActivityLog::query()->where('action', Action::ShareLinkDownloaded)->sole();
    expect($anonymousEntry->ip_address)->not->toBeNull();
});

test('a public group listing download respects the setting like any other download', function () {
    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');
    app(Settings::class)->set(Setting::DownloadIpLogging, 'none');

    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $file = ipLoggingTestFile($this->admin);
    $file->update(['public' => true]);
    $file->assignments()->create(['assignable_type' => Group::class, 'assignable_id' => $group->id]);

    $this->get("/public/files/{$file->slug}/download");

    $entry = ActivityLog::query()->where('action', Action::PublicFileDownloaded)->sole();
    expect($entry->ip_address)->toBeNull();
});

test('setting download IP logging to none also omits the IP for a file preview', function () {
    app(Settings::class)->set(Setting::DownloadIpLogging, 'none');
    $file = ipLoggingTestFile($this->admin);
    // Unlike every other test here, this one needs a type the preview
    // endpoint will actually serve — it only renders the formats on
    // ThumbnailGenerator::SUPPORTED_MIME_TYPES, so the shared PDF fixture
    // would 404 before anything reached the activity log.
    $file->update(['original_name' => 'report.png', 'mime_type' => 'image/png']);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview");

    $entry = ActivityLog::query()->where('action', Action::FilePreviewed)->sole();
    expect($entry->ip_address)->toBeNull();
});

test('a non-download action always records IP regardless of the setting', function () {
    app(Settings::class)->set(Setting::DownloadIpLogging, 'none');

    $this->actingAs($this->admin)->patch('/settings/profile', [
        'name' => 'Renamed',
        'email' => $this->admin->email,
    ]);

    $entry = ActivityLog::query()->where('action', Action::ProfileUpdated)->sole();
    expect($entry->ip_address)->not->toBeNull();
});
