import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SavedIndicator } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface StorageSettingsProps {
    active: boolean;
    access_key: string;
    has_secret: boolean;
    bucket: string;
    region: string;
    endpoint: string;
    use_path_style: boolean;
    root: string;
    test_result: string | null;
}

const FORM_ID = 'storage-settings-form';

export default function StorageSettings({
    active,
    access_key,
    has_secret,
    bucket,
    region,
    endpoint,
    use_path_style,
    root,
    test_result,
}: StorageSettingsProps) {
    const { t } = useTranslation();
    const [testing, setTesting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Storage'), href: '/system/settings/storage' },
    ];

    const { data, setData, patch, processing, recentlySuccessful, errors } = useForm({
        active: active,
        access_key: access_key,
        secret: '',
        bucket: bucket,
        region: region,
        endpoint: endpoint,
        use_path_style: use_path_style,
        root: root,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('system-settings.storage.update'), {
            preserveScroll: true,
            onSuccess: () => setData('secret', ''),
        });
    };

    const testConnection = () => {
        setTesting(true);
        router.post(
            route('system-settings.storage.test'),
            {
                access_key: data.access_key,
                secret: data.secret,
                bucket: data.bucket,
                region: data.region,
                endpoint: data.endpoint,
                use_path_style: data.use_path_style,
            },
            { preserveScroll: true, onFinish: () => setTesting(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Storage settings')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Storage settings')}
                    description={t(
                        'Connect an external S3-compatible bucket (AWS S3, MinIO, or another S3-compatible service) as the storage backend for new uploads.',
                    )}
                />

                <div className="bg-muted/50 mt-6 max-w-xl rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Only uploads made after this backend is activated are affected. Files already stored locally stay there and keep working — there is no migration between backends.',
                        )}
                    </p>
                </div>

                <form id={FORM_ID} onSubmit={submit} className="mt-6 max-w-xl space-y-6">
                    <div className="flex items-start gap-2">
                        <Checkbox id="active" checked={data.active} onCheckedChange={(checked) => setData('active', checked === true)} />
                        <div className="grid gap-1">
                            <Label htmlFor="active" className="font-normal">
                                {t('Use external storage for new uploads')}
                            </Label>
                        </div>
                    </div>

                    <div className="flex gap-4">
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="storage_access_key">{t('Access key')}</Label>
                            <Input id="storage_access_key" value={data.access_key} onChange={(e) => setData('access_key', e.target.value)} />
                            <InputError message={errors.access_key} />
                        </div>
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="storage_secret">{t('Secret key')}</Label>
                            <Input
                                id="storage_secret"
                                type="password"
                                placeholder={has_secret ? t('Unchanged') : ''}
                                value={data.secret}
                                onChange={(e) => setData('secret', e.target.value)}
                            />
                            <InputError message={errors.secret} />
                        </div>
                    </div>

                    <div className="flex gap-4">
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="storage_bucket">{t('Bucket')}</Label>
                            <Input id="storage_bucket" value={data.bucket} onChange={(e) => setData('bucket', e.target.value)} />
                            <InputError message={errors.bucket} />
                        </div>
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="storage_region">{t('Region')}</Label>
                            <Input id="storage_region" value={data.region} onChange={(e) => setData('region', e.target.value)} />
                            <InputError message={errors.region} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="storage_endpoint">{t('Custom endpoint')}</Label>
                        <p className="text-muted-foreground text-sm">
                            {t('Leave blank for AWS S3. Set this to use an S3-compatible service such as MinIO or Backblaze.')}
                        </p>
                        <Input
                            id="storage_endpoint"
                            value={data.endpoint}
                            onChange={(e) => setData('endpoint', e.target.value)}
                            placeholder="https://s3.example.com"
                        />
                        <InputError message={errors.endpoint} />
                    </div>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="use_path_style"
                            checked={data.use_path_style}
                            onCheckedChange={(checked) => setData('use_path_style', checked === true)}
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="use_path_style" className="font-normal">
                                {t('Use path-style addressing')}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {t('Required by most S3-compatible services (MinIO, etc). Leave off for AWS S3.')}
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="storage_root">{t('Path prefix')}</Label>
                        <p className="text-muted-foreground text-sm">{t('Optional. Confines uploads to a subfolder within the bucket.')}</p>
                        <Input id="storage_root" value={data.root} onChange={(e) => setData('root', e.target.value)} />
                        <InputError message={errors.root} />
                    </div>
                </form>

                <div className="mt-6 max-w-xl space-y-4">
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="outline" onClick={testConnection} disabled={testing || data.bucket.trim() === ''}>
                            {t('Test connection')}
                        </Button>
                    </div>

                    {test_result && (
                        <div className="grid gap-2">
                            <Label htmlFor="test_result">{t('Test result')}</Label>
                            <Textarea id="test_result" value={test_result} readOnly rows={3} className="bg-muted text-muted-foreground" />
                        </div>
                    )}
                </div>

                <div className="mt-6 flex max-w-xl items-center gap-4">
                    <Button type="submit" form={FORM_ID} disabled={processing}>
                        {t('Save')}
                    </Button>

                    <SavedIndicator recentlySuccessful={recentlySuccessful} />
                </div>
            </div>
        </AppLayout>
    );
}
