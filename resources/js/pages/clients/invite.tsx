import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectValue, SelectTrigger } from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

type Tab = 'history' | 'send';

/**
 * What a row is, as the server decided it — "expired" among them, which is
 * not a stored status. See Invitation::state().
 */
type InvitationState = 'pending' | 'expired' | 'redeemed' | 'revoked' | 'superseded';

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
    state: InvitationState;
}

interface ClientsInviteProps {
    groups: { id: number; name: string }[];
    default_storage_quota_mb: number;
    invitations: InvitationRow[];
    pagination: PaginationMeta;
    filters: { status: string | null };
    /** Live invitations across the whole table, not this filtered page. */
    pending_count: number;
}

export default function ClientsInvite({
    groups,
    default_storage_quota_mb,
    invitations,
    pagination,
    filters,
    pending_count,
}: ClientsInviteProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    // The history opens first: arriving here, the question is usually "who
    // have we already invited" — including the one you were about to invite
    // again. ?tab=send goes straight to the form for anything that means to
    // link at it, and anything unrecognised falls back to the history.
    const [tab, setTab] = useState<Tab>(new URLSearchParams(window.location.search).get('tab') === 'send' ? 'send' : 'history');

    const { values, set, reset, hasFilters } = useListQuery('invitations.create', { status: filters.status ?? ALL }, { status: ALL });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: '/clients' },
        { title: t('Invitations'), href: '/clients/invite' },
    ];

    // Every state a row can be in, said once. "Expired" and "Replaced" are
    // the two a reader needs told apart: one is a link that simply ran out,
    // the other was retired by a newer invitation to the same address.
    const stateLabels: Record<InvitationState, string> = {
        pending: t('Pending'),
        expired: t('Expired'),
        redeemed: t('Accepted'),
        revoked: t('Revoked'),
        superseded: t('Replaced'),
    };

    const stateVariants: Record<InvitationState, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        pending: 'default',
        expired: 'destructive',
        redeemed: 'secondary',
        revoked: 'outline',
        superseded: 'outline',
    };

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
                <Heading title={t('Invitations')} description={t('People invited to register a client account, and what became of each invitation')} />

                <nav className="mb-6 flex gap-1 border-b">
                    {(['history', 'send'] as Tab[]).map((tabKey) => (
                        <button
                            type="button"
                            key={tabKey}
                            onClick={() => setTab(tabKey)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {tabKey === 'send'
                                ? t('Send an invitation')
                                : // The count is of live invitations, not of the
                                  // rows below: the history is mostly settled, and
                                  // the number worth carrying in a label is the one
                                  // that says whether anybody is still waiting.
                                  t('History (:count pending)', { count: pending_count })}
                        </button>
                    ))}
                </nav>

                {/* Hidden rather than unmounted, the same as the staff account
                    form's tabs: switching to the list and back must not throw
                    away a half-typed invitation. */}
                <form onSubmit={submit} className={`grid max-w-md gap-6 ${tab === 'send' ? '' : 'hidden'}`}>
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
                            {t('Send an invitation')}
                        </Button>
                    </div>
                </form>

                {tab === 'history' && (
                    <div>
                        <ListToolbar showClear={hasFilters} onClear={reset}>
                            <FilterField label={t('Status')} htmlFor="invitations-status">
                                <Select value={values.status} onValueChange={(v) => set('status', v)}>
                                    <SelectTrigger id="invitations-status" className="w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>{t('All statuses')}</SelectItem>
                                        <SelectItem value="pending">{stateLabels.pending}</SelectItem>
                                        <SelectItem value="expired">{stateLabels.expired}</SelectItem>
                                        <SelectItem value="redeemed">{stateLabels.redeemed}</SelectItem>
                                        <SelectItem value="revoked">{stateLabels.revoked}</SelectItem>
                                        <SelectItem value="superseded">{stateLabels.superseded}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FilterField>
                        </ListToolbar>

                        <TableShell
                            columns={[t('Email address'), t('Status'), t('Group'), t('Invited by'), t('Sent'), t('Expires'), null]}
                            isEmpty={invitations.length === 0}
                            emptyMessage={<>{hasFilters ? t('No invitations match this filter.') : t('No invitations have been sent yet.')}</>}
                        >
                            {invitations.map((invitation) => (
                                <tr key={invitation.id} className="border-b last:border-0">
                                    <td className="px-4 py-2.5 font-medium">
                                        {invitation.email}
                                        {invitation.name && <span className="text-muted-foreground ml-2 font-normal">{invitation.name}</span>}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <Badge variant={stateVariants[invitation.state]}>{stateLabels[invitation.state]}</Badge>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-2.5">{invitation.group ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-2.5">{invitation.invited_by ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-2.5">{dateTime(invitation.created_at)}</td>
                                    <td className="text-muted-foreground px-4 py-2.5">
                                        {/* Only a live link has an expiry worth reading.
                                            On a settled row the date is still stored and
                                            still true, and saying it invites somebody to
                                            wonder what expires about an invitation that
                                            was accepted. */}
                                        {invitation.state === 'pending' || invitation.state === 'expired' ? dateTime(invitation.expires_at) : '—'}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex justify-end">
                                            {/* Only a live link can be revoked. A settled
                                                row is history, and offering a button that
                                                would 404 is worse than offering none. */}
                                            {(invitation.state === 'pending' || invitation.state === 'expired') && (
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
                                                    // preserveState so the page comes back on this
                                                    // tab, and on the filter the person was reading,
                                                    // rather than on the form.
                                                    onConfirm={() =>
                                                        router.delete(route('invitations.destroy', invitation.id), {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </TableShell>

                        <Pagination meta={pagination} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
