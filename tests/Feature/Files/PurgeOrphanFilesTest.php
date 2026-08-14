<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    Storage::fake('files_external');
    $this->admin = User::factory()->create();

    app(Settings::class)->set(Setting::OrphanFilesAutoDeleteEnabled, true);
    app(Settings::class)->set(Setting::OrphanFilesDeleteAfterDays, 30);
});

/** Backdates a fake disk file's mtime — Storage::fake() writes real files, so this is a real touch(). */
function ageOrphanFile(string $path, int $daysOld, string $disk = 'files'): void
{
    touch(Storage::disk($disk)->path($path), now()->subDays($daysOld)->timestamp);
}

test('an orphan younger than the grace period survives', function () {
    makeOrphanFile('recent.pdf');
    ageOrphanFile('recent.pdf', 10);

    $this->artisan('projectsend:purge-orphan-files')->assertSuccessful();

    Storage::disk('files')->assertExists('recent.pdf');
});

test('an orphan past its grace period is deleted and logged as a system action', function () {
    makeOrphanFile('stale.pdf');
    ageOrphanFile('stale.pdf', 31);

    $this->artisan('projectsend:purge-orphan-files')->assertSuccessful();

    Storage::disk('files')->assertMissing('stale.pdf');

    $entry = ActivityLog::query()->where('action', Action::OrphanFileAutoDeleted)->sole();
    expect($entry->actor_id)->toBeNull()
        ->and($entry->context['name'])->toBe('stale.pdf')
        ->and($entry->context['disk'])->toBe('files');
});

test('disabling auto-delete leaves every orphan untouched regardless of age', function () {
    app(Settings::class)->set(Setting::OrphanFilesAutoDeleteEnabled, false);
    makeOrphanFile('stale.pdf');
    ageOrphanFile('stale.pdf', 90);

    $this->artisan('projectsend:purge-orphan-files')->assertSuccessful();

    Storage::disk('files')->assertExists('stale.pdf');
});

test('excluded derived-artifact paths are never deleted even when old', function () {
    makeOrphanFile('thumbnails/1.jpg');
    ageOrphanFile('thumbnails/1.jpg', 90);
    makeOrphanFile('zips/download.zip');
    ageOrphanFile('zips/download.zip', 90);

    $this->artisan('projectsend:purge-orphan-files')->assertSuccessful();

    Storage::disk('files')->assertExists('thumbnails/1.jpg');
    Storage::disk('files')->assertExists('zips/download.zip');
});

test('once external storage is active, the command is a complete no-op, even for an old local orphan', function () {
    activateExternalStorage();

    makeOrphanFile('stale.pdf');
    ageOrphanFile('stale.pdf', 90);

    $this->artisan('projectsend:purge-orphan-files')->assertSuccessful();

    Storage::disk('files')->assertExists('stale.pdf');
    expect(ActivityLog::query()->where('action', Action::OrphanFileAutoDeleted)->exists())->toBeFalse();
});
