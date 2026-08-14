import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface FileRetentionSettingsProps {
    expired_files_auto_delete_enabled: boolean;
    expired_files_delete_after_days: number;
    orphan_files_auto_delete_enabled: boolean;
    orphan_files_delete_after_days: number;
    external_storage_active: boolean;
}

export default function FileRetentionSettings({
    expired_files_auto_delete_enabled,
    expired_files_delete_after_days,
    orphan_files_auto_delete_enabled,
    orphan_files_delete_after_days,
    external_storage_active,
}: FileRetentionSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('File retention'), href: '/system/settings/file-retention' },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        expired_files_auto_delete_enabled: expired_files_auto_delete_enabled,
        expired_files_delete_after_days: expired_files_delete_after_days,
        orphan_files_auto_delete_enabled: orphan_files_auto_delete_enabled,
        orphan_files_delete_after_days: orphan_files_delete_after_days,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.file-retention.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('File retention settings')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('File retention')}
                    description={t('What happens to a file once it expires, or once one turns up unclaimed on disk')}
                />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="expired_files_auto_delete_enabled"
                                checked={data.expired_files_auto_delete_enabled}
                                onCheckedChange={(checked) => setData('expired_files_auto_delete_enabled', checked === true)}
                            />
                            <Label htmlFor="expired_files_auto_delete_enabled" className="font-normal">
                                {t('Automatically delete expired files')}
                            </Label>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {t('A daily task permanently deletes files past their expiration date and grace period below.')}
                        </p>
                        <InputError className="mt-2" message={errors.expired_files_auto_delete_enabled} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="expired_files_delete_after_days">{t('Delete this many days after expiry')}</Label>
                        <Input
                            id="expired_files_delete_after_days"
                            type="number"
                            min={0}
                            className="max-w-32"
                            value={data.expired_files_delete_after_days}
                            onChange={(e) => setData('expired_files_delete_after_days', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t('How long an expired file is kept before it is permanently deleted. 0 deletes it the same day it expires.')}
                        </p>
                        <InputError className="mt-2" message={errors.expired_files_delete_after_days} />
                    </div>

                    <div className="border-t pt-6">
                        <HeadingSmall
                            title={t('Orphan files')}
                            description={t(
                                'Files sitting on disk with no matching record — an interrupted upload, a restore, manual filesystem access',
                            )}
                        />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="orphan_files_auto_delete_enabled"
                                checked={data.orphan_files_auto_delete_enabled}
                                disabled={external_storage_active}
                                onCheckedChange={(checked) => setData('orphan_files_auto_delete_enabled', checked === true)}
                            />
                            <Label htmlFor="orphan_files_auto_delete_enabled" className="font-normal">
                                {t('Automatically delete orphan files')}
                            </Label>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {t('A daily task permanently deletes local orphan files older than the grace period below.')}
                        </p>
                        <InputError className="mt-2" message={errors.orphan_files_auto_delete_enabled} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="orphan_files_delete_after_days">{t('Delete this many days after being found')}</Label>
                        <Input
                            id="orphan_files_delete_after_days"
                            type="number"
                            min={0}
                            className="max-w-32"
                            disabled={external_storage_active}
                            value={data.orphan_files_delete_after_days}
                            onChange={(e) => setData('orphan_files_delete_after_days', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t('How long an orphan file is kept before it is permanently deleted. 0 deletes it the same day it is first found.')}
                        </p>
                        <InputError className="mt-2" message={errors.orphan_files_delete_after_days} />
                    </div>

                    {external_storage_active ? (
                        <Alert>
                            <TriangleAlert className="size-4" />
                            <AlertTitle>{t('Unavailable while external storage is active')}</AlertTitle>
                            <AlertDescription>
                                {t(
                                    'Orphan auto-delete only ever operates on local storage, so it stays off while external storage is active — even if it was previously enabled.',
                                )}
                            </AlertDescription>
                        </Alert>
                    ) : (
                        data.orphan_files_auto_delete_enabled && (
                            <Alert variant="warning">
                                <TriangleAlert className="size-4" />
                                <AlertTitle>{t('This deletion is permanent')}</AlertTitle>
                                <AlertDescription>
                                    {t("Unlike an expired file, an orphan has no database row to soft-delete — there is no undo once it's gone.")}
                                </AlertDescription>
                            </Alert>
                        )
                    )}

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}
