import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Derives a URL slug from a display name, for the live preview shown as
 * someone types a name. The server derives its own slug on save and
 * validates the submitted one against the same shape (App\Support\Rules),
 * so this only has to agree with that shape -- it is never the last word.
 */
export function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
