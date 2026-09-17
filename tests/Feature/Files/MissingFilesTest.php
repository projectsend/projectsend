<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    app(Settings::class)->set(Setting::VirusScanningEnabled, false);
    app(Settings::class)->set(Setting::VirusScannerAddress, '');
});

/** A row with bytes behind it, as an upload would leave it. */
function fileWithBytes(array $overrides = []): File
{
    $path = 'uploads/'.Str::uuid()->toString().'.pdf';
    Storage::disk('files')->put($path, 'some bytes');

    return File::factory()->create(array_merge([
        'path' => $path,
        'disk' => 'files',
        'size' => 10,
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::BeforeScanning->value,
    ], $overrides));
}

test('the daily check finds a row whose bytes are gone, and says so once', function () {
    $here = fileWithBytes();
    $gone = fileWithBytes(['name' => 'Contrato']);
    Storage::disk('files')->delete($gone->path);

    $this->artisan('projectsend:check-missing-files')->assertSuccessful();

    expect($gone->refresh()->scan_status)->toBe(ScanStatus::Missing)
        ->and($here->refresh()->scan_status)->toBe(ScanStatus::NotScanned);

    $entry = ActivityLog::query()->where('action', Action::FileMissing)->sole();
    expect($entry->context['name'])->toBe('Contrato');

    // Stamped like any other verdict, so it shows up on the Activity tab
    // rather than being decided somewhere nobody can see.
    expect($gone->refresh()->scanned_at)->not->toBeNull();

    // Run again: the file is still gone, and that is not news.
    $this->artisan('projectsend:check-missing-files')->assertSuccessful();

    expect(ActivityLog::query()->where('action', Action::FileMissing)->count())->toBe(1);
});

test('a file that comes back is checked again rather than left for dead', function () {
    $file = fileWithBytes();
    $path = $file->path;
    Storage::disk('files')->delete($path);

    $this->artisan('projectsend:check-missing-files')->assertSuccessful();
    expect($file->refresh()->scan_status)->toBe(ScanStatus::Missing);

    // A remount, a restored backup, a bucket reconnected.
    Storage::disk('files')->put($path, 'some bytes');
    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, 'tcp://scanner.test:3310');

    $this->artisan('projectsend:check-missing-files')->assertSuccessful();

    // Back to the start: nothing here knows what the scanner had decided
    // about bytes that have since been away.
    expect($file->refresh()->scan_status)->toBe(ScanStatus::Pending);
});

test('a file that comes back on an installation with no scanner is simply available again', function () {
    $file = fileWithBytes();
    $path = $file->path;
    Storage::disk('files')->delete($path);
    $this->artisan('projectsend:check-missing-files');

    Storage::disk('files')->put($path, 'some bytes');
    $this->artisan('projectsend:check-missing-files')->assertSuccessful();

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::NotScanned)
        ->and($file->scan_status->isAvailable())->toBeTrue();
});

test('a missing file is not offered to anybody', function () {
    $client = User::factory()->client()->create();
    $file = fileWithBytes(['uploaded_by' => $this->admin->id]);
    app(App\Modules\Files\Sharing\FileSharing::class)->assign($file, $client, $client->name);

    expect(File::query()->visibleToClient($client)->count())->toBe(1);

    Storage::disk('files')->delete($file->path);
    $this->artisan('projectsend:check-missing-files');

    expect(File::query()->visibleToClient($client)->count())->toBe(0);

    // And says what it is rather than "not available", which would send
    // somebody looking for a permission that would let them through.
    $this->actingAs($this->admin)->get("/files/{$file->id}/download")
        ->assertStatus(423)
        ->assertSee('no longer on the server', false);
});

/*
|--------------------------------------------------------------------------
| The screen
|--------------------------------------------------------------------------
*/

test('missing files are listed beside the orphans, which are the same fault from the other end', function () {
    $gone = fileWithBytes(['name' => 'Contrato']);
    Storage::disk('files')->delete($gone->path);
    $this->artisan('projectsend:check-missing-files');

    $this->actingAs($this->admin)->get('/files/orphans')->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('tab', 'orphans')
            // Carried on both tabs, so the count in the tab label is there
            // before anybody clicks it.
            ->where('missing_count', 1),
    );

    $this->actingAs($this->admin)->get('/files/orphans?tab=missing')->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('tab', 'missing')
            ->where('missing.0.name', 'Contrato')
            ->where('missing.0.path', $gone->path),
    );
});

test('the screen needs the permission it always needed', function () {
    $staff = User::factory()->role(App\Modules\Identity\Permissions\SystemRole::Uploader)->create();
    App\Modules\Identity\Models\RolePermission::query()
        ->where('role_id', $staff->role_id)
        ->where('permission', App\Modules\Identity\Permissions\Permission::ImportOrphans->value)
        ->delete();
    forgetRequestState();

    $this->actingAs($staff)->get('/files/orphans?tab=missing')->assertForbidden();
});

test('removing a missing file takes the record with it', function () {
    $gone = fileWithBytes(['uploaded_by' => $this->admin->id]);
    Storage::disk('files')->delete($gone->path);
    $this->artisan('projectsend:check-missing-files');

    $this->actingAs($this->admin)->delete("/files/{$gone->id}")->assertRedirect();

    expect(File::query()->whereKey($gone->id)->exists())->toBeFalse();
});

test('the dashboard and the status document both count them', function () {
    app(Settings::class)->set(Setting::GettingStartedPending, false);
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '');

    $gone = fileWithBytes();
    Storage::disk('files')->delete($gone->path);
    $this->artisan('projectsend:check-missing-files');

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (Inertia\Testing\AssertableInertia $page) => $page->where('system.missing_files', 1),
    );

    Illuminate\Support\Facades\Artisan::call('projectsend:status', ['--json' => true]);
    $status = json_decode(Illuminate\Support\Facades\Artisan::output(), true);

    expect($status['health']['missing_files'])->toBe(1);
});
