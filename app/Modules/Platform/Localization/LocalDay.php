<?php

declare(strict_types=1);

namespace App\Modules\Platform\Localization;

use Carbon\Carbon;
use Throwable;

/**
 * A calendar day in somebody's timezone, expressed as the UTC instants it
 * actually spans.
 *
 * Every date filter in the application is a `<input type="date">` posting a
 * bare `YYYY-MM-DD`, and every timestamp column is UTC. Comparing the two
 * directly — which is what `whereDate()` does — silently asks the database
 * "which UTC day was this?", so a viewer in Buenos Aires filtering to
 * "10 August" misses everything they did after 21:00 that evening and picks
 * up three hours of the 9th instead. The fix is not a different comparison,
 * it is converting the boundary: their 10 August began at 03:00Z and ended
 * at 02:59:59Z the next morning.
 *
 * Both methods are total. A malformed or impossible date reaches here only
 * from a hand-edited query string (the forms post a date picker's output,
 * and `'date'` validation runs first), and a filter that throws would take
 * down a page that has a perfectly good answer without it — so an
 * unparseable value yields null and the caller simply does not filter.
 */
final class LocalDay
{
    /**
     * The first instant of `$date` in `$timezone`, in UTC.
     */
    public static function start(string $date, string $timezone): ?Carbon
    {
        return self::parse($date, $timezone)?->startOfDay()->utc();
    }

    /**
     * The last instant of `$date` in `$timezone`, in UTC.
     *
     * Inclusive on purpose: "to 10 August" means through the end of the
     * 10th, which is how the person typing it reads it.
     */
    public static function end(string $date, string $timezone): ?Carbon
    {
        return self::parse($date, $timezone)?->endOfDay()->utc();
    }

    private static function parse(string $date, string $timezone): ?Carbon
    {
        try {
            return Carbon::parse($date, $timezone);
        } catch (Throwable) {
            return null;
        }
    }
}
