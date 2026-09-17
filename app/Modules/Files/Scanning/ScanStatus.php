<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

/**
 * Where a file stands with the virus scanner.
 *
 * Availability is not a case here on purpose: three of these mean the
 * file may be served and three mean it may not, and asking
 * FileAvailability rather than comparing cases is what keeps that rule in
 * one place. See docs/feature-virus-scanning.md.
 */
enum ScanStatus: string
{
    /** Waiting to be scanned, or being scanned right now. */
    case Pending = 'pending';

    /** Scanned, nothing found. */
    case Clean = 'clean';

    /** A threat was found. Quarantined; `scan_note` is the threat name. */
    case Infected = 'infected';

    /** Was infected, and an administrator decided to allow it anyway. */
    case Released = 'released';

    /** Not checked, and allowed through. `scan_note` is a NotScannedReason. */
    case NotScanned = 'not_scanned';

    /** Could not be checked, and this installation blocks those. Quarantined. */
    case UnscannableBlocked = 'unscannable_blocked';

    /**
     * The row is here and the bytes are not.
     *
     * Its own state rather than a kind of "not scanned", because what it
     * means for the file is different: nothing can be served, so nothing
     * is offered. A client listing it and getting an error on the
     * download is worse than not seeing it, and staff need to see it
     * precisely because somebody has to decide what to do about it.
     */
    case Missing = 'missing';

    /**
     * Whether a file in this state may be seen and downloaded by people
     * other than staff and its uploader.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Clean, self::Released, self::NotScanned => true,
            self::Pending, self::Infected, self::UnscannableBlocked, self::Missing => false,
        };
    }

    /**
     * The states a query may hand to somebody other than staff.
     *
     * @return list<string>
     */
    public static function availableValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->isAvailable()),
        ));
    }

    /** Whether this state is waiting on an administrator's decision. */
    public function isQuarantined(): bool
    {
        return $this === self::Infected || $this === self::UnscannableBlocked;
    }

    /**
     * English, and the translation key — what staff see on the file.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Checking for viruses',
            self::Clean => 'Checked',
            self::Infected => 'Quarantined',
            self::Released => 'Released by an administrator',
            self::NotScanned => 'Not scanned',
            self::UnscannableBlocked => 'Blocked: could not be scanned',
            self::Missing => 'Missing from storage',
        };
    }
}
