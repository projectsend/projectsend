<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

/**
 * The seam between this application and whatever actually reads the
 * bytes.
 *
 * One implementation ships (ClamAvScanner) and one more lives in the test
 * suite. It exists as an interface because a commercial engine is a
 * plausible later addition and because every test that is *about* policy
 * — what happens to a file the scanner could not open — should be able to
 * state the verdict rather than produce a file that provokes it.
 */
interface VirusScanner
{
    /**
     * Read a file and say what it is.
     *
     * Implementations never throw for a scanner that is down or slow:
     * that is ScanVerdict::unavailable(), because the caller has a policy
     * for it and an exception would look like a bug in the job.
     *
     * @param  resource  $stream  the file's bytes, at position 0
     * @param  int  $size  the file's size in bytes
     */
    public function scan(mixed $stream, int $size): ScanVerdict;

    /**
     * Whether the scanner answers, and what it is running.
     */
    public function status(): ScannerStatus;
}
