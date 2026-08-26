import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface DownloadSettingsProps {
    max_zip_download_size_mb: number;
}

export default function DownloadSettings({ max_zip_download_size_mb }: DownloadSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Downloads'), href: '/system/settings/downloads' },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        max_zip_download_size_mb: max_zip_download_size_mb,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.downloads.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Download settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Downloads')} description={t('Limits that apply when files leave your installation')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="max_zip_download_size_mb">{t('Maximum zip download size (MB)')}</Label>
                        <Input
                            id="max_zip_download_size_mb"
                            type="number"
                            min={0}
                            className="max-w-32"
                            value={data.max_zip_download_size_mb}
                            onChange={(e) => setData('max_zip_download_size_mb', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'The largest selection anyone can ask to download as a single zip, measured across every file it would contain. Building an archive takes disk space and holds the background worker while it runs, so this keeps one very large request from delaying everything else. 0 means no limit.',
                            )}
                        </p>
                        <InputError className="mt-2" message={errors.max_zip_download_size_mb} />
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}
