<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

use Illuminate\Support\Carbon;

/**
 * What the scanner said about itself — for the Test button, the dashboard
 * warning and `projectsend:status`.
 */
final class ScannerStatus
{
    public function __construct(
        public readonly bool $reachable,
        /** e.g. "ClamAV 1.4.1", or null when unreachable. */
        public readonly ?string $engine = null,
        /** The signature database number, when the scanner reports one. */
        public readonly ?int $definitionsVersion = null,
        public readonly ?Carbon $definitionsDate = null,
        /** Why it could not be reached, for a person to act on. */
        public readonly ?string $error = null,
    ) {}

    public static function unreachable(string $error): self
    {
        return new self(false, error: $error);
    }

    /**
     * How old the definitions are, in hours. Null when the scanner does
     * not say — absent and zero are different answers, and a caller
     * warning on "older than three days" must not treat "did not say" as
     * "brand new".
     */
    public function definitionsAgeHours(): ?int
    {
        if ($this->definitionsDate === null) {
            return null;
        }

        // diffInHours() answers with a float; whole hours is what the
        // warning threshold and the status document both speak in.
        return (int) $this->definitionsDate->diffInHours(now());
    }
}
