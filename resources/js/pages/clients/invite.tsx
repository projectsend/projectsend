import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectValue, SelectTrigger } from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface InvitationFormData {
    [key: string]: string;
    email: string;
    name: string;
    group_id: string;
    storage_quota_mb: string;
}

interface InvitationRow {
    id: number;
    name: string | null;
    email: string;
    group: string | null;
    invited_by: string | null;
    created_at: string | null;
    expires_at: string;
    /** Past its expiry but still revocable — see the note on the list below. */
    expired: boolean;
}

interface ClientsInviteProps {
    groups: { id: number; name: string }[];
    default_storage_quota_mb: number;
    invitations: InvitationRow[];
    pagination: PaginationMeta;
}

export default function ClientsInvite({ groups, default_storage_quota_mb, invitations, pagination }: ClientsInviteProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: t('Invite client'), href: '/clients/invite' },
    ];

    const { data, setData, post, processing, errors } = useForm<InvitationFormData>({
        email: '',
        name: '',
        group_id: '0',
        // Empty = inherit the site default rather than baking in today's
        // numeric value — see the field's own hint text below.
        storage_quota_mb: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitations.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Invite client')} />

            <div className="px-4 py-6">
                <Heading title={t('Invite client')} description={t('Invite a client to share files with')} />

                <form onSubmit={submit} className="grid max-w-md gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoFocus
                            autoComplete="off"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name (optional)')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="off" />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="group_id">{t('Group (optional)')}</Label>
                        <Select value={data.group_id} onValueChange={(value) => setData('group_id', value)}>
                            <SelectTrigger id="group_id" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0">{t('None')}</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem key={group.id} value={String(group.id)}>
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-sm">{t('The client joins this group as soon as they register.')}</p>
                        <InputError message={errors.group_id} />
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

                    <div>
                        <Button type="submit" disabled={processing}>
                            {t('Send invitation')}
                        </Button>
                    </div>
                </form>

                <div className="mt-10">
                    <Heading
                        title={t('Outstanding invitations')}
                        description={t('Links that have been sent and not used yet. Revoking one stops it working for good.')}
                    />

                    <TableShell
                        columns={[t('Email address'), t('Group'), t('Invited by'), t('Sent'), t('Expires'), null]}
                        isEmpty={invitations.length === 0}
                        emptyMessage={<>{t('No invitations are waiting to be used.')}</>}
                    >
                        {invitations.map((invitation) => (
                            <tr key={invitation.id} className="border-b last:border-0">
                                <td className="px-4 py-2.5 font-medium">
                                    {invitation.email}
                                    {invitation.name && <span className="text-muted-foreground ml-2 font-normal">{invitation.name}</span>}
                                </td>
                                <td className="text-muted-foreground px-4 py-2.5">{invitation.group ?? '—'}</td>
                                <td className="text-muted-foreground px-4 py-2.5">{invitation.invited_by ?? '—'}</td>
                                <td className="text-muted-foreground px-4 py-2.5">{dateTime(invitation.created_at)}</td>
                                <td className="text-muted-foreground px-4 py-2.5">
                                    {invitation.expired ? <Badge variant="destructive">{t('Expired')}</Badge> : dateTime(invitation.expires_at)}
                                </td>
                                <td className="px-4 py-2.5">
                                    <div className="flex justify-end">
                                        <ConfirmDialog
                                            trigger={
                                                <Button size="sm" variant="destructive">
                                                    {t('Revoke')}
                                                </Button>
                                            }
                                            title={t('Revoke this invitation?')}
                                            description={t(
                                                'The link sent to :email stops working, and cannot be renewed by whoever holds it. You can send a new invitation at any time.',
                                                { email: invitation.email },
                                            )}
                                            confirmLabel={t('Revoke')}
                                            onConfirm={() => router.delete(route('invitations.destroy', invitation.id))}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </TableShell>

                    <Pagination meta={pagination} />
                </div>
            </div>
        </AppLayout>
    );
}
