import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { englishLanguageName, languageName } from '@/lib/language-name';

interface LanguageSettingsProps {
    /** Every catalogue found in lang/, English included. */
    installed: string[];
    enabled: string[];
    default_locale: string;
}

/**
 * Everything after the language's own name: "· Catalan · ca" — the English
 * name for an administrator who cannot read the autonym, then the code that
 * names the catalogue file. The English part is dropped when it would only
 * repeat the autonym, leaving "English · en".
 */
function describe(code: string): string {
    const english = englishLanguageName(code);

    return languageName(code) === english ? `· ${code}` : `· ${english} · ${code}`;
}

export default function LanguageSettings({ installed, enabled, default_locale }: LanguageSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Languages'), href: '/system/settings/languages' },
    ];

    // Alphabetical by English name rather than by code: an administrator
    // scanning for "Portuguese" should not have to know it lives at pt_BR.
    const ordered = [...installed].sort((a, b) => englishLanguageName(a).localeCompare(englishLanguageName(b)));

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<{
        enabled_locales: string[];
        default_locale: string;
    }>({
        enabled_locales: enabled,
        default_locale: default_locale,
    });

    // Only what is currently ticked can be the default, so the two fields
    // cannot disagree by the time Save is pressed. Unticking the current
    // default hands the role back to English.
    const defaultOptions = ordered.filter((code) => code === 'en' || data.enabled_locales.includes(code));

    const toggle = (code: string, checked: boolean) => {
        const next = checked ? [...data.enabled_locales, code] : data.enabled_locales.filter((locale) => locale !== code);

        setData((current) => ({
            ...current,
            enabled_locales: next,
            default_locale: next.includes(current.default_locale) || current.default_locale === 'en' ? current.default_locale : 'en',
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.languages.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Language settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Languages')} description={t('Which languages people can choose from')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Every language installed on this server is listed below. Tick the ones you want offered in the language menu — staff, clients and public visitors will only see those. Installing another language is a matter of adding its translation file to the lang folder.',
                        )}
                    </p>

                    <div className="grid gap-3">
                        {ordered.map((code) => {
                            const always = code === 'en';

                            return (
                                <div key={code} className="flex items-start gap-2">
                                    <Checkbox
                                        id={`locale-${code}`}
                                        className="mt-0.5"
                                        checked={always || data.enabled_locales.includes(code)}
                                        disabled={always}
                                        onCheckedChange={(checked) => toggle(code, checked === true)}
                                    />
                                    <div className="grid gap-0.5">
                                        <Label htmlFor={`locale-${code}`} className="flex flex-wrap items-baseline gap-x-2 font-normal">
                                            <span>{languageName(code)}</span>
                                            <span className="text-muted-foreground">{describe(code)}</span>
                                        </Label>
                                        {always && (
                                            <p className="text-muted-foreground text-sm">
                                                {t('Always available — the application is written in English, so it needs no translation file.')}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <InputError message={errors.enabled_locales} />

                    <div className="grid gap-2 border-t pt-6">
                        <Label htmlFor="default_locale">{t('Default language')}</Label>

                        <Select value={data.default_locale} onValueChange={(value) => setData('default_locale', value)}>
                            <SelectTrigger id="default_locale" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {defaultOptions.map((code) => (
                                    <SelectItem key={code} value={code}>
                                        {languageName(code)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <p className="text-muted-foreground text-sm">
                            {t(
                                'What people see before they have chosen anything. A signed-in account keeps its own choice, and a visitor whose browser asks for one of the languages above gets that one — this is the answer for everyone else.',
                            )}
                        </p>

                        <InputError className="mt-2" message={errors.default_locale} />
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}
