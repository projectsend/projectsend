<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    // Settings survive RefreshDatabase's rollback in the cache.
    app(Settings::class)->set(Setting::DefaultFileExpiryDays, 0);
});

function storeForExpiry(User $uploader, Action $action = Action::FileUploaded): File
{
    return app(StoreUploadedFile::class)->create(
        uploader: $uploader,
        originalName: 'photo.jpg',
        path: 'uploads/photo.jpg',
        mimeType: 'image/jpeg',
        size: 10,
        checksum: str_repeat('a', 64),
        action: $action,
    );
}

test('the default expiry is shown and saved on the file retention page', function () {
    app(Settings::class)->set(Setting::DefaultFileExpiryDays, 14);

    $this->actingAs($this->admin)->get('/system/settings/file-retention')->assertInertia(
        fn (AssertableInertia $page) => $page->where('default_file_expiry_days', 14),
    );

    $this->actingAs($this->admin)->patch('/system/settings/file-retention', [
        'default_file_expiry_days' => 30,
        'expired_files_auto_delete_enabled' => false,
        'expired_files_delete_after_days' => 30,
        'orphan_files_auto_delete_enabled' => false,
        'orphan_files_delete_after_days' => 30,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(Settings::class)->get(Setting::DefaultFileExpiryDays))->toBe(30);
});

test('a negative default expiry is rejected', function () {
    $this->actingAs($this->admin)->patch('/system/settings/file-retention', [
        'default_file_expiry_days' => -1,
        'expired_files_auto_delete_enabled' => false,
        'expired_files_delete_after_days' => 30,
        'orphan_files_auto_delete_enabled' => false,
        'orphan_files_delete_after_days' => 30,
    ])->assertSessionHasErrors('default_file_expiry_days');
});

test('a file staff upload expires at the end of the day N days later, where the uploader lives', function () {
    app(Settings::class)->set(Setting::DefaultFileExpiryDays, 7);
    // 23:30 on the 1st in Buenos Aires is already the 2nd in UTC: the
    // seven days count from the uploader's own today.
    $this->travelTo(Carbon::parse('2026-11-02 02:30:00', 'UTC'));

    $file = storeForExpiry($this->admin);

    expect($file->refresh()->expires_at?->setTimezone('America/Argentina/Buenos_Aires')->format('Y-m-d H:i:s'))->toBe('2026-11-08 23:59:59');
});

test('with the default at 0, a new file never expires', function () {
    expect(storeForExpiry($this->admin)->expires_at)->toBeNull();
});

test('a file a client uploads gets no default expiry', function () {
    app(Settings::class)->set(Setting::DefaultFileExpiryDays, 7);

    expect(storeForExpiry(User::factory()->client()->create())->expires_at)->toBeNull();
});

test('an imported orphan gets no default expiry, so a restored file is not set up to be purged', function () {
    app(Settings::class)->set(Setting::DefaultFileExpiryDays, 7);

    expect(storeForExpiry($this->admin, Action::FileImported)->expires_at)->toBeNull();
});
