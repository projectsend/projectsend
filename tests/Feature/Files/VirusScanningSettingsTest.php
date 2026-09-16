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

    // The dashboard test below lands on the greeting instead of the
    // dashboard otherwise — and settings survive RefreshDatabase's
    // rollback in the cache, so both markers are stated rather than
    // assumed.
    app(Settings::class)->set(Setting::GettingStartedPending, false);
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '');
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

test('the answer actually reaches the screen', function () {
    // It did not, at first: the page read a flash prop that nothing
    // shares, so the button appeared to do nothing at all. The result is
    // handed over as a page prop, like the CAPTCHA screen's.
    app()->instance(VirusScanner::class, new FakeVirusScanner(ScanVerdict::infected('Eicar-Test-Signature')));

    $this->actingAs($this->admin)->post('/system/settings/virus-scanning/test');

    $this->actingAs($this->admin)->get('/system/settings/virus-scanning')->assertInertia(
        fn (AssertableInertia $page) => $page->where('test_result.ok', true),
    );
});

test('the screen opens on the scanner tab, and the other one is a link away', function () {
    $this->actingAs($this->admin)->get('/system/settings/virus-scanning')->assertInertia(
        fn (AssertableInertia $page) => $page->where('tab', 'scanner'),
    );

    $this->actingAs($this->admin)->get('/system/settings/virus-scanning?tab=options')->assertInertia(
        fn (AssertableInertia $page) => $page->where('tab', 'options'),
    );

    // Anything else is the default rather than an error: a stale
    // bookmark should open the page, not break it.
    $this->actingAs($this->admin)->get('/system/settings/virus-scanning?tab=nonsense')->assertInertia(
        fn (AssertableInertia $page) => $page->where('tab', 'scanner'),
    );
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

/*
|--------------------------------------------------------------------------
| Saying so where nobody is looking
|--------------------------------------------------------------------------
*/

/** The scanning block of the status document, as the fleet probe reads it. */
function scanningStatus(): array
{
    Illuminate\Support\Facades\Artisan::call('projectsend:status', ['--json' => true]);

    /** @var array<string, mixed> $document */
    $document = json_decode(Illuminate\Support\Facades\Artisan::output(), true);

    return $document['scanning'];
}

test('the status command reports scanning as off when it is off', function () {
    $this->artisan('projectsend:status')->assertSuccessful();

    // statusJson() lives in StatusCommandTest — Pest loads every test
    // file into one process, so a second copy here would be a redeclare.
    $status = scanningStatus();

    expect($status['enabled'])->toBeFalse()
        // Null, not false: there is nothing to reach. A watcher must be
        // able to tell that from a scanner that should answer and does not.
        ->and($status['reachable'])->toBeNull();
});

test('the status command reports an unreachable scanner and what got through', function () {
    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, 'tcp://nowhere.test:3310');
    app()->instance(VirusScanner::class, (new FakeVirusScanner)->reports(ScannerStatus::unreachable('no answer')));

    app(App\Modules\Audit\ActivityLogger::class)->logSystem(App\Modules\Audit\Action::FileNotScanned, [
        'id' => 1, 'name' => 'x', 'reason' => 'scanner_unavailable',
    ]);

    // statusJson() lives in StatusCommandTest — Pest loads every test
    // file into one process, so a second copy here would be a redeclare.
    $status = scanningStatus();

    expect($status['enabled'])->toBeTrue()
        ->and($status['reachable'])->toBeFalse()
        ->and($status['let_through_24h'])->toBe(1);
});

test('the dashboard reports a healthy scanner, and says so when it stops answering', function () {
    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, 'tcp://scanner.test:3310');
    app()->instance(VirusScanner::class, new FakeVirusScanner);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('system.scanning.reachable', true),
    );

    app()->instance(VirusScanner::class, (new FakeVirusScanner)->reports(ScannerStatus::unreachable('no answer')));

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('system.scanning.reachable', false),
    );
});

/*
|--------------------------------------------------------------------------
| Nothing is checking what this installation accepts
|--------------------------------------------------------------------------
*/

test('an installation with no scanner is told so on the dashboard', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('system.scanning.configured', false)
            ->where('system.scanning.reachable', false),
    );
});

test('a hosted installation is not told: the scanner is not its job', function () {
    // The capability, not an edition check — see
    // Capability::VirusScanningConnect. The System card is community-only
    // in its own right, so on a hosted installation the whole card is
    // absent and the notice with it; the assertion below is about the
    // card, and the one on the settings screen (further down) is what
    // pins the capability itself.
    config(['projectsend.edition' => App\Modules\Platform\Capabilities\Edition::Cloud]);
    forgetRequestState();

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('system', null),
    );
});

test('a working scanner is still reported, by name', function () {
    // The row is always there, like the delivery and storage rows beside
    // it: "my uploads are checked by ClamAV" is worth confirming at a
    // glance, not only worth saying when it is false.
    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, 'tcp://scanner.test:3310');
    app()->instance(VirusScanner::class, new FakeVirusScanner);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('system.scanning.configured', true)
            ->where('system.scanning.reachable', true)
            ->where('system.scanning.engine', 'FakeAV 1.0')
            ->where('system.scanning.let_through_24h', 0),
    );
});

test('a hosted installation cannot connect a scanner of its own, but keeps its policies', function () {
    config(['projectsend.edition' => App\Modules\Platform\Capabilities\Edition::Cloud]);
    forgetRequestState();

    $this->actingAs($this->admin)->get('/system/settings/virus-scanning')->assertInertia(
        fn (AssertableInertia $page) => $page->where('managed', true),
    );

    $this->actingAs($this->admin)->post('/system/settings/virus-scanning/test')->assertForbidden();

    $this->actingAs($this->admin)->patch('/system/settings/virus-scanning', [
        'enabled' => true,
        'address' => 'tcp://mine:3310',
        'max_size_mb' => 256,
        'unscannable_policy' => 'block',
        'scanner_down_policy' => 'hold',
        'wait_minutes' => 10,
        'existing_rate_per_minute' => 60,
    ])->assertSessionHasNoErrors();

    expect(app(Settings::class)->get(Setting::VirusScannerAddress))->toBe('')
        ->and(app(Settings::class)->get(Setting::VirusUnscannablePolicy))->toBe('block');
});
