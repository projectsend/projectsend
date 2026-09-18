<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Sharing\CreateShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * What somebody following a public link is told about a file nothing
 * checked.
 *
 * An installation that scans can still let files through — too large for
 * the scanner, an archive it could not open, or an upload that arrived
 * while the scanner was down. The uploader sees that on their own file and
 * staff see it in the library. The person holding the link sees the same
 * page as for a file that passed, and they neither chose the policy nor
 * can see the setting.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::VirusScanningEnabled, true);
    $this->settings->set(Setting::VirusScannerAddress, 'tcp://scanner.test:3310');
    $this->settings->set(Setting::PublicListingEnabled, true);
    $this->settings->set(Setting::PublicListingSlug, 'public');
    $this->settings->set(Setting::Theme, 'default');
});

/** The `unscanned` prop on the share page for a file in this state. */
function sharedFileNotice(array $scan): bool
{
    $file = File::factory()->create(array_merge(['uploaded_by' => test()->admin->id], $scan));
    $link = app(CreateShareLink::class)->for($file, test()->admin);

    $notice = null;

    test()->get("/s/{$link->token}")->assertInertia(function (AssertableInertia $page) use (&$notice) {
        $notice = $page->toArray()['props']['unscanned'];
    });

    return $notice;
}

test('a link to a file nothing checked says so', function () {
    expect(sharedFileNotice([
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::TooLarge->value,
    ]))->toBeTrue();
});

test('the same for a file that went out while the scanner was down, or that it could not open', function () {
    foreach ([NotScannedReason::ScannerUnavailable, NotScannedReason::Encrypted] as $reason) {
        expect(sharedFileNotice(['scan_status' => ScanStatus::NotScanned, 'scan_note' => $reason->value]))
            ->toBeTrue($reason->value);
    }
});

test('a file that passed says nothing', function () {
    expect(sharedFileNotice(['scan_status' => ScanStatus::Clean]))->toBeFalse();
});

test('a file from before this installation scanned says nothing', function () {
    // Every file on an installation that has only just switched scanning
    // on is in this state. Saying it about all of them says nothing about
    // any of them.
    expect(sharedFileNotice([
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::BeforeScanning->value,
    ]))->toBeFalse();

    expect(sharedFileNotice(['scan_status' => ScanStatus::NotScanned, 'scan_note' => null]))->toBeFalse();
});

test('an installation that does not scan says nothing about any of it', function () {
    $this->settings->set(Setting::VirusScanningEnabled, false);

    expect(sharedFileNotice([
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::TooLarge->value,
    ]))->toBeFalse();
});

test('the public file page says it too, in every theme', function (string $theme) {
    $this->settings->set(Setting::Theme, $theme);

    $group = Group::query()->create(['name' => 'Showcase', 'public' => true]);
    $file = File::factory()->public()->create([
        'uploaded_by' => $this->admin->id,
        'scan_status' => ScanStatus::NotScanned,
        'scan_note' => NotScannedReason::ScannerUnavailable->value,
    ]);
    shareFileWithGroup($file, $group);

    $this->get("/public/files/{$file->slug}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component("public/themes/{$theme}/file")
            ->where('unscanned', true),
    );
})->with(['default', 'compact', 'drive', 'gallery']);
