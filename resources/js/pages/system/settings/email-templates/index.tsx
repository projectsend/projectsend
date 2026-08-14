import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface TemplateRow {
    slot: string;
    label: string;
    customized: boolean;
}

interface EmailTemplatesIndexProps {
    templates: TemplateRow[];
}

export default function EmailTemplatesIndex({ templates }: EmailTemplatesIndexProps) {
    const { t } = useTranslation();
    const [previewing, setPreviewing] = useState<TemplateRow | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Email templates'), href: '/system/settings/email-templates' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Email templates')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Email templates')}
                    description={t('Customize the wording of any transactional email sent by this installation.')}
                />

                <div className="mt-6 overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b text-left">
                                <th className="px-4 py-2.5 font-medium">{t('Email')}</th>
                                <th className="px-4 py-2.5 font-medium">{t('Status')}</th>
                                <th className="px-4 py-2.5" />
                            </tr>
                        </thead>
                        <tbody>
                            {templates.map((template) => (
                                <tr key={template.slot} className="border-b last:border-0">
                                    <td className="px-4 py-2.5 font-medium">{t(template.label)}</td>
                                    <td className="px-4 py-2.5">
                                        {template.customized ? (
                                            <Badge variant="secondary">{t('Customized')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Default')}</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="sm" onClick={() => setPreviewing(template)}>
                                                {t('Preview')}
                                            </Button>
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('email-templates.edit', template.slot)}>{t('Edit')}</Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={previewing !== null} onOpenChange={(open) => !open && setPreviewing(null)}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{previewing ? t('Preview: :theme', { theme: t(previewing.label) }) : ''}</DialogTitle>
                    </DialogHeader>
                    {previewing && (
                        <iframe
                            src={route('email-templates.preview', previewing.slot)}
                            title={t(previewing.label)}
                            className="h-[70vh] w-full rounded border bg-white"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
