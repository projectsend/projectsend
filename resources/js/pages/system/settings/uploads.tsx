import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface UploadSettingsProps {
    max_file_size_mb: number;
    upload_type_restriction: string;
    allowed_upload_extensions: string[];
}

export default function UploadSettings({ max_file_size_mb, upload_type_restriction, allowed_upload_extensions }: UploadSettingsProps) {
    const { t } = useTranslation();
    const [newExtension, setNewExtension] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Uploads'), href: '/system/settings/uploads' },
    ];

    const restrictionOptions: { value: string; label: string }[] = [
        { value: 'none', label: t('Nobody (no restriction)') },
        { value: 'clients', label: t('Clients only') },
        { value: 'all', label: t('Everyone') },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        max_file_size_mb: String(max_file_size_mb),
        upload_type_restriction: upload_type_restriction,
        allowed_upload_extensions: allowed_upload_extensions,
    });

    const addExtension = () => {
        const extension = newExtension.trim().toLowerCase().replace(/^\./, '');
        if (extension === '' || data.allowed_upload_extensions.includes(extension)) return;
        setData('allowed_upload_extensions', [...data.allowed_upload_extensions, extension]);
        setNewExtension('');
    };

    const removeExtension = (extension: string) => {
        setData(
            'allowed_upload_extensions',
            data.allowed_upload_extensions.filter((existing) => existing !== extension),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('system-settings.uploads.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Upload settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Upload settings')} description={t('Limits for files uploaded to this installation')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="max_file_size_mb">{t('Maximum file size (MB)')}</Label>
                        <Input
                            id="max_file_size_mb"
                            type="number"
                            min={0}
                            className="w-40"
                            value={data.max_file_size_mb}
                            onChange={(e) => setData('max_file_size_mb', e.target.value)}
                        />
                        <p className="text-muted-foreground text-sm">{t('Set to 0 for no limit. Available disk space still applies.')}</p>
                        <InputError message={errors.max_file_size_mb} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="upload_type_restriction">{t('Limit file types uploading to')}</Label>

                        <Select value={data.upload_type_restriction} onValueChange={(value) => setData('upload_type_restriction', value)}>
                            <SelectTrigger id="upload_type_restriction" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {restrictionOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <p className="text-muted-foreground text-sm">
                            {t('Accounts covered by this setting may only upload files whose extension is on the allowed list below.')}
                        </p>

                        <InputError className="mt-2" message={errors.upload_type_restriction} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="allowed_upload_extensions">{t('Allowed file extensions')}</Label>
                        <p className="text-muted-foreground text-sm">
                            {t('Be careful when changing this list — it can affect the security of this installation.')}
                        </p>

                        <div className="flex gap-2">
                            <Input
                                id="allowed_upload_extensions"
                                type="text"
                                placeholder={t('Extension, e.g. pdf')}
                                value={newExtension}
                                onChange={(e) => setNewExtension(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addExtension();
                                    }
                                }}
                                className="w-48"
                            />
                            <Button type="button" onClick={addExtension} disabled={newExtension.trim() === ''}>
                                {t('Add')}
                            </Button>
                        </div>

                        <div className="flex flex-wrap gap-2 rounded-lg border p-3">
                            {data.allowed_upload_extensions.length === 0 && (
                                <p className="text-muted-foreground px-1 py-1 text-sm">{t('No extensions added yet.')}</p>
                            )}
                            {data.allowed_upload_extensions.map((extension) => (
                                <span key={extension} className="bg-muted flex items-center gap-1 rounded-md py-1 pr-1 pl-2 text-sm">
                                    .{extension}
                                    <Button type="button" variant="ghost" size="sm" className="size-5 p-0" onClick={() => removeExtension(extension)}>
                                        <X className="size-3" />
                                        <span className="sr-only">{t('Remove')}</span>
                                    </Button>
                                </span>
                            ))}
                        </div>
                        <InputError message={errors.allowed_upload_extensions} />
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}
