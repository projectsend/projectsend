<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanningConfig;
use App\Modules\Files\Scanning\ScanOutcome;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\VirusScanner;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The virus scanning screen.
 *
 * Two of these settings decide what happens when the scanner cannot
 * answer, and both default to letting files through. That is a
 * deliberate choice (see docs/feature-virus-scanning.md) and it is the
 * reason this screen states the count of files currently allowed through
 * unscanned rather than leaving it to be discovered: a scanner that has
 * quietly stopped protecting anything looks exactly like one that is
 * working.
 *
 * Where a managed configuration names a scanner, the connection is not
 * this screen's to change and scanning cannot be switched off — the
 * policies still are. Same shape as the CAPTCHA screen under managed
 * keys.
 */
class VirusScanningSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ScanningConfig $config,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/virus-scanning', [
            'enabled' => $this->config->enabled(),
            'managed' => $this->config->isManaged(),
            'address' => $this->config->isManaged() ? '' : $this->settings->get(Setting::VirusScannerAddress),
            'max_size_mb' => $this->settings->get(Setting::VirusScanMaxSizeMb),
            'unscannable_policy' => $this->settings->get(Setting::VirusUnscannablePolicy),
            'scanner_down_policy' => $this->settings->get(Setting::VirusScannerDownPolicy),
            'wait_minutes' => $this->settings->get(Setting::VirusScannerWaitMinutes),
            'existing_rate_per_minute' => $this->settings->get(Setting::VirusScanExistingRatePerMinute),
            'counts' => $this->counts(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'max_size_mb' => ['required', 'integer', 'min:0', 'max:4096'],
            'unscannable_policy' => ['required', Rule::in(['allow', 'block'])],
            'scanner_down_policy' => ['required', Rule::in(['allow', 'hold'])],
            'wait_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'existing_rate_per_minute' => ['required', 'integer', 'min:1', 'max:6000'],
        ]);

        // A managed installation may still choose its policies. The
        // connection and the switch are not on the screen there, and a
        // request that sends them anyway changes nothing.
        if (! $this->config->isManaged()) {
            $address = trim((string) ($validated['address'] ?? ''));

            // Refused rather than saved and quietly inert: switching this
            // on with nowhere to send files would leave every upload
            // waiting for a scanner that does not exist.
            if ($request->boolean('enabled') && $address === '') {
                return back()->withErrors(['address' => __('Enter the address of your scanner first.')]);
            }

            $this->settings->set(Setting::VirusScannerAddress, $address);
            $this->settings->set(Setting::VirusScanningEnabled, $request->boolean('enabled'));
        }

        $this->settings->set(Setting::VirusScanMaxSizeMb, (int) $validated['max_size_mb']);
        $this->settings->set(Setting::VirusUnscannablePolicy, $validated['unscannable_policy']);
        $this->settings->set(Setting::VirusScannerDownPolicy, $validated['scanner_down_policy']);
        $this->settings->set(Setting::VirusScannerWaitMinutes, (int) $validated['wait_minutes']);
        $this->settings->set(Setting::VirusScanExistingRatePerMinute, (int) $validated['existing_rate_per_minute']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'virus_scanning']);

        return back();
    }

    /**
     * Prove the scanner is there, and that it is actually detecting.
     *
     * Three steps, reported separately, because "cannot connect" and
     * "connects and finds nothing" are different problems and the second
     * is the one that looks fine from the outside. The third sends the
     * EICAR test string — a harmless sequence every engine recognises by
     * agreement — so the answer is "it detected something" rather than
     * "it did not complain".
     */
    public function test(VirusScanner $scanner): RedirectResponse
    {
        $status = $scanner->status();

        if (! $status->reachable) {
            return back()->with('scanner_test_result', [
                'ok' => false,
                'message' => $status->error ?? __('The scanner could not be reached.'),
            ]);
        }

        $stream = fopen('php://temp', 'r+');
        assert($stream !== false);
        fwrite($stream, $this->eicar());
        rewind($stream);

        $verdict = $scanner->scan($stream, strlen($this->eicar()));
        fclose($stream);

        if ($verdict->outcome === ScanOutcome::Infected) {
            return back()->with('scanner_test_result', [
                'ok' => true,
                'message' => __('Working. :engine detected the test file as ":threat".', [
                    'engine' => $status->engine ?? __('The scanner'),
                    'threat' => $verdict->detail ?? '',
                ]),
            ]);
        }

        // Reachable, and did not recognise a file every engine is supposed
        // to. Almost always empty or broken virus definitions, which is
        // exactly the failure nothing else would show.
        return back()->with('scanner_test_result', [
            'ok' => false,
            'message' => __(':engine answered but did not detect the standard test file. Check that its virus definitions are installed and up to date.', [
                'engine' => $status->engine ?? __('The scanner'),
            ]),
        ]);
    }

    /**
     * Queue every file that has never been scanned.
     *
     * The work itself is the hourly command's, so this button does not
     * hold a request open for a library of any size, and the pace is the
     * setting above rather than "as fast as the queue will go".
     */
    public function scanExisting(): RedirectResponse
    {
        abort_unless($this->config->enabled(), 422);

        \Illuminate\Support\Facades\Artisan::queue('projectsend:scan-files', ['--existing' => true]);

        return back()->with('success', __('Scanning existing files has started. It runs in the background.'));
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'pending' => File::query()->where('scan_status', ScanStatus::Pending)->count(),
            'quarantined' => File::query()->whereIn('scan_status', [
                ScanStatus::Infected->value,
                ScanStatus::UnscannableBlocked->value,
            ])->count(),
            'never_scanned' => File::query()
                ->where('scan_status', ScanStatus::NotScanned)
                ->where('scan_note', NotScannedReason::BeforeScanning->value)
                ->count(),
            'let_through' => File::query()
                ->where('scan_status', ScanStatus::NotScanned)
                ->whereIn('scan_note', [
                    NotScannedReason::ScannerUnavailable->value,
                    NotScannedReason::TooLarge->value,
                    NotScannedReason::Encrypted->value,
                ])
                ->count(),
        ];
    }

    private function eicar(): string
    {
        // Assembled rather than written out, so the repository itself
        // never contains the literal string: antivirus software on a
        // developer's machine quarantines files that do, and a checkout
        // that deletes its own test fixtures is a bad afternoon.
        return 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$'.'EICAR-STANDARD-'.'ANTIVIRUS-TEST-FILE!'.'$H+H*';
    }
}
