import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

import { AccountContentDeleteDialog, type ReassignCandidate } from '@/components/account-content-delete-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ClientRow {
    id: number;
    name: string;
    email: string;
    active: boolean;
    account_requested: boolean;
    created_at: string | null;
    content: { files: number; folders: number };
}

interface Filters {
    search: string | null;
    status: string | null;
}

interface ClientsIndexProps {
    clients: ClientRow[];
    pagination: PaginationMeta;
    filters: Filters;
    reassign_candidates: ReassignCandidate[];
}

export default function ClientsIndex({ clients, pagination, filters, reassign_candidates }: ClientsIndexProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const can = (permission: string) => auth.permissions.includes(permission);

    const { values, set, reset, hasFilters } = useListQuery(
        'clients.index',
        { search: filters.search ?? '', status: filters.status ?? ALL },
        { search: '', status: ALL },
    );

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Clients'), href: '/clients' }];

    const statusBadge = (client: ClientRow) => {
        if (client.account_requested) {
            return <Badge variant="outline">{t('Pending approval')}</Badge>;
        }

        return <Badge variant={client.active ? 'secondary' : 'destructive'}>{client.active ? t('Active') : t('Inactive')}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Clients')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Clients')} description={t('The people you share files with')} />
                    <div className="flex gap-2">
                        {can('manage_custom_fields') && (
                            <Button variant="outline" asChild>
                                <Link href={route('client-custom-fields.index')}>{t('Manage custom fields')}</Link>
                            </Button>
                        )}
                        {can('create_clients') && (
                            <Button asChild>
                                <Link href={route('clients.create')}>{t('New client')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Search')} htmlFor="clients-search">
                        <Input
                            id="clients-search"
                            type="search"
                            placeholder={t('Name or email address')}
                            className="w-64"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                    <FilterField label={t('Status')} htmlFor="clients-status">
                        <Select value={values.status} onValueChange={(v) => set('status', v)}>
                            <SelectTrigger id="clients-status" className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All statuses')}</SelectItem>
                                <SelectItem value="active">{t('Active')}</SelectItem>
                                <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </ListToolbar>

                <TableShell
                    columns={[t('Name'), t('Email address'), t('Status'), null]}
                    isEmpty={clients.length === 0}
                    emptyMessage={<>{hasFilters ? t('No clients match these filters.') : t('No clients yet.')}</>}
                >
                    {clients.map((client) => (
                        <tr key={client.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">{client.name}</td>
                            <td className="text-muted-foreground px-4 py-2.5">{client.email}</td>
                            <td className="px-4 py-2.5">{statusBadge(client)}</td>
                            <td className="px-4 py-2.5 text-right">
                                <div className="flex justify-end gap-1">
                                    {can('edit_clients') && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('clients.files', client.id)}>{t('Files')}</Link>
                                        </Button>
                                    )}
                                    {can('edit_clients') && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('clients.edit', client.id)}>{t('Edit')}</Link>
                                        </Button>
                                    )}
                                    {can('delete_clients') &&
                                        (client.content.files > 0 || client.content.folders > 0 ? (
                                            <AccountContentDeleteDialog
                                                trigger={
                                                    <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                        {t('Delete')}
                                                    </Button>
                                                }
                                                name={client.name}
                                                content={client.content}
                                                candidates={reassign_candidates.filter((candidate) => candidate.id !== client.id)}
                                                onConfirm={(choice) =>
                                                    router.delete(route('clients.destroy', client.id), {
                                                        data: choice,
                                                        preserveScroll: true,
                                                    })
                                                }
                                            />
                                        ) : (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                        {t('Delete')}
                                                    </Button>
                                                }
                                                title={t('Delete client?')}
                                                description={t('The account of :name will be permanently deleted. This cannot be undone.', {
                                                    name: client.name,
                                                })}
                                                confirmLabel={t('Delete client')}
                                                onConfirm={() => router.delete(route('clients.destroy', client.id), { preserveScroll: true })}
                                            />
                                        ))}
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
