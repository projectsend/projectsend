import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Translate an app string. Keys are the English text (Laravel JSON
 * translations); an untranslated key falls back to itself, so English
 * needs no message catalog.
 *
 * Every user-facing string in a component must go through `t()` — no
 * hardcoded copy.
 */
export function useTranslation() {
    const { translations } = usePage<SharedData>().props;

    const t = (key: string, replacements: Record<string, string | number> = {}): string => {
        let message = translations[key] ?? key;

        for (const [name, value] of Object.entries(replacements)) {
            message = message.replaceAll(`:${name}`, String(value));
        }

        return message;
    };

    return { t };
}
