/**
 * Turns this application's locale key into a language tag `Intl` will accept.
 *
 * The two are not the same thing, and the difference is not cosmetic. A
 * locale here is a catalogue file name — `lang/pt_BR.json`, `lang/zh_CN.json`
 * — so the key carries an underscore. Every `Intl` API (and every
 * `toLocaleString`/`toLocaleDateString`, which are `Intl` underneath) requires
 * a BCP 47 tag, where the separator is a hyphen. Handed `zh_CN` they do not
 * fall back or warn: they throw `RangeError: Invalid language tag`.
 *
 * Thrown from a React render, that is fatal — the tree unmounts and the user
 * gets a blank page, with nothing in the server log because nothing reached
 * the server. Selecting Chinese did exactly that: the dashboard formats dates
 * in four of its widgets, so the first page after signing in died on the
 * first one.
 *
 * `resources/views/app.blade.php` already does the same conversion for the
 * `<html lang>` attribute; this is the client-side half of that rule.
 *
 * Returns `undefined` — never a throw — for anything unusable, which makes
 * `toLocaleDateString(intlLocale(locale), …)` fall back to the browser's own
 * locale. A date in the wrong format is a much smaller problem than a screen
 * that will not render.
 */
export function intlLocale(locale: string | null | undefined): string | undefined {
    if (!locale) {
        return undefined;
    }

    const tag = locale.replace(/_/g, '-');

    try {
        // The cheapest way to ask "would Intl accept this?" without
        // constructing a formatter.
        Intl.getCanonicalLocales(tag);

        return tag;
    } catch {
        return undefined;
    }
}
