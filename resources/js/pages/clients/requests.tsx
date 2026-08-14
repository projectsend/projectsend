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
    name: string;
    email: string;
    created_at: string | null;
}

interface RequestsProps {
    requests: RequestRow[];
    pagination: PaginationMeta;
    filters: { search: string | null };
}

export default function AccountRequests({ requests, pagination, filters }: RequestsProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const { values, set, reset, hasFilters } = useListQuery('account-requests.index', { search: filters.search ?? '' });

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Account requests'), href: '/account-requests' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Account requests')} />

            <div className="px-4 py-6">
                <Heading title={t('Account requests')} description={t('Clients who registered themselves and are waiting for approval')} />

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <Input
                        type="search"
                        placeholder={t('Search by name or email address')}
                        className="w-72"
                        value={values.search}
                        onChange={(e) => set('search', e.target.value, true)}
                    />
                </ListToolbar>

                <TableShell
                    columns={[t('Name'), t('Email address'), t('Requested'), null]}
                    isEmpty={requests.length === 0}
                    emptyMessage={<>{hasFilters ? t('No requests match your search.') : t('No pending requests.')}</>}
                >
                    {requests.map((request) => (
                        <tr key={request.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">{request.name}</td>
                            <td className="text-muted-foreground px-4 py-2.5">{request.email}</td>
                            <td className="text-muted-foreground px-4 py-2.5">{dateTime(request.created_at)}</td>
                            <td className="px-4 py-2.5">
                                <div className="flex justify-end gap-2">
                                    <Button size="sm" onClick={() => router.post(route('account-requests.approve', request.id))}>
                                        {t('Approve')}
                                    </Button>
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm" variant="destructive">
                                                {t('Deny')}
                                            </Button>
                                        }
                                        title={t('Deny this request?')}
                                        description={t('The account request of :name will be denied and the account deleted.', {
                                            name: request.name,
                                        })}
                                        confirmLabel={t('Deny')}
                                        onConfirm={() => router.delete(route('account-requests.deny', request.id))}
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
