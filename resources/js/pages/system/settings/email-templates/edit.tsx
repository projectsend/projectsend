import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SavedIndicator } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface EmailTemplateEditProps {
    slot: string;
    label: string;
    subject: string;
    body: string;
    customized: boolean;
    placeholders: Record<string, string>;
}

export default function EmailTemplateEdit({ slot, label, subject, body, customized, placeholders }: EmailTemplateEditProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Email templates'), href: '/system/settings/email-templates' },
        { title: t(label), href: `/system/settings/email-templates/${slot}` },
    ];

    const { data, setData, patch, processing, recentlySuccessful, errors } = useForm({ subject, body });
    const bodyRef = useRef<HTMLTextAreaElement>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('email-templates.update', slot));
    };

    const resetToDefault = () => {
        router.delete(route('email-templates.destroy', slot), { preserveScroll: true });
    };

    const insertPlaceholder = (token: string) => {
        const textarea = bodyRef.current;
        const insertion = `:${token}`;

        if (!textarea) {
            setData('body', data.body + insertion);
            return;
        }

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const nextValue = data.body.slice(0, start) + insertion + data.body.slice(end);
        setData('body', nextValue);

        const cursor = start + insertion.length;
        requestAnimationFrame(() => {
            textarea.focus();
            textarea.setSelectionRange(cursor, cursor);
        });
    };

    const placeholderEntries = Object.entries(placeholders);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t(label)} />

            <div className="px-4 py-6">
                <Heading title={t(label)} description={t('Only the wording below is editable — any button or link in this email stays fixed.')} />

                <div className="mt-6 max-w-xl space-y-6">
                    <form id="email-template-form" onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="subject">{t('Subject')}</Label>
                            <Input id="subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} />
                            <InputError message={errors.subject} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="body">{t('Body')}</Label>
                            <Textarea id="body" ref={bodyRef} value={data.body} onChange={(e) => setData('body', e.target.value)} rows={8} />
                            <p className="text-muted-foreground text-sm">{t('Separate paragraphs with a blank line.')}</p>
                            <InputError message={errors.body} />
                        </div>
                    </form>

                    {placeholderEntries.length > 0 && (
                        <div className="space-y-2">
                            <HeadingSmall title={t('Available placeholders')} />
                            <div className="rounded-lg border">
                                {placeholderEntries.map(([token, description]) => (
                                    <div key={token} className="flex items-center justify-between gap-3 border-b px-4 py-2 text-sm last:border-0">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <code className="bg-muted shrink-0 rounded px-1.5 py-0.5 font-mono text-xs">:{token}</code>
                                            <span className="text-muted-foreground truncate">{t(description)}</span>
                                        </div>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => insertPlaceholder(token)}>
                                            {t('Insert')}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button type="submit" form="email-template-form" disabled={processing}>
                            {t('Save')}
                        </Button>

                        {customized && (
                            <ConfirmDialog
                                trigger={
                                    <Button type="button" variant="outline">
                                        {t('Reset to default')}
                                    </Button>
                                }
                                title={t('Reset this email to its default wording?')}
                                description={t(
                                    'Your customized subject and body will be discarded and replaced with the default wording immediately — this cannot be undone.',
                                )}
                                confirmLabel={t('Reset to default')}
                                onConfirm={resetToDefault}
                            />
                        )}

                        <SavedIndicator recentlySuccessful={recentlySuccessful} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
