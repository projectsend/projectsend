<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

/**
 * What a scanner answered about one file.
 *
 * Five outcomes rather than a boolean, because four of them are not
 * "clean or not": a file the scanner refused to open, one too big for it,
 * and a scanner that never answered are three different facts, and this
 * installation's settings decide what each one means for the file. That
 * decision lives in ScanPolicy, not here.
 */
final class ScanVerdict
{
    private function __construct(
        public readonly ScanOutcome $outcome,
        /** The threat name, the reason a scan was refused, or null. */
        public readonly ?string $detail = null,
        /** Engine and definitions, as the scanner reported them. */
        public readonly ?string $engine = null,
    ) {}

    public static function clean(?string $engine = null): self
    {
        return new self(ScanOutcome::Clean, null, $engine);
    }

    public static function infected(string $threat, ?string $engine = null): self
    {
        return new self(ScanOutcome::Infected, $threat, $engine);
    }

    public static function tooLarge(?string $engine = null): self
    {
        return new self(ScanOutcome::TooLarge, null, $engine);
    }

    public static function encrypted(?string $engine = null): self
    {
        return new self(ScanOutcome::Encrypted, null, $engine);
    }

    /** The file could not be read, so nothing was scanned. */
    public static function unreadable(string $reason): self
    {
        return new self(ScanOutcome::Unreadable, $reason);
    }

    /** The scanner could not be reached, or did not answer in time. */
    public static function unavailable(string $reason): self
    {
        return new self(ScanOutcome::Unavailable, $reason);
    }
}
