import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';

import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { activityActorLabel as actorLabel } from '@/lib/activity-actor';

interface ActivityEntry {
    id: number;
    created_at: string;
    actor_name: string | null;
    actor_type: string | null;
    origin: string;
    origin_label: string;
    api_token_name: string | null;
    action: string;
    template: string;
    replacements: Record<string, string>;
    actor_url: string | null;
    subject_url: string | null;
}

interface Filters {
    action: string | null;
    actor_type: string | null;
    origin: string | null;
    api_token: string | null;
    actor: string | null;
    from: string | null;
    to: string | null;
}

interface ActivityIndexProps {
    entries: ActivityEntry[];
    pagination: PaginationMeta;
    filters: Filters;
    actions: { key: string; description: string }[];
    origins: { key: string; label: string }[];
}

export default function ActivityIndex({ entries, pagination, filters, actions, origins }: ActivityIndexProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const { values, set, reset, hasFilters } = useListQuery(
        'activity.index',
        {
            action: filters.action ?? ALL,
            actor_type: filters.actor_type ?? ALL,
            origin: filters.origin ?? ALL,
            api_token: filters.api_token ?? '',
            actor: filters.actor ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
        },
        { action: ALL, actor_type: ALL, origin: ALL, api_token: '', actor: '', from: '', to: '' },
    );

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Activity log'), href: '/activity' }];

    // The CSV export honors the currently applied filters.
    const exportUrl = () => {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(values)) {
            if (value !== '' && value !== ALL) params.set(key, value);
        }
        const query = params.toString();

        return route('activity.export') + (query !== '' ? `?${query}` : '');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Activity log')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Activity log')} description={t('Everything that happened on this installation, newest first')} />
                    <Button variant="outline" asChild>
                        <a href={exportUrl()}>
                            <Download className="size-4" />
                            {t('Export CSV')}
                        </a>
                    </Button>
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Action')} htmlFor="filter-action">
                        <Select value={values.action} onValueChange={(v) => set('action', v)}>
                            <SelectTrigger id="filter-action" className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All actions')}</SelectItem>
                                {actions.map((option) => (
                                    <SelectItem key={option.key} value={option.key}>
                                        {t(option.description)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>

                    <FilterField label={t('Account type')} htmlFor="filter-actor-type">
                        <Select value={values.actor_type} onValueChange={(v) => set('actor_type', v)}>
                            <SelectTrigger id="filter-actor-type" className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All types')}</SelectItem>
                                <SelectItem value="staff">{t('System users')}</SelectItem>
                                <SelectItem value="client">{t('Clients')}</SelectItem>
                                <SelectItem value="system">{t('System')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>

                    <FilterField label={t('Origin')} htmlFor="filter-origin">
                        <Select value={values.origin} onValueChange={(v) => set('origin', v)}>
                            <SelectTrigger id="filter-origin" className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All origins')}</SelectItem>
                                {origins.map((option) => (
                                    <SelectItem key={option.key} value={option.key}>
                                        {t(option.label)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>

                    <FilterField label={t('Account')} htmlFor="filter-actor">
                        <Input
                            id="filter-actor"
                            type="search"
                            placeholder={t('Search by name')}
                            className="w-48"
                            value={values.actor}
                            onChange={(e) => set('actor', e.target.value, true)}
                        />
                    </FilterField>

                    <FilterField label={t('From')} htmlFor="filter-from">
                        <Input id="filter-from" type="date" className="w-40" value={values.from} onChange={(e) => set('from', e.target.value)} />
                    </FilterField>

                    <FilterField label={t('To')} htmlFor="filter-to">
                        <Input id="filter-to" type="date" className="w-40" value={values.to} onChange={(e) => set('to', e.target.value)} />
                    </FilterField>
                </ListToolbar>

                <TableShell
                    columns={[t('Date'), t('Account'), t('Origin'), t('Action'), { label: t('Related'), srOnly: true }]}
                    isEmpty={entries.length === 0}
                    emptyMessage={<>{hasFilters ? t('No activity matches these filters.') : t('No activity recorded yet.')}</>}
                >
                    {entries.map((entry) => (
                        <tr key={entry.id} className="border-b last:border-0">
                            <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">{dateTime(entry.created_at)}</td>
                            <td className="px-4 py-2.5">
                                {actorLabel(entry).kind === 'named' ? (
                                    entry.actor_url !== null ? (
                                        <Link href={entry.actor_url} className="hover:text-primary font-medium hover:underline">
                                            {entry.actor_name}
                                        </Link>
                                    ) : (
                                        entry.actor_name
                                    )
                                ) : actorLabel(entry).kind === 'deleted' ? (
                                    <span className="text-muted-foreground italic">{t(actorLabel(entry).key)}</span>
                                ) : (
                                    <Badge variant="secondary">{t(actorLabel(entry).key)}</Badge>
                                )}
                            </td>
                            <td className="px-4 py-2.5 whitespace-nowrap">
                                {entry.origin === 'api' ? (
                                    <Badge variant="outline" title={entry.api_token_name ?? undefined}>
                                        {entry.api_token_name === null
                                            ? t(entry.origin_label)
                                            : t(':origin · :token', { origin: t(entry.origin_label), token: entry.api_token_name })}
                                    </Badge>
                                ) : (
                                    <span className="text-muted-foreground text-sm">{t(entry.origin_label)}</span>
                                )}
                            </td>
                            <td className="px-4 py-2.5">{t(entry.template, entry.replacements)}</td>
                            <td className="px-4 py-2.5 text-right">
                                {entry.subject_url !== null && (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={entry.subject_url}>{t('View')}</Link>
                                    </Button>
                                )}
                            </td>
                        </tr>
                    ))}
                </TableShell>

                <Pagination meta={pagination} />
            </div>
        </AppLayout>
    );
}
