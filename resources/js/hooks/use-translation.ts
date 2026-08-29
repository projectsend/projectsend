import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Translate an app string. Keys are the English text (Laravel JSON
 * translations); an untranslated key falls back to itself, so English
 * needs no message catalog.
 *
 * Every user-facing string in a component must go through `t()` — no
 * hardcoded copy.
 *
 * Replacements follow Laravel's own placeholder convention, because the
 * catalogs already do: a `:name` in the key licenses `:name`, `:Name`
 * and `:NAME` in a locale's value, receiving the replacement as-is,
 * capitalized, or upper-cased. That case shift is how a language that
 * orders the sentence differently keeps its typography — Dutch writes
 * "Add :name" as ":Name toevoegen", the noun capitalized because it now
 * opens the phrase. The backend translator has always honoured all
 * three forms; an exact-match replace here would hand those locales the
 * literal ":Name" instead of the value.
 */
export function useTranslation() {
    const { translations } = usePage<SharedData>().props;

    const t = (key: string, replacements: Record<string, string | number> = {}): string => {
        let message = translations[key] ?? key;

        const variants: [string, string][] = [];
        for (const [name, value] of Object.entries(replacements)) {
            const text = String(value);
            variants.push(
                [`:${name}`, text],
                [`:${name.charAt(0).toUpperCase()}${name.slice(1)}`, `${text.charAt(0).toUpperCase()}${text.slice(1)}`],
                [`:${name.toUpperCase()}`, text.toUpperCase()],
            );
        }

        // Longest placeholder first — strtr's implicit rule, made
        // explicit: with both :name and :names in play, :name must not
        // eat the front half of :names. Ties keep insertion order, so a
        // key whose case variants collide resolves to the as-is value,
        // as the backend's array assignment order does.
        variants.sort((a, b) => b[0].length - a[0].length);

        for (const [placeholder, replacement] of variants) {
            message = message.replaceAll(placeholder, replacement);
        }

        return message;
    };

    return { t };
}
