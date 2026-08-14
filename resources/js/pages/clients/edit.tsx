import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { AccountContentDeleteDialog, type ReassignCandidate } from '@/components/account-content-delete-dialog';
import { ClientCustomFieldsSection, type CustomFieldDefinition } from '@/components/client-custom-fields-section';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SavedIndicator } from '@/components/save-button';
import { TwoFactorResetPanel } from '@/components/two-factor-reset-panel';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordRequirements } from '@/components/password-requirements';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ClientsEditProps {
    client: {
        id: number;
        name: string;
        email: string;
        active: boolean;
        account_requested: boolean;
        storage_quota_mb: number;
        two_factor_enabled: boolean;
    };
    default_storage_quota_mb: number;
    storage_used_mb: number;
    custom_fields: CustomFieldDefinition[];
    custom_field_values: Record<string, string>;
    content: { files: number; folders: number };
    reassign_candidates: ReassignCandidate[];
}

interface ClientFormData {
    [key: string]: string | boolean | Record<string, string>;
    name: string;
    email: string;
    active: boolean;
    password: string;
    password_confirmation: string;
    storage_quota_mb: string;
    custom_field_values: Record<string, string>;
}

export default function ClientsEdit({
    client,
    default_storage_quota_mb,
    storage_used_mb,
    custom_fields,
    custom_field_values,
    content,
    reassign_candidates,
}: ClientsEditProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: client.name, href: `/clients/${client.id}` },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<ClientFormData>({
        name: client.name,
        email: client.email,
        active: client.active,
        password: '',
        password_confirmation: '',
        // Represented as empty (not literal "0") when inheriting, so the
        // input shows the resolved default as a placeholder instead of a
        // number the admin has to know to type themselves.
        storage_quota_mb: client.storage_quota_mb > 0 ? String(client.storage_quota_mb) : '',
        custom_field_values: Object.fromEntries(
            custom_fields.map((field) => [field.id, custom_field_values[field.id] ?? (field.type === 'checkbox' ? '0' : '')]),
        ),
    });

    const quotaMb = Number(data.storage_quota_mb) || 0;
    // A stored 0 inherits the site default rather than meaning unlimited
    // — mirrors ClientStorageUsage::quotaMb()'s resolution server-side.
    const effectiveQuotaMb = quotaMb > 0 ? quotaMb : default_storage_quota_mb;
    const usagePercent = effectiveQuotaMb > 0 ? Math.min(100, Math.round((storage_used_mb / effectiveQuotaMb) * 100)) : 0;

    const deleteForm = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('clients.update', client.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={client.name} />

            <div className="px-4 py-6">
                <Heading title={client.name} description={t('Edit this client account')} />

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
                        <Label htmlFor="password">{t('New password (leave blank to keep current)')}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
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
                            required={data.password !== ''}
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox id="active" checked={data.active} onCheckedChange={(checked) => setData('active', checked === true)} />
                        <Label htmlFor="active" className="font-normal">
                            {t('Account is active')}
                        </Label>
                    </div>
                    {client.account_requested && (
                        <p className="text-muted-foreground text-sm">{t('Activating this account approves its pending request.')}</p>
                    )}
                    <InputError message={errors.active} />

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
                            {effectiveQuotaMb > 0
                                ? t(':used MB of :quota MB used', { used: storage_used_mb, quota: effectiveQuotaMb })
                                : t(':used MB used, no limit', { used: storage_used_mb })}
                            {quotaMb === 0 &&
                                (effectiveQuotaMb > 0
                                    ? ' ' + t('(blank = inheriting the site default)')
                                    : ' ' + t('(blank = unlimited, no site default is set)'))}
                        </p>
                        {effectiveQuotaMb > 0 && (
                            <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                <div
                                    className={`h-full rounded-full ${usagePercent >= 90 ? 'bg-destructive' : 'bg-primary'}`}
                                    style={{ width: `${usagePercent}%` }}
                                />
                            </div>
                        )}
                        <InputError message={errors.storage_quota_mb} />
                    </div>

                    <TwoFactorResetPanel
                        enabled={client.two_factor_enabled}
                        name={client.name}
                        resetUrl={route('clients.two-factor.destroy', client.id)}
                    />

                    <ClientCustomFieldsSection
                        fields={custom_fields}
                        values={data.custom_field_values}
                        onChange={(fieldId, value) => setData('custom_field_values', { ...data.custom_field_values, [fieldId]: value })}
                        errors={errors}
                    />

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>

                        {auth.permissions.includes('delete_clients') &&
                            (content.files > 0 || content.folders > 0 ? (
                                <AccountContentDeleteDialog
                                    trigger={
                                        <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                            {t('Delete client')}
                                        </Button>
                                    }
                                    name={client.name}
                                    content={content}
                                    candidates={reassign_candidates}
                                    onConfirm={(choice) => {
                                        deleteForm.transform(() => choice);
                                        deleteForm.delete(route('clients.destroy', client.id));
                                    }}
                                />
                            ) : (
                                <ConfirmDialog
                                    trigger={
                                        <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                            {t('Delete client')}
                                        </Button>
                                    }
                                    title={t('Delete client?')}
                                    description={t('The account of :name will be permanently deleted. This cannot be undone.', {
                                        name: client.name,
                                    })}
                                    confirmLabel={t('Delete client')}
                                    onConfirm={() => deleteForm.delete(route('clients.destroy', client.id))}
                                />
                            ))}

                        <SavedIndicator recentlySuccessful={recentlySuccessful} />
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
