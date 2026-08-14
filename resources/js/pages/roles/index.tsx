import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

type RoleType = 'system' | 'client' | 'custom';

interface RoleRow {
    id: number;
    name: string;
    type: RoleType;
    is_system: boolean;
    is_administrator: boolean;
    users_count: number;
    permissions_count: number | null;
}

interface RolesIndexProps {
    roles: RoleRow[];
    total_permissions: number;
    filters: { type: string | null };
}

// Distinct, theme-aware tag colors per role type.
const TYPE_BADGE: Record<RoleType, string> = {
    system: 'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    client: 'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    custom: 'border-transparent bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
};

export default function RolesIndex({ roles, total_permissions, filters }: RolesIndexProps) {
    const { t } = useTranslation();

    const { values, set, reset, hasFilters } = useListQuery('roles.index', { type: filters.type ?? ALL }, { type: ALL });

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Roles'), href: '/roles' }];

    const typeLabel: Record<RoleType, string> = { system: t('System'), client: t('Client'), custom: t('Custom') };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Roles')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Roles')} description={t('What each kind of account is allowed to do')} />
                    <Button asChild>
                        <Link href={route('roles.create')}>{t('New role')}</Link>
                    </Button>
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Type')} htmlFor="roles-type">
                        <Select value={values.type} onValueChange={(v) => set('type', v)}>
                            <SelectTrigger id="roles-type" className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All types')}</SelectItem>
                                <SelectItem value="system">{t('System')}</SelectItem>
                                <SelectItem value="client">{t('Client')}</SelectItem>
                                <SelectItem value="custom">{t('Custom')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </ListToolbar>

                <TableShell
                    columns={[t('Role'), t('Type'), t('Accounts'), t('Permissions'), null]}
                    isEmpty={roles.length === 0}
                    emptyMessage={<>{t('No roles match this filter.')}</>}
                >
                    {roles.map((role) => (
                        <tr key={role.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">{role.is_system ? t(role.name) : role.name}</td>
                            <td className="px-4 py-2.5">
                                <Badge variant="outline" className={TYPE_BADGE[role.type]}>
                                    {typeLabel[role.type]}
                                </Badge>
                            </td>
                            <td className="text-muted-foreground px-4 py-2.5">{role.users_count}</td>
                            <td className="text-muted-foreground px-4 py-2.5">
                                {role.is_administrator ? t('All permissions') : `${role.permissions_count} / ${total_permissions}`}
                            </td>
                            <td className="px-4 py-2.5 text-right">
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route('roles.edit', role.id)}>{role.is_administrator ? t('View') : t('Edit')}</Link>
                                </Button>
                            </td>
                        </tr>
                    ))}
                </TableShell>
            </div>
        </AppLayout>
    );
}
