import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { AssignableRole, ClientOption, UserForm } from '@/components/user-form';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface UsersCreateProps {
    roles: AssignableRole[];
    clients: ClientOption[];
}

interface UserFormData {
    [key: string]: string | number[];
    name: string;
    email: string;
    role_id: string;
    password: string;
    password_confirmation: string;
    assigned_clients: number[];
}

export default function UsersCreate({ roles, clients }: UsersCreateProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('System users'), href: '/users' },
        { title: t('New user'), href: '/users/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<UserFormData>({
        name: '',
        email: '',
        role_id: '',
        password: '',
        password_confirmation: '',
        assigned_clients: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New user')} />

            <div className="px-4 py-6">
                <Heading title={t('New user')} description={t('Create a staff account for this installation')} />

                <form onSubmit={submit} className="space-y-6">
                    <UserForm
                        name={data.name}
                        email={data.email}
                        roleId={data.role_id}
                        password={data.password}
                        passwordConfirmation={data.password_confirmation}
                        onChange={(field, value) => setData(field, value)}
                        roles={roles}
                        clients={clients}
                        assignedClients={data.assigned_clients}
                        onAssignedClientsChange={(ids) => setData('assigned_clients', ids)}
                        passwordOptional={false}
                        errors={errors}
                    />

                    <Button type="submit" disabled={processing}>
                        {t('Create user')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
