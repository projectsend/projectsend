import { formatCalendarDate, formatCalendarDateShort, formatDate, formatDateTime, type DateFormatOptions } from '@/lib/format-date';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Render a date the way this viewer reads dates.
 *
 * Locale and timezone both come off the shared props, so a component never
 * threads either by hand and can never accidentally omit one — which is how
 * a third of the app ended up formatting in the browser's language instead
 * of the one the user picked. Every date a component shows goes through
 * here, the same rule `useTranslation` has for strings.
 *
 * Pick by what the value *is*, not by how it should look:
 *
 *   date / dateTime  an instant (a `toIso8601String()` timestamp) — moved
 *                    into the viewer's zone
 *   calendarDate     a bare `YYYY-MM-DD` (an expiry, a chart's day key) —
 *                    left exactly where it is
 *
 * See `lib/format-date.ts` for why conflating the two is a bug and not a
 * detail.
 */
export function useFormatDate() {
    const { locale, timezone } = usePage<SharedData>().props;

    const options: DateFormatOptions = { locale, timeZone: timezone };

    return {
        date: (iso: string | null) => formatDate(iso, options),
        dateTime: (iso: string | null) => formatDateTime(iso, options),
        calendarDate: (date: string | null) => formatCalendarDate(date, options),
        /** A calendar date trimmed for a chart axis: "12 Aug". */
        calendarDateShort: (date: string | null) => formatCalendarDateShort(date, options),
    };
}
