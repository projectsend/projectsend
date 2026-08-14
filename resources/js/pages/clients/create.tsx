import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ClientCustomFieldsSection, type CustomFieldDefinition } from '@/components/client-custom-fields-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordRequirements } from '@/components/password-requirements';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ClientFormData {
    [key: string]: string | Record<string, string>;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    storage_quota_mb: string;
    custom_field_values: Record<string, string>;
}

interface ClientsCreateProps {
    custom_fields: CustomFieldDefinition[];
    default_storage_quota_mb: number;
}

export default function ClientsCreate({ custom_fields, default_storage_quota_mb }: ClientsCreateProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: t('New client'), href: '/clients/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<ClientFormData>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        // Empty = inherit the site default rather than baking in today's
        // numeric value — see the field's own hint text below.
        storage_quota_mb: '',
        custom_field_values: Object.fromEntries(custom_fields.map((field) => [field.id, field.type === 'checkbox' ? '0' : ''])),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('clients.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New client')} />

            <div className="px-4 py-6">
                <Heading title={t('New client')} description={t('Create an account for someone you share files with')} />

                <form onSubmit={submit} className="grid max-w-md gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoComplete="off" />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="off"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            autoComplete="new-password"
                        />
                        <PasswordRequirements />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">{t('Confirm password')}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="storage_quota_mb">{t('Storage quota (MB)')}</Label>
                        <Input
                            id="storage_quota_mb"
                            type="number"
                            min={0}
                            placeholder={String(default_storage_quota_mb)}
                            value={data.storage_quota_mb}
                            onChange={(e) => setData('storage_quota_mb', e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">
                            {default_storage_quota_mb > 0
                                ? t('Blank = inherit the site default (currently :default MB). Set a value to give this client their own limit.', {
                                      default: default_storage_quota_mb,
                                  })
                                : t('Blank = unlimited (no site default is set). Set a value to give this client their own limit.')}
                        </p>
                        <InputError message={errors.storage_quota_mb} />
                    </div>

                    <ClientCustomFieldsSection
                        fields={custom_fields}
                        values={data.custom_field_values}
                        onChange={(fieldId, value) => setData('custom_field_values', { ...data.custom_field_values, [fieldId]: value })}
                        errors={errors}
                    />

                    <div>
                        <Button type="submit" disabled={processing}>
                            {t('Create client')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
