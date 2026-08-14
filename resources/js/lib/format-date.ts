import { intlLocale } from '@/lib/intl-locale';

/**
 * Dates arrive from the server as ISO strings and are rendered in the
 * viewer's own locale and timezone. Both have to be passed in explicitly:
 * these stay pure so they can be unit-tested and called outside a React
 * tree. Inside components use `useFormatDate()`, which reads both off the
 * shared props once and hands back the same three functions already bound.
 *
 * The locale goes through intlLocale() first: the app's keys are catalogue
 * file names (pt_BR), and Intl only accepts BCP 47 tags (pt-BR). It throws on
 * the difference rather than degrading, which takes the whole page with it.
 *
 * The timezone is passed straight through. Unlike the locale it needs no
 * translation — it is the IANA identifier the server resolved and validated
 * (TimezoneRegistry), and `undefined` correctly means "the browser's own",
 * which is the right answer before the props have arrived.
 *
 * **Instants and calendar dates are not the same thing**, which is why
 * there are three functions and not two. A timestamp is a point in time and
 * belongs in the reader's zone. A date like a file's expiry is a calendar
 * day — the 12th is the 12th everywhere — and shifting it by an offset is
 * always wrong: it used to render "11 Aug" for everyone west of Greenwich.
 * Match the function to which kind of value you have, not to how much of it
 * you want on screen.
 */
export interface DateFormatOptions {
    locale: string;
    /** An IANA identifier. Undefined falls back to the browser's own zone. */
    timeZone?: string;
}

/** An instant, shown as a date: "12 Aug 2026". Tolerates null so optional columns can call it directly. */
export function formatDate(iso: string | null, { locale, timeZone }: DateFormatOptions): string {
    return iso === null ? '' : new Date(iso).toLocaleDateString(intlLocale(locale), { dateStyle: 'medium', timeZone });
}

/** An instant with its time: "12 Aug 2026, 14:30" -- for logs, where ordering within a day matters. */
export function formatDateTime(iso: string | null, { locale, timeZone }: DateFormatOptions): string {
    return iso === null ? '' : new Date(iso).toLocaleString(intlLocale(locale), { dateStyle: 'medium', timeStyle: 'short', timeZone });
}

/**
 * A bare `YYYY-MM-DD` from the server, shown as itself: "12 Aug 2026".
 *
 * Deliberately ignores the viewer's timezone. `new Date('2026-08-12')` is
 * parsed as UTC midnight, so formatting it in any negative offset moves it
 * to the 11th; pinning the formatter to UTC as well cancels that out and
 * the date survives the round trip. This is what the scattered
 * `new Date(iso + 'T00:00:00')` workarounds were reaching for.
 */
export function formatCalendarDate(date: string | null, { locale }: DateFormatOptions): string {
    return date === null ? '' : new Date(`${date}T00:00:00Z`).toLocaleDateString(intlLocale(locale), { dateStyle: 'medium', timeZone: 'UTC' });
}

/** A calendar date trimmed for a chart axis: "12 Aug". */
export function formatCalendarDateShort(date: string | null, { locale }: DateFormatOptions): string {
    return date === null
        ? ''
        : new Date(`${date}T00:00:00Z`).toLocaleDateString(intlLocale(locale), {
              month: 'short',
              day: 'numeric',
              timeZone: 'UTC',
          });
}
