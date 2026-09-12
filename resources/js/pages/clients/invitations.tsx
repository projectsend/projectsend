import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

/**
 * What a row is, as the server decided it — "expired" among them, which is
 * not a stored status. See Invitation::state().
 */
type InvitationState = 'pending' | 'expired' | 'redeemed' | 'revoked' | 'superseded';

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

interface InvitationsProps {
    invitations: InvitationRow[];
    pagination: PaginationMeta;
    filters: { status: string | null };
}

export default function Invitations({ invitations, pagination, filters }: InvitationsProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const { values, set, reset, hasFilters } = useListQuery('invitations.index', { status: filters.status ?? ALL }, { status: ALL });

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Invitations'), href: '/clients/invitations' }];

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Invitations')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading
                        title={t('Invitations')}
                        description={t('People invited to register a client account, and what became of each invitation')}
                    />
                    {/* No permission check on this screen: every route that
                        reaches it already requires create_clients, so somebody
                        looking at this list may always send one and revoke one. */}
                    <Button asChild>
                        <Link href={route('invitations.create')}>{t('Invite client')}</Link>
                    </Button>
                </div>

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
                                {/* Only a live link has an expiry worth reading. On a
                                    settled row the date is still stored and still true,
                                    and saying it invites somebody to wonder what expires
                                    about an invitation that was accepted. */}
                                {invitation.state === 'pending' || invitation.state === 'expired' ? dateTime(invitation.expires_at) : '—'}
                            </td>
                            <td className="px-4 py-2.5">
                                <div className="flex justify-end">
                                    {/* Only a live link can be revoked. A settled row is
                                        history, and offering a button that would 404 is
                                        worse than offering none. */}
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
                                            // preserveState so the page comes back on the
                                            // filter the person was reading.
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
        </AppLayout>
    );
}
