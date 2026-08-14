<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    app(Settings::class)->set(Setting::ExpiredFilesAutoDeleteEnabled, true);
    app(Settings::class)->set(Setting::ExpiredFilesDeleteAfterDays, 30);
});

function expiredFile(?Carbon $expiresAt): File
{
    $path = '2026/07/'.Str::uuid()->toString().'.pdf';
    Storage::disk('files')->put($path, 'hello-world');

    return File::factory()->create([
        'uploaded_by' => test()->admin->id,
        'name' => 'doc',
        'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 11,
        'path' => $path,
        'disk' => 'files',
        'expires_at' => $expiresAt,
    ]);
}

test('a file expired less than the grace period ago survives', function () {
    $file = expiredFile(now()->subDays(10));

    $this->artisan('projectsend:purge-expired-files')->assertSuccessful();

    expect(File::query()->find($file->id))->not->toBeNull();
    Storage::disk('files')->assertExists($file->path);
});

test('a file past its grace period is deleted, its bytes removed, and the deletion logged as a system action', function () {
    $file = expiredFile(now()->subDays(31));

    $this->artisan('projectsend:purge-expired-files')->assertSuccessful();

    expect(File::query()->find($file->id))->toBeNull()
        ->and(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue();
    Storage::disk('files')->assertMissing($file->path);

    $entry = ActivityLog::query()->where('action', Action::ExpiredFileDeleted)->sole();
    expect($entry->actor_id)->toBeNull()
        ->and($entry->context['name'])->toBe('doc');
});

test('a file with no expiry date is never touched', function () {
    $file = expiredFile(null);

    $this->artisan('projectsend:purge-expired-files')->assertSuccessful();

    expect(File::query()->find($file->id))->not->toBeNull();
});

test('disabling auto-delete leaves every expired file untouched', function () {
    app(Settings::class)->set(Setting::ExpiredFilesAutoDeleteEnabled, false);
    $file = expiredFile(now()->subDays(60));

    $this->artisan('projectsend:purge-expired-files')->assertSuccessful();

    expect(File::query()->find($file->id))->not->toBeNull();
    Storage::disk('files')->assertExists($file->path);
});

test('a zero-day grace period deletes a file the same day it expires', function () {
    app(Settings::class)->set(Setting::ExpiredFilesDeleteAfterDays, 0);
    $file = expiredFile(now()->subMinute());

    $this->artisan('projectsend:purge-expired-files')->assertSuccessful();

    expect(File::query()->find($file->id))->toBeNull();
});
