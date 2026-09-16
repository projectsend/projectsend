<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * What this installation's scanning setup actually is, once the managed
 * configuration and the settings screen have both had their say.
 *
 * The rule is the one Captcha::resolve() already follows: an address
 * named in the environment wins, and where it wins the screen stops
 * offering the choice. That is how a hosted fleet points every site at one
 * scanning service without a per-site setting to get wrong, and it is
 * deliberately not an edition check — a self-hosted operator who prefers
 * to configure this in the environment gets the same behaviour. Edition
 * differences flow through the capability registry; this is not one.
 *
 * The two policies stay editable either way. What to do with a file that
 * cannot be scanned, and what to do while the scanner is down, are
 * decisions about somebody's own files.
 */
class ScanningConfig
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * Whether new uploads are scanned at all.
     *
     * Forced on under a managed configuration: a platform that supplies
     * the scanner is not offering the tenant a switch for it.
     */
    public function enabled(): bool
    {
        return $this->isManaged() || $this->settings->get(Setting::VirusScanningEnabled) === true;
    }

    public function isManaged(): bool
    {
        return $this->managedAddress() !== '';
    }

    public function address(): string
    {
        if ($this->isManaged()) {
            return $this->managedAddress();
        }

        $stored = $this->settings->get(Setting::VirusScannerAddress);

        return is_string($stored) ? trim($stored) : '';
    }

    /**
     * The largest file this installation sends to the scanner, in bytes.
     * Zero means no limit of our own — clamd's StreamMaxLength still
     * applies, and answers with tooLarge when it is reached.
     */
    public function maxScanBytes(): int
    {
        return max(0, (int) $this->settings->get(Setting::VirusScanMaxSizeMb)) * 1024 * 1024;
    }

    /** What happens to a file the scanner could not open. */
    public function blocksUnscannable(): bool
    {
        return $this->settings->get(Setting::VirusUnscannablePolicy) === 'block';
    }

    /** Whether uploads wait for a scanner that is not answering. */
    public function holdsWhileUnavailable(): bool
    {
        return $this->settings->get(Setting::VirusScannerDownPolicy) === 'hold';
    }

    /** How long a file waits for an unreachable scanner before the policy applies. */
    public function unavailableWaitMinutes(): int
    {
        return max(1, (int) $this->settings->get(Setting::VirusScannerWaitMinutes));
    }

    /** How many already-stored files an hour-long backfill may scan per minute. */
    public function existingScanRatePerMinute(): int
    {
        return max(1, (int) $this->settings->get(Setting::VirusScanExistingRatePerMinute));
    }

    public function connectTimeoutSeconds(): int
    {
        return max(1, (int) config('projectsend.scanning.connect_timeout', 5));
    }

    public function replyTimeoutSeconds(): int
    {
        return max(1, (int) config('projectsend.scanning.reply_timeout', 600));
    }

    private function managedAddress(): string
    {
        $address = config('projectsend.scanning.address', '');

        return is_string($address) ? trim($address) : '';
    }
}
