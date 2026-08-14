import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormatDate } from '@/hooks/use-format-date';
import { useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface RequestRow {
    id: number;
    client_name: string;
    client_email: string;
    group_name: string;
    created_at: string | null;
}

interface MembershipRequestsProps {
    requests: RequestRow[];
    pagination: PaginationMeta;
    filters: { search: string | null };
}

export default function MembershipRequests({ requests, pagination, filters }: MembershipRequestsProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const { values, set, reset, hasFilters } = useListQuery('membership-requests.index', { search: filters.search ?? '' });

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Membership requests'), href: '/membership-requests' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Membership requests')} />

            <div className="px-4 py-6">
                <Heading title={t('Membership requests')} description={t('Clients asking to join groups')} />

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <Input
                        type="search"
                        placeholder={t('Search by client or group')}
                        className="w-72"
                        value={values.search}
                        onChange={(e) => set('search', e.target.value, true)}
                    />
                </ListToolbar>

                <TableShell
                    columns={[t('Client'), t('Group'), t('Requested'), null]}
                    isEmpty={requests.length === 0}
                    emptyMessage={<>{hasFilters ? t('No requests match your search.') : t('No pending requests.')}</>}
                >
                    {requests.map((request) => (
                        <tr key={request.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5">
                                <p className="font-medium">{request.client_name}</p>
                                <p className="text-muted-foreground text-xs">{request.client_email}</p>
                            </td>
                            <td className="px-4 py-2.5">{request.group_name}</td>
                            <td className="text-muted-foreground px-4 py-2.5">{dateTime(request.created_at)}</td>
                            <td className="px-4 py-2.5">
                                <div className="flex justify-end gap-2">
                                    <Button size="sm" onClick={() => router.post(route('membership-requests.approve', request.id))}>
                                        {t('Approve')}
                                    </Button>
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm" variant="destructive">
                                                {t('Deny')}
                                            </Button>
                                        }
                                        title={t('Deny this request?')}
                                        description={t(':name will not be added to the group ":group".', {
                                            name: request.client_name,
                                            group: request.group_name,
                                        })}
                                        confirmLabel={t('Deny')}
                                        onConfirm={() => router.delete(route('membership-requests.deny', request.id))}
                                    />
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
