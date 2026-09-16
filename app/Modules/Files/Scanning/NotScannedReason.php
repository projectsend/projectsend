<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

/**
 * Why a file carries ScanStatus::NotScanned — stored in `scan_note`.
 *
 * Four different things to say to a person, and two of them are the
 * installation's own doing rather than the file's, so a single "not
 * scanned" badge with no reason would be unactionable.
 */
enum NotScannedReason: string
{
    /** Bigger than the largest file this installation scans. */
    case TooLarge = 'too_large';

    /** An encrypted archive or document the scanner cannot open. */
    case Encrypted = 'encrypted';

    /** The scanner could not be reached in time, and the policy lets files through. */
    case ScannerUnavailable = 'scanner_unavailable';

    /** Uploaded before scanning was switched on, or while it is off. */
    case BeforeScanning = 'before_scanning';

    /**
     * The bytes were not there to read — an orphaned row, or storage that
     * has moved. Its own reason rather than "the scanner could not be
     * reached", which is what it used to say: that reading is both wrong
     * on screen and wrong in behaviour, because the hourly sweep retries
     * an unreachable scanner and would have retried these forever.
     */
    case Unreadable = 'unreadable';

    public function label(): string
    {
        return match ($this) {
            self::TooLarge => 'Too large to scan',
            self::Encrypted => 'Encrypted, so it could not be scanned',
            self::ScannerUnavailable => 'The scanner could not be reached',
            self::BeforeScanning => 'Uploaded before virus scanning was switched on',
            self::Unreadable => 'The file itself could not be read from storage',
        };
    }
}
