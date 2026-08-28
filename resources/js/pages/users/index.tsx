import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

import { AccountContentDeleteDialog, type ReassignCandidate } from '@/components/account-content-delete-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { SeatLimitedAction, type SeatState } from '@/components/seat-limit';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_is_system: boolean;
    active: boolean;
    two_factor_enabled: boolean;
    created_at: string | null;
    is_self: boolean;
    is_last_administrator: boolean;
    content: { files: number; folders: number };
    api_tokens: { total: number; active: number };
}

interface Filters {
    search: string | null;
    role: string | null;
    status: string | null;
}

interface UsersIndexProps {
    users: UserRow[];
    pagination: PaginationMeta;
    filters: Filters;
    roles: { id: number; name: string }[];
    reassign_candidates: ReassignCandidate[];
    seats: SeatState | null;
}

export default function UsersIndex({ users, pagination, filters, roles, reassign_candidates, seats }: UsersIndexProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const { values, set, reset, hasFilters } = useListQuery(
        'users.index',
        { search: filters.search ?? '', role: filters.role ?? ALL, status: filters.status ?? ALL },
        { search: '', role: ALL, status: ALL },
    );

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('System users'), href: '/users' }];
    const can = (permission: string) => auth.permissions.includes(permission);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('System users')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('System users')} description={t('The people who administer this installation and upload files')} />
                    {can('create_users') && (
                        <SeatLimitedAction
                            seats={seats}
                            href={route('users.create')}
                            label={t('New user')}
                            usage={({ used, limit }) => t(':used of :limit staff seats used', { used, limit })}
                        />
                    )}
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Search')} htmlFor="users-search">
                        <Input
                            id="users-search"
                            type="search"
                            placeholder={t('Name or email address')}
                            className="w-64"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                    <FilterField label={t('Role')} htmlFor="users-role">
                        <Select value={values.role} onValueChange={(v) => set('role', v)}>
                            <SelectTrigger id="users-role" className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All roles')}</SelectItem>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={String(role.id)}>
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label={t('Status')} htmlFor="users-status">
                        <Select value={values.status} onValueChange={(v) => set('status', v)}>
                            <SelectTrigger id="users-status" className="w-40">
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
                    columns={[t('Name'), t('Email address'), t('Role'), t('Status'), t('API tokens'), null]}
                    isEmpty={users.length === 0}
                    emptyMessage={<>{hasFilters ? t('No users match these filters.') : t('No users yet.')}</>}
                >
                    {users.map((user) => (
                        <tr key={user.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">
                                <span className="flex items-center gap-1.5">
                                    {user.name}
                                    {user.two_factor_enabled && (
                                        <ShieldCheck className="text-primary size-4" aria-label={t('Two-factor authentication is enabled.')} />
                                    )}
                                </span>
                            </td>
                            <td className="text-muted-foreground px-4 py-2.5">{user.email}</td>
                            <td className="px-4 py-2.5">{user.role ? (user.role_is_system ? t(user.role) : user.role) : '—'}</td>
                            <td className="px-4 py-2.5">
                                <Badge variant={user.active ? 'secondary' : 'destructive'}>{user.active ? t('Active') : t('Inactive')}</Badge>
                            </td>
                            {/* Three distinct states, because "holds a live
                                credential", "used to and no longer does" and
                                "never touched the API" are different answers and
                                only the first is a badge worth noticing. */}
                            <td className="px-4 py-2.5">
                                {user.api_tokens.active > 0 ? (
                                    <Badge variant="secondary">{t(':count active', { count: user.api_tokens.active })}</Badge>
                                ) : user.api_tokens.total > 0 ? (
                                    <span className="text-muted-foreground" title={t('This account has created API tokens before, but none are live now.')}>
                                        {t(':count expired', { count: user.api_tokens.total })}
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">—</span>
                                )}
                            </td>
                            <td className="px-4 py-2.5 text-right">
                                <div className="flex justify-end gap-1">
                                    {can('edit_users') && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('users.edit', user.id)}>{t('Edit')}</Link>
                                        </Button>
                                    )}
                                    {can('delete_users') &&
                                        !user.is_self &&
                                        (user.is_last_administrator ? (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground"
                                                disabled
                                                title={t('This is the last active administrator account.')}
                                            >
                                                {t('Delete')}
                                            </Button>
                                        ) : user.content.files > 0 || user.content.folders > 0 ? (
                                            <AccountContentDeleteDialog
                                                trigger={
                                                    <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                        {t('Delete')}
                                                    </Button>
                                                }
                                                name={user.name}
                                                content={user.content}
                                                candidates={reassign_candidates.filter((candidate) => candidate.id !== user.id)}
                                                onConfirm={(choice) =>
                                                    router.delete(route('users.destroy', user.id), {
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
                                                title={t('Delete user?')}
                                                description={t('The account of :name will be permanently deleted. This cannot be undone.', {
                                                    name: user.name,
                                                })}
                                                confirmLabel={t('Delete user')}
                                                onConfirm={() => router.delete(route('users.destroy', user.id), { preserveScroll: true })}
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
