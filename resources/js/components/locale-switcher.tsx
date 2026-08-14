import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { languageName } from '@/lib/language-name';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Check, Languages, Settings2 } from 'lucide-react';

function useLocaleSwitcher() {
    const { locale, locales } = usePage<SharedData>().props;

    const change = (code: string) => {
        if (code !== locale) {
            // Drop any prefetched pages: their props carry the old locale.
            router.flushAll();
            router.put(route('locale.update'), { locale: code }, { preserveScroll: true });
        }
    };

    return { locale, locales, change };
}

/**
 * The way to the Languages screen, for the one person who can do anything
 * about a language missing from the list above. The server already returns 0
 * for everyone else (clients, public visitors, staff without edit_settings),
 * so the count alone is the whole gate — see HandleInertiaRequests.
 */
function ManageLanguagesLink() {
    const { t } = useTranslation();
    const { locales_disabled: disabled } = usePage<SharedData>().props;

    if (disabled < 1) {
        return null;
    }

    return (
        <>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link href={route('system-settings.languages.edit')} className="gap-2">
                    <Settings2 className="size-4" />
                    <span>
                        {/* t() substitutes placeholders but has no plural rules, so the two forms are picked here. */}
                        {disabled === 1 ? t('1 more language available') : t(':count more languages available', { count: disabled })}
                    </span>
                </Link>
            </DropdownMenuItem>
        </>
    );
}

function LocaleMenuContent() {
    const { locale, locales, change } = useLocaleSwitcher();

    return (
        <DropdownMenuContent align="end">
            {locales.map((code) => (
                <DropdownMenuItem key={code} onClick={() => change(code)}>
                    {languageName(code)}
                    {code === locale && <Check className="ml-auto size-4" />}
                </DropdownMenuItem>
            ))}
            <ManageLanguagesLink />
        </DropdownMenuContent>
    );
}

/** Compact switcher for auth screens and other plain surfaces. */
export function LocaleSwitcher() {
    const { locale } = useLocaleSwitcher();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors">
                <Languages className="size-4" />
                {languageName(locale)}
            </DropdownMenuTrigger>
            <LocaleMenuContent />
        </DropdownMenu>
    );
}

/** Icon-only trigger for header bars — the dropdown content carries the language names. */
export function IconLocaleSwitcher() {
    const { t } = useTranslation();
    const { locale } = useLocaleSwitcher();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label={t('Language: :language', { language: languageName(locale) })}>
                    <Languages className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <LocaleMenuContent />
        </DropdownMenu>
    );
}
