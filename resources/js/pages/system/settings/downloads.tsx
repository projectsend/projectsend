import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { FileDeliveryDialog, type FileDelivery } from '@/components/file-delivery-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface DownloadSettingsProps {
    max_zip_download_size_mb: number;
    file_delivery: FileDelivery;
}

export default function DownloadSettings({ max_zip_download_size_mb, file_delivery }: DownloadSettingsProps) {
    const { t } = useTranslation();
    const [deliveryOpen, setDeliveryOpen] = useState(false);

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

                {/* Not a setting, and here because this is where somebody
                    coming from v1 looks for one — v1 had a "Download
                    method" dropdown. Read-only on purpose: it describes
                    the server this installation is running on, and a value
                    kept in the database would travel to a different server
                    in a restore and be wrong there. */}
                <div className="mt-8 max-w-xl border-t pt-6">
                    <h2 className="text-sm font-medium">{t('How downloads are sent')}</h2>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {file_delivery.method === 'nginx' &&
                            t('Files are handed to nginx, which sends them without holding a PHP process open. This is the fastest option and needs nothing further.')}
                        {file_delivery.method === 'xsendfile' &&
                            t('Files are handed to your web server with the X-Sendfile header, which sends them without holding a PHP process open.')}
                        {file_delivery.method === 'php' &&
                            t('PHP is reading each file and sending it. That works on every server, but it occupies a PHP worker process for the whole of each download.')}
                    </p>
                    {file_delivery.method === 'php' && (
                        <button
                            type="button"
                            onClick={() => setDeliveryOpen(true)}
                            className="mt-2 flex items-center gap-1.5 text-sm font-medium text-amber-600 underline underline-offset-2 hover:no-underline dark:text-amber-500"
                        >
                            <AlertTriangle className="size-4 shrink-0" />
                            {t('Why this matters, and how to change it')}
                        </button>
                    )}
                    <p className="text-muted-foreground mt-3 text-xs">
                        {file_delivery.detected
                            ? t('Detected from the web server. Set PROJECTSEND_FILE_DELIVERY in your .env file to choose explicitly.')
                            : t('Set explicitly by PROJECTSEND_FILE_DELIVERY in your .env file.')}
                    </p>
                </div>

                <FileDeliveryDialog delivery={file_delivery} open={deliveryOpen} onOpenChange={setDeliveryOpen} />
            </div>
        </AppLayout>
    );
}
