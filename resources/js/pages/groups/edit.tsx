import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { GroupForm } from '@/components/group-form';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface Person {
    id: number;
    name: string;
    email: string;
}

interface GroupsEditProps {
    group: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        public: boolean;
    };
    members: Person[];
    available_clients: Person[];
}

interface GroupFormData {
    [key: string]: string | boolean;
    name: string;
    slug: string;
    description: string;
    public: boolean;
}

type Tab = 'general' | 'members';

export default function GroupsEdit({ group, members, available_clients }: GroupsEditProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const pageErrors = usePage().props.errors as Record<string, string>;
    const [tab, setTab] = useState<Tab>('general');
    const [selectedClient, setSelectedClient] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Groups'), href: '/groups' },
        { title: group.name, href: `/groups/${group.id}` },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<GroupFormData>({
        name: group.name,
        slug: group.slug,
        description: group.description ?? '',
        public: group.public,
    });

    const deleteForm = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('groups.update', group.id));
    };

    const addMember = () => {
        if (selectedClient !== '') {
            router.post(
                route('groups.members.store', group.id),
                { user_id: Number(selectedClient) },
                { preserveScroll: true, onSuccess: () => setSelectedClient('') },
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={group.name} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={group.name} description={t(':count members', { count: members.length })} />
                    {auth.permissions.includes('delete_groups') && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="destructive" disabled={deleteForm.processing}>
                                    {t('Delete group')}
                                </Button>
                            }
                            title={t('Delete group?')}
                            description={t('The group ":name" will be deleted. Its clients keep their accounts.', { name: group.name })}
                            confirmLabel={t('Delete group')}
                            onConfirm={() => deleteForm.delete(route('groups.destroy', group.id))}
                        />
                    )}
                </div>

                <nav className="mt-6 flex gap-1 border-b">
                    {(['general', 'members'] as Tab[]).map((tabKey) => (
                        <button
                            key={tabKey}
                            onClick={() => setTab(tabKey)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {tabKey === 'general' ? t('General') : t('Members')}
                        </button>
                    ))}
                </nav>

                <div className="mt-6">
                    {tab === 'general' ? (
                        <form onSubmit={submit} className="max-w-md space-y-6">
                            <GroupForm
                                name={data.name}
                                slug={data.slug}
                                description={data.description}
                                isPublic={data.public}
                                onChange={(field, value) => setData(field, value)}
                                errors={errors}
                            />

                            <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                        </form>
                    ) : (
                        <div className="max-w-xl space-y-4">
                            <HeadingSmall title={t('Members')} description={t('The clients this group shares files with')} />

                            <div className="flex gap-2">
                                <Select value={selectedClient} onValueChange={setSelectedClient}>
                                    <SelectTrigger className="w-72">
                                        <SelectValue placeholder={t('Select a client to add')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {available_clients.map((client) => (
                                            <SelectItem key={client.id} value={String(client.id)}>
                                                {client.name} — {client.email}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button type="button" onClick={addMember} disabled={selectedClient === ''}>
                                    {t('Add')}
                                </Button>
                            </div>
                            <InputError message={pageErrors.user_id} />

                            <div className="rounded-lg border">
                                {members.length === 0 && (
                                    <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('No members yet.')}</p>
                                )}
                                {members.map((member) => (
                                    <div key={member.id} className="flex items-center justify-between border-b px-4 py-2 last:border-0">
                                        <div>
                                            <p className="text-sm font-medium">{member.name}</p>
                                            <p className="text-muted-foreground text-xs">{member.email}</p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(route('groups.members.destroy', [group.id, member.id]), { preserveScroll: true })
                                            }
                                        >
                                            <X className="size-4" />
                                            <span className="sr-only">{t('Remove')}</span>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
