<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScannerStatus;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\ScanVerdict;
use App\Modules\Files\Scanning\VirusScanner;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeVirusScanner;

beforeEach(function () {
    $this->admin = User::factory()->create();

    app(Settings::class)->set(Setting::VirusScanningEnabled, false);
    app(Settings::class)->set(Setting::VirusScannerAddress, '');
    app(Settings::class)->set(Setting::VirusUnscannablePolicy, 'allow');
    app(Settings::class)->set(Setting::VirusScannerDownPolicy, 'allow');

    config()->set('projectsend.scanning.address', null);
});

test('the screen shows what is configured and what is outstanding', function () {
    File::factory()->create(['scan_status' => ScanStatus::NotScanned, 'scan_note' => NotScannedReason::ScannerUnavailable->value]);
    File::factory()->create(['scan_status' => ScanStatus::NotScanned, 'scan_note' => NotScannedReason::BeforeScanning->value]);
    File::factory()->create(['scan_status' => ScanStatus::Infected, 'scan_note' => 'X']);

    $this->actingAs($this->admin)->get('/system/settings/virus-scanning')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/virus-scanning')
            ->where('managed', false)
            ->where('counts.let_through', 1)
            ->where('counts.never_scanned', 1)
            ->where('counts.quarantined', 1),
    );
});

test('scanning cannot be switched on without an address', function () {
    $this->actingAs($this->admin)->patch('/system/settings/virus-scanning', [
        'enabled' => true,
        'address' => '',
        'max_size_mb' => 512,
        'unscannable_policy' => 'allow',
        'scanner_down_policy' => 'allow',
        'wait_minutes' => 10,
        'existing_rate_per_minute' => 60,
    ])->assertSessionHasErrors('address');

    expect(app(Settings::class)->get(Setting::VirusScanningEnabled))->toBeFalse();
});

test('an address and the policies are saved', function () {
    $this->actingAs($this->admin)->patch('/system/settings/virus-scanning', [
        'enabled' => true,
        'address' => 'tcp://clamav:3310',
        'max_size_mb' => 256,
        'unscannable_policy' => 'block',
        'scanner_down_policy' => 'hold',
        'wait_minutes' => 20,
        'existing_rate_per_minute' => 30,
    ])->assertSessionHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->get(Setting::VirusScanningEnabled))->toBeTrue()
        ->and($settings->get(Setting::VirusScannerAddress))->toBe('tcp://clamav:3310')
        ->and($settings->get(Setting::VirusUnscannablePolicy))->toBe('block')
        ->and($settings->get(Setting::VirusScannerDownPolicy))->toBe('hold');
});

test('a managed installation keeps its policies but not the connection', function () {
    config()->set('projectsend.scanning.address', 'tcp://fleet-scanner:3310');

    $this->actingAs($this->admin)->get('/system/settings/virus-scanning')->assertInertia(
        fn (AssertableInertia $page) => $page->where('managed', true)->where('enabled', true)->where('address', ''),
    );

    // Sending the fields anyway changes nothing about the connection, and
    // cannot switch scanning off.
    $this->actingAs($this->admin)->patch('/system/settings/virus-scanning', [
        'enabled' => false,
        'address' => 'tcp://somewhere-else:3310',
        'max_size_mb' => 100,
        'unscannable_policy' => 'block',
        'scanner_down_policy' => 'hold',
        'wait_minutes' => 10,
        'existing_rate_per_minute' => 60,
    ])->assertSessionHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->get(Setting::VirusScannerAddress))->toBe('')
        ->and(app(App\Modules\Files\Scanning\ScanningConfig::class)->enabled())->toBeTrue()
        ->and($settings->get(Setting::VirusUnscannablePolicy))->toBe('block');
});

/*
|--------------------------------------------------------------------------
| The test button
|--------------------------------------------------------------------------
*/

test('the test button says when the scanner cannot be reached', function () {
    app()->instance(VirusScanner::class, (new FakeVirusScanner)->reports(ScannerStatus::unreachable('No answer from tcp://clamav:3310.')));

    $this->actingAs($this->admin)->post('/system/settings/virus-scanning/test')
        ->assertSessionHas('scanner_test_result', fn (array $result): bool => $result['ok'] === false);
});

test('the test button says when the scanner answers but detects nothing', function () {
    // The failure that looks like success: reachable, and blind. Empty or
    // broken virus definitions do exactly this.
    app()->instance(VirusScanner::class, new FakeVirusScanner(ScanVerdict::clean('FakeAV 1.0')));

    $this->actingAs($this->admin)->post('/system/settings/virus-scanning/test')
        ->assertSessionHas('scanner_test_result', fn (array $result): bool => $result['ok'] === false
            && str_contains($result['message'], 'did not detect'));
});

test('the test button confirms a working scanner', function () {
    app()->instance(VirusScanner::class, new FakeVirusScanner(ScanVerdict::infected('Eicar-Test-Signature')));

    $this->actingAs($this->admin)->post('/system/settings/virus-scanning/test')
        ->assertSessionHas('scanner_test_result', fn (array $result): bool => $result['ok'] === true);
});

test('the test button sends the standard test file, not an empty stream', function () {
    $scanner = new FakeVirusScanner(ScanVerdict::infected('Eicar-Test-Signature'));
    app()->instance(VirusScanner::class, $scanner);

    $this->actingAs($this->admin)->post('/system/settings/virus-scanning/test');

    expect($scanner->scans)->toBe(1)
        ->and($scanner->sizes[0])->toBe(68);
});

test('only somebody who can edit settings may test or save', function () {
    $staff = User::factory()->role(App\Modules\Identity\Permissions\SystemRole::Uploader)->create();

    $this->actingAs($staff)->get('/system/settings/virus-scanning')->assertForbidden();
    $this->actingAs($staff)->post('/system/settings/virus-scanning/test')->assertForbidden();
});
