import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

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
    /** Separates an unauthenticated visitor from the scheduler; both have no actor. */
    origin: string;
    template: string;
    replacements: Record<string, string>;
}

interface Filters {
    action: string | null;
    actor: string | null;
    from: string | null;
    to: string | null;
}

interface ActivitySubjectProps {
    entries: ActivityEntry[];
    pagination: PaginationMeta;
    filters: Filters;
    /** Only the actions this subject's own history contains, with their counts. */
    action_options: { key: string; label: string; count: number }[];
    subject_name: string;
    back_url: string;
    /** This same page, for the filter links — a file's history and a folder's are different routes. */
    route_name: string;
    route_params: Record<string, unknown>;
}

export default function ActivitySubject({
    entries,
    pagination,
    filters,
    action_options,
    subject_name,
    back_url,
    route_name,
    route_params,
}: ActivitySubjectProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const { values, set, reset, hasFilters } = useListQuery(
        route_name,
        {
            action: filters.action ?? ALL,
            actor: filters.actor ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
        },
        { action: ALL, actor: '', from: '', to: '' },
        route_params,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Activity log'), href: '/activity' },
        { title: subject_name, href: back_url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Activity history for :name', { name: subject_name })} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    {/* Once a filter is on, "every recorded action" is no
                        longer what the table shows — say how many it does. */}
                    <Heading
                        title={t('Activity history for :name', { name: subject_name })}
                        description={
                            hasFilters
                                ? t(':count matching this filter, newest first', { count: pagination.total })
                                : t('Every recorded action, newest first')
                        }
                    />
                    <Button variant="outline" asChild>
                        <Link href={back_url}>
                            <ArrowLeft className="size-4" />
                            {t('Back')}
                        </Link>
                    </Button>
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Action')} htmlFor="filter-action">
                        <Select value={values.action} onValueChange={(v) => set('action', v)}>
                            <SelectTrigger id="filter-action" className="w-72">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All actions')}</SelectItem>
                                {action_options.map((option) => (
                                    <SelectItem key={option.key} value={option.key}>
                                        {t(':action (:count)', { action: t(option.label), count: option.count })}
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
                    columns={[t('Date'), t('Account'), t('Action')]}
                    isEmpty={entries.length === 0}
                    emptyMessage={<>{hasFilters ? t('No activity matches these filters.') : t('No activity recorded yet.')}</>}
                >
                    {entries.map((entry) => (
                        <tr key={entry.id} className="border-b last:border-0">
                            <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">{dateTime(entry.created_at)}</td>
                            <td className="px-4 py-2.5">
                                {actorLabel(entry).kind === 'named' ? (
                                    entry.actor_name
                                ) : actorLabel(entry).kind === 'deleted' ? (
                                    <span className="text-muted-foreground italic">{t(actorLabel(entry).key)}</span>
                                ) : (
                                    <Badge variant="secondary">{t(actorLabel(entry).key)}</Badge>
                                )}
                            </td>
                            <td className="px-4 py-2.5">{t(entry.template, entry.replacements)}</td>
                        </tr>
                    ))}
                </TableShell>

                <Pagination meta={pagination} />
            </div>
        </AppLayout>
    );
}
