import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PermissionCatalogCategory, RoleForm } from '@/components/role-form';
import { SavedIndicator } from '@/components/save-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface RolesEditProps {
    role: {
        id: number;
        name: string;
        is_system: boolean;
        is_administrator: boolean;
        client_scoped: boolean;
        users_count: number;
        permissions: string[];
    };
    catalog: PermissionCatalogCategory[];
}

interface RoleFormData {
    [key: string]: string | string[] | boolean;
    name: string;
    client_scoped: boolean;
    permissions: string[];
}

export default function RolesEdit({ role, catalog }: RolesEditProps) {
    const { t } = useTranslation();

    const displayName = role.is_system ? t(role.name) : role.name;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Roles'), href: '/roles' },
        { title: displayName, href: `/roles/${role.id}` },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<RoleFormData>({
        name: role.name,
        client_scoped: role.client_scoped,
        permissions: role.permissions,
    });

    const deleteForm = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('roles.update', role.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={displayName} />

            <div className="px-4 py-6">
                <Heading title={displayName} description={t(':count accounts have this role', { count: role.users_count })} />

                {role.is_administrator ? (
                    <Alert>
                        <ShieldCheck className="size-4" />
                        <AlertDescription>{t('The administrator role always has every permission and cannot be edited.')}</AlertDescription>
                    </Alert>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <RoleForm
                            name={data.name}
                            onNameChange={(name) => setData('name', name)}
                            nameLocked={role.is_system}
                            clientScoped={data.client_scoped}
                            onClientScopedChange={(value) => setData('client_scoped', value)}
                            scopeLocked={role.is_system}
                            permissions={data.permissions}
                            onPermissionsChange={(permissions) => setData('permissions', permissions)}
                            catalog={catalog}
                            errors={errors}
                        />

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {t('Save')}
                            </Button>

                            {!role.is_system && role.users_count === 0 && (
                                <ConfirmDialog
                                    trigger={
                                        <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                            {t('Delete role')}
                                        </Button>
                                    }
                                    title={t('Delete role?')}
                                    description={t('The role ":name" will be permanently deleted. This cannot be undone.', { name: displayName })}
                                    confirmLabel={t('Delete role')}
                                    onConfirm={() => deleteForm.delete(route('roles.destroy', role.id))}
                                />
                            )}

                            <SavedIndicator recentlySuccessful={recentlySuccessful} />
                        </div>

                        <InputError message={(deleteForm.errors as Partial<Record<string, string>>).role} />
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
