import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import { AccountContentDeleteDialog, type ReassignCandidate } from '@/components/account-content-delete-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SavedIndicator } from '@/components/save-button';
import { TwoFactorResetPanel } from '@/components/two-factor-reset-panel';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { ApiTokensPanel, type UserApiToken } from '@/components/users/api-tokens-panel';
import { AssignableRole, ClientOption, UserForm } from '@/components/user-form';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface UsersEditProps {
    user: {
        id: number;
        name: string;
        email: string;
        role_id: number | null;
        active: boolean;
        two_factor_enabled: boolean;
    };
    roles: AssignableRole[];
    clients: ClientOption[];
    assigned_client_ids: number[];
    is_self: boolean;
    is_last_administrator: boolean;
    content: { files: number; folders: number };
    reassign_candidates: ReassignCandidate[];
    api_tokens: UserApiToken[];
}

type Tab = 'account' | 'api';

interface UserFormData {
    [key: string]: string | boolean | number[];
    name: string;
    email: string;
    role_id: string;
    active: boolean;
    password: string;
    password_confirmation: string;
    assigned_clients: number[];
}

export default function UsersEdit({
    user,
    roles,
    clients,
    assigned_client_ids,
    is_self,
    is_last_administrator,
    content,
    reassign_candidates,
    api_tokens,
}: UsersEditProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const [tab, setTab] = useState<Tab>('account');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('System users'), href: '/users' },
        { title: user.name, href: `/users/${user.id}` },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<UserFormData>({
        name: user.name,
        email: user.email,
        role_id: user.role_id === null ? '' : String(user.role_id),
        active: user.active,
        password: '',
        password_confirmation: '',
        assigned_clients: assigned_client_ids,
    });

    const deleteForm = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('users.update', user.id), {
            onSuccess: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })),
        });
    };

    const mayDelete = auth.permissions.includes('delete_users') && !is_self;
    const canDelete = mayDelete && !is_last_administrator;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={user.name} />

            <div className="px-4 py-6">
                <Heading title={user.name} description={t('Edit this staff account')} />

                <nav className="mb-6 flex gap-1 border-b">
                    {(['account', 'api'] as Tab[]).map((tabKey) => (
                        <button
                            type="button"
                            key={tabKey}
                            onClick={() => setTab(tabKey)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {tabKey === 'account' ? t('Account') : t('API')}
                        </button>
                    ))}
                </nav>

                {tab === 'api' && <ApiTokensPanel tokens={api_tokens} userName={user.name} />}

                {/* Hidden rather than unmounted, unlike the API panel: this is a
                    form, and switching tabs must not throw away edits that have
                    not been saved. */}
                <form onSubmit={submit} className={`space-y-6 ${tab === 'account' ? '' : 'hidden'}`}>
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
                        passwordOptional
                        errors={errors}
                    />

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="active"
                            checked={data.active}
                            disabled={is_self}
                            onCheckedChange={(checked) => setData('active', checked === true)}
                        />
                        <Label htmlFor="active" className="font-normal">
                            {t('Account is active')}
                        </Label>
                    </div>
                    {is_self && <p className="text-muted-foreground text-sm">{t('You cannot deactivate your own account.')}</p>}
                    <InputError message={errors.active} />

                    <TwoFactorResetPanel
                        enabled={user.two_factor_enabled}
                        name={user.name}
                        resetUrl={route('users.two-factor.destroy', user.id)}
                    />

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>

                        {mayDelete &&
                            (canDelete ? (
                                content.files > 0 || content.folders > 0 ? (
                                    <AccountContentDeleteDialog
                                        trigger={
                                            <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                                {t('Delete user')}
                                            </Button>
                                        }
                                        name={user.name}
                                        content={content}
                                        candidates={reassign_candidates}
                                        onConfirm={(choice) => {
                                            deleteForm.transform(() => choice);
                                            deleteForm.delete(route('users.destroy', user.id));
                                        }}
                                    />
                                ) : (
                                    <ConfirmDialog
                                        trigger={
                                            <Button type="button" variant="destructive" disabled={deleteForm.processing}>
                                                {t('Delete user')}
                                            </Button>
                                        }
                                        title={t('Delete user?')}
                                        description={t('The account of :name will be permanently deleted. This cannot be undone.', {
                                            name: user.name,
                                        })}
                                        confirmLabel={t('Delete user')}
                                        onConfirm={() => deleteForm.delete(route('users.destroy', user.id))}
                                    />
                                )
                            ) : (
                                <Button type="button" variant="outline" disabled title={t('This is the last active administrator account.')}>
                                    {t('Delete user')}
                                </Button>
                            ))}

                        <SavedIndicator recentlySuccessful={recentlySuccessful} />
                    </div>

                    <InputError message={(deleteForm.errors as Partial<Record<string, string>>).user} />
                </form>
            </div>
        </AppLayout>
    );
}
