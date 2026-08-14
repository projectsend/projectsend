import { intlLocale } from '@/lib/intl-locale';

/**
 * Naming a language for a human, from this application's locale keys.
 *
 * The tag has to be converted first — a catalogue key like `zh_CN` is not a
 * valid BCP 47 tag, and `Intl.DisplayNames` throws on it rather than
 * degrading, which without `intlLocale()` left Chinese listed as "zh_CN".
 * Unknown codes fall back to the code itself: locales are discovered from
 * disk, so a pack for a language `Intl` has never heard of is a legitimate
 * thing to find.
 */
function displayName(code: string, displayIn: string): string {
    const tag = intlLocale(code);

    if (tag === undefined) {
        return code;
    }

    try {
        const name = new Intl.DisplayNames([displayIn], { type: 'language' }).of(tag) ?? code;

        return name.charAt(0).toUpperCase() + name.slice(1);
    } catch {
        return code;
    }
}

/** The language's own name for itself (autonym): "Español", not "Spanish". */
export function languageName(code: string): string {
    return displayName(code, intlLocale(code) ?? 'en');
}

/**
 * The English name — a secondary label for the Languages settings screen,
 * where an administrator may well be picking languages they cannot read.
 */
export function englishLanguageName(code: string): string {
    return displayName(code, 'en');
}
