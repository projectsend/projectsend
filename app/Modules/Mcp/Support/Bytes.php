<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

/**
 * Bytes, the way a person reads them.
 *
 * The same rule FreePlanInvitation uses for the band on the shared
 * instance, kept in step by a test rather than by sharing the method:
 * that class is the band's and this is the connector's, and the one
 * thing they must agree on is what "1.7 GB" looks like when the
 * assistant quotes it back and the person then opens the web.
 *
 * Whole gigabytes without a decimal, fractions of one with a single
 * digit, megabytes below a gigabyte, kilobytes below a megabyte, and
 * bytes below that. "2 GB", "1.7 GB", "300 MB", "18 KB" — never "2.0 GB"
 * and never "1,000 MB".
 */
final class Bytes
{
    public static function human(int $bytes): string
    {
        $gigabyte = 1024 ** 3;
        $megabyte = 1024 ** 2;

        if ($bytes >= $gigabyte) {
            $gigabytes = round($bytes / $gigabyte, 1);

            return number_format($gigabytes, floor($gigabytes) === $gigabytes ? 0 : 1, '.', '').' GB';
        }

        if ($bytes >= $megabyte) {
            $megabytes = round($bytes / $megabyte, 1);

            return number_format($megabytes, floor($megabytes) === $megabytes ? 0 : 1, '.', '').' MB';
        }

        if ($bytes >= 1024) {
            return number_format(round($bytes / 1024), 0, '.', '').' KB';
        }

        return $bytes.' B';
    }

    /**
     * The share of a quota that is used, as the percent a person reads:
     * rounded, so 1.7 GB of 2 GB says 85 and not 84. The threshold that
     * decides a warning is not this: it compares byte counts in integer
     * arithmetic (Account::storageWarning), because a boundary is a fact
     * and not a rounding.
     */
    public static function percent(int $used, int $quota): int
    {
        if ($quota <= 0) {
            return 0;
        }

        return (int) round($used * 100 / $quota);
    }
}
