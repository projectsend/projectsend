import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PermissionCatalogCategory, RoleForm } from '@/components/role-form';
import { SavedIndicator } from '@/components/save-button';
import { StartPageSelect, type StartPageOption } from '@/components/start-page-select';
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
        start_page: string | null;
    };
    catalog: PermissionCatalogCategory[];
    start_page_options: StartPageOption[];
}

interface RoleFormData {
    [key: string]: string | string[] | boolean | null;
    name: string;
    client_scoped: boolean;
    permissions: string[];
    start_page: string | null;
}

export default function RolesEdit({ role, catalog, start_page_options }: RolesEditProps) {
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
        start_page: role.start_page,
    });

    // The administrator role's permissions are fixed, so its form sends the
    // start page alone — anything more is refused by the server.
    const adminForm = useForm<{ start_page: string | null }>({ start_page: role.start_page });

    const deleteForm = useForm({});

    const startPageDescription = t('Where people with this role land after signing in. Each person can still choose their own in their profile.');

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
                    <div className="space-y-6">
                        <Alert>
                            <ShieldCheck className="size-4" />
                            <AlertDescription>{t('The administrator role always has every permission and cannot be edited.')}</AlertDescription>
                        </Alert>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                adminForm.patch(route('roles.update', role.id));
                            }}
                            className="space-y-6"
                        >
                            <StartPageSelect
                                value={adminForm.data.start_page}
                                onChange={(value) => adminForm.setData('start_page', value)}
                                options={start_page_options}
                                error={adminForm.errors.start_page}
                                description={startPageDescription}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={adminForm.processing}>
                                    {t('Save')}
                                </Button>
                                <SavedIndicator recentlySuccessful={adminForm.recentlySuccessful} />
                            </div>
                        </form>
                    </div>
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

                        <StartPageSelect
                            value={data.start_page}
                            onChange={(value) => setData('start_page', value)}
                            options={start_page_options}
                            grantedPermissions={data.permissions}
                            error={errors.start_page}
                            description={startPageDescription}
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
