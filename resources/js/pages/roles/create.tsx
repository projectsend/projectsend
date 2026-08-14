import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import { PermissionCatalogCategory, RoleForm } from '@/components/role-form';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface RolesCreateProps {
    catalog: PermissionCatalogCategory[];
}

interface RoleFormData {
    [key: string]: string | string[] | boolean;
    name: string;
    client_scoped: boolean;
    permissions: string[];
}

export default function RolesCreate({ catalog }: RolesCreateProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Roles'), href: '/roles' },
        { title: t('New role'), href: '/roles/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<RoleFormData>({
        name: '',
        client_scoped: false,
        permissions: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('roles.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New role')} />

            <div className="px-4 py-6">
                <Heading title={t('New role')} description={t('Custom roles let you grant a precise set of permissions')} />

                <form onSubmit={submit} className="space-y-6">
                    <RoleForm
                        name={data.name}
                        onNameChange={(name) => setData('name', name)}
                        nameLocked={false}
                        clientScoped={data.client_scoped}
                        onClientScopedChange={(value) => setData('client_scoped', value)}
                        scopeLocked={false}
                        permissions={data.permissions}
                        onPermissionsChange={(permissions) => setData('permissions', permissions)}
                        catalog={catalog}
                        errors={errors}
                    />

                    <Button type="submit" disabled={processing}>
                        {t('Create role')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
