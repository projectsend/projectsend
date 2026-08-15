import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Image as ImageIcon } from 'lucide-react';
import { useState } from 'react';

import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useIsDark } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ThemeOption {
    key: string;
    label: string;
    description: string;
    preview_url: string | null;
    preview_url_dark: string | null;
}

interface ThemingSettingsProps {
    theme: string;
    email_theme: string;
    themes: ThemeOption[];
    email_themes: ThemeOption[];
}

type Tab = 'pages' | 'email';

function ThemeCard({
    option,
    active,
    onActivate,
    activating,
    onPreview,
}: {
    option: ThemeOption;
    active: boolean;
    onActivate: () => void;
    activating: boolean;
    onPreview?: () => void;
}) {
    const { t } = useTranslation();
    const isDark = useIsDark();
    // Falls back to the light capture whenever a dark one hasn't been taken
    // for this key yet (e.g. email themes, whose rendered HTML doesn't
    // change with the app's own appearance — see theme-screenshots SKILL.md)
    // rather than showing a mismatched screenshot or a placeholder.
    const previewUrl = (isDark && option.preview_url_dark) || option.preview_url;

    return (
        <div className={`overflow-hidden rounded-lg border ${active ? 'border-primary bg-accent' : ''}`}>
            <div className="bg-muted flex aspect-video items-center justify-center overflow-hidden">
                {previewUrl ? (
                    <img src={previewUrl} alt={option.label} className="h-full w-full object-cover object-top" />
                ) : (
                    <ImageIcon className="text-muted-foreground size-8" strokeWidth={1.5} />
                )}
            </div>
            <div className="p-3">
                <p className="truncate text-sm font-medium">{option.label}</p>
                <p className="text-muted-foreground mt-1 text-xs">{option.description}</p>
                <div className="mt-3 flex items-center justify-end gap-1">
                    {onPreview && (
                        <Button size="sm" variant="ghost" onClick={onPreview}>
                            <Eye className="size-4" />
                            <span className="sr-only">{t('Preview')}</span>
                        </Button>
                    )}
                    {active ? (
                        <Badge variant="success">{t('Active')}</Badge>
                    ) : (
                        <Button size="sm" variant="outline" onClick={onActivate} disabled={activating}>
                            {t('Activate')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function ThemingSettings({ theme, email_theme, themes, email_themes }: ThemingSettingsProps) {
    const { t } = useTranslation();
    // ?tab=email opens on the email themes, so something elsewhere can link
    // at one of these two halves rather than at the page and a sentence
    // asking the reader to find the right tab. Anything unrecognised falls
    // back to the pages tab rather than rendering nothing.
    const [tab, setTab] = useState<Tab>(new URLSearchParams(window.location.search).get('tab') === 'email' ? 'email' : 'pages');
    const [activatingKey, setActivatingKey] = useState<string | null>(null);
    const [previewing, setPreviewing] = useState<ThemeOption | null>(null);

    const activate = (payload: { theme: string; email_theme: string }, key: string) => {
        setActivatingKey(key);
        router.patch(route('system-settings.theming.update'), payload, {
            preserveScroll: true,
            onFinish: () => setActivatingKey(null),
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Theming'), href: '/system/settings/theming' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Theming settings')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Theming settings')}
                    description={t('The look of the guest public pages, the client portal, and outgoing notification emails.')}
                />

                <nav className="flex gap-1 border-b">
                    {(['pages', 'email'] as Tab[]).map((tabKey) => (
                        <button
                            type="button"
                            key={tabKey}
                            onClick={() => setTab(tabKey)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {tabKey === 'pages' ? t('Public pages & portal') : t('Outgoing email')}
                        </button>
                    ))}
                </nav>

                <div className="mt-6">
                    {tab === 'pages' ? (
                        <div>
                            <p className="text-muted-foreground mb-4 text-sm">
                                {t('Applies to the guest-facing public group/file pages and the "My files" client portal.')}
                            </p>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {themes.map((option) => (
                                    <ThemeCard
                                        key={option.key}
                                        option={option}
                                        active={option.key === theme}
                                        activating={activatingKey === option.key}
                                        onActivate={() => activate({ theme: option.key, email_theme }, option.key)}
                                    />
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div>
                            <p className="text-muted-foreground mb-4 text-sm">
                                {t('The header/footer style used for every outgoing notification email.')}
                            </p>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {email_themes.map((option) => (
                                    <ThemeCard
                                        key={option.key}
                                        option={option}
                                        active={option.key === email_theme}
                                        activating={activatingKey === option.key}
                                        onActivate={() => activate({ theme, email_theme: option.key }, option.key)}
                                        onPreview={() => setPreviewing(option)}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <Dialog open={previewing !== null} onOpenChange={(open) => !open && setPreviewing(null)}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{previewing ? t('Preview: :theme', { theme: previewing.label }) : ''}</DialogTitle>
                    </DialogHeader>
                    {previewing && (
                        <iframe
                            src={route('system-settings.theming.email-preview', previewing.key)}
                            title={previewing.label}
                            className="h-[70vh] w-full rounded border bg-white"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
