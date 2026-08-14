import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

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

interface GroupRow {
    id: number;
    name: string;
    description: string | null;
    public: boolean;
    members_count: number;
    public_url: string | null;
}

interface Filters {
    search: string | null;
    visibility: string | null;
}

interface GroupsIndexProps {
    groups: GroupRow[];
    pagination: PaginationMeta;
    filters: Filters;
}

export default function GroupsIndex({ groups, pagination, filters }: GroupsIndexProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const [copiedGroupId, setCopiedGroupId] = useState<number | null>(null);

    const can = (permission: string) => auth.permissions.includes(permission);

    const copyPublicUrl = (id: number, url: string) => {
        void navigator.clipboard.writeText(url);
        setCopiedGroupId(id);
        window.setTimeout(() => setCopiedGroupId((current) => (current === id ? null : current)), 1500);
    };

    const { values, set, reset, hasFilters } = useListQuery(
        'groups.index',
        { search: filters.search ?? '', visibility: filters.visibility ?? ALL },
        { search: '', visibility: ALL },
    );

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Groups'), href: '/groups' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Groups')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Groups')} description={t('Share files with several clients at once')} />
                    {can('create_groups') && (
                        <Button asChild>
                            <Link href={route('groups.create')}>{t('New group')}</Link>
                        </Button>
                    )}
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Search')} htmlFor="groups-search">
                        <Input
                            id="groups-search"
                            type="search"
                            placeholder={t('Name or description')}
                            className="w-64"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                    <FilterField label={t('Visibility')} htmlFor="groups-visibility">
                        <Select value={values.visibility} onValueChange={(v) => set('visibility', v)}>
                            <SelectTrigger id="groups-visibility" className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All')}</SelectItem>
                                <SelectItem value="public">{t('Public')}</SelectItem>
                                <SelectItem value="private">{t('Private')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </ListToolbar>

                <TableShell
                    columns={[t('Name'), t('Description'), t('Members'), t('Visibility'), null]}
                    isEmpty={groups.length === 0}
                    emptyMessage={<>{hasFilters ? t('No groups match these filters.') : t('No groups yet.')}</>}
                >
                    {groups.map((group) => (
                        <tr key={group.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5 font-medium">{group.name}</td>
                            <td className="text-muted-foreground max-w-xs truncate px-4 py-2.5">{group.description}</td>
                            <td className="text-muted-foreground px-4 py-2.5">{group.members_count}</td>
                            <td className="px-4 py-2.5">
                                <Badge variant={group.public ? 'default' : 'secondary'}>{group.public ? t('Public') : t('Private')}</Badge>
                            </td>
                            <td className="px-4 py-2.5 text-right">
                                <div className="flex justify-end gap-2">
                                    {group.public && group.public_url && (
                                        <Button variant="outline" size="sm" onClick={() => copyPublicUrl(group.id, group.public_url!)}>
                                            {copiedGroupId === group.id ? <Check className="size-4" /> : <Copy className="size-4" />}
                                            {t('Copy link')}
                                        </Button>
                                    )}
                                    {can('edit_groups') && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('groups.edit', group.id)}>{t('Edit')}</Link>
                                        </Button>
                                    )}
                                    {can('delete_groups') && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                    {t('Delete')}
                                                </Button>
                                            }
                                            title={t('Delete group?')}
                                            description={t('The group ":name" will be deleted. Its clients keep their accounts.', {
                                                name: group.name,
                                            })}
                                            confirmLabel={t('Delete group')}
                                            onConfirm={() => router.delete(route('groups.destroy', group.id), { preserveScroll: true })}
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
