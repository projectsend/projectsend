import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormatDate } from '@/hooks/use-format-date';
import { useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface DownloadEntry {
    id: number;
    created_at: string;
    actor_name: string;
    actor_type: string | null;
    ip_address: string | null;
    file_name?: string;
    file_url?: string | null;
}

interface Filters {
    file: string | null;
    user: string | null;
    from: string | null;
    to: string | null;
}

interface ActivityDownloadsProps {
    entries: DownloadEntry[];
    pagination: PaginationMeta;
    // Only the installation-wide page filters: a single file's history is
    // already narrowed to the one thing its filters would ask about.
    filters?: Filters;
    // Present only when scoped to a single file/folder (the details
    // panel's "View all downloads" destination). Absent for the
    // installation-wide /downloads page, which shows a File column
    // instead and has no single subject to link back to.
    subject_name?: string;
    back_url?: string;
}

export default function ActivityDownloads({ entries, pagination, filters, subject_name, back_url }: ActivityDownloadsProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const scoped = subject_name !== undefined && back_url !== undefined;

    const { values, set, reset, hasFilters } = useListQuery(
        'downloads.index',
        {
            file: filters?.file ?? '',
            user: filters?.user ?? '',
            from: filters?.from ?? '',
            to: filters?.to ?? '',
        },
        { file: '', user: '', from: '', to: '' },
    );

    const breadcrumbs: BreadcrumbItem[] = scoped
        ? [
              { title: t('Activity log'), href: '/activity' },
              { title: subject_name, href: back_url },
          ]
        : [{ title: t('Download history'), href: '/downloads' }];

    const title = scoped ? t('Download history for :name', { name: subject_name }) : t('Download history');
    const description = scoped
        ? t('Every download, newest first')
        : hasFilters
          ? t(':count matching this filter, newest first', { count: pagination.total })
          : t('Every download across the installation, newest first');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={title} description={description} />
                    {scoped && (
                        <Button variant="outline" asChild>
                            <Link href={back_url}>
                                <ArrowLeft className="size-4" />
                                {t('Back')}
                            </Link>
                        </Button>
                    )}
                </div>

                {filters !== undefined && (
                    <ListToolbar showClear={hasFilters} onClear={reset}>
                        <FilterField label={t('File')} htmlFor="filter-file">
                            <Input
                                id="filter-file"
                                type="search"
                                placeholder={t('Search by name')}
                                className="w-56"
                                value={values.file}
                                onChange={(e) => set('file', e.target.value, true)}
                            />
                        </FilterField>

                        <FilterField label={t('Downloaded by')} htmlFor="filter-user">
                            <Input
                                id="filter-user"
                                type="search"
                                placeholder={t('Search by name')}
                                className="w-48"
                                value={values.user}
                                onChange={(e) => set('user', e.target.value, true)}
                            />
                        </FilterField>

                        <FilterField label={t('From')} htmlFor="filter-from">
                            <Input id="filter-from" type="date" className="w-40" value={values.from} onChange={(e) => set('from', e.target.value)} />
                        </FilterField>

                        <FilterField label={t('To')} htmlFor="filter-to">
                            <Input id="filter-to" type="date" className="w-40" value={values.to} onChange={(e) => set('to', e.target.value)} />
                        </FilterField>
                    </ListToolbar>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b text-left">
                                <th className="px-4 py-2.5 font-medium">{t('Date')}</th>
                                {!scoped && <th className="px-4 py-2.5 font-medium">{t('File')}</th>}
                                <th className="px-4 py-2.5 font-medium">{t('Downloaded by')}</th>
                                <th className="px-4 py-2.5 font-medium">{t('IP address')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 && (
                                <tr>
                                    <td colSpan={scoped ? 3 : 4} className="text-muted-foreground px-4 py-8 text-center">
                                        {hasFilters ? t('No downloads match these filters.') : t('No downloads recorded yet.')}
                                    </td>
                                </tr>
                            )}
                            {entries.map((entry) => (
                                <tr key={entry.id} className="border-b last:border-0">
                                    <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">{dateTime(entry.created_at)}</td>
                                    {!scoped && (
                                        <td className="px-4 py-2.5">
                                            {entry.file_url ? (
                                                <Link href={entry.file_url} className="hover:text-primary font-medium hover:underline">
                                                    {entry.file_name}
                                                </Link>
                                            ) : (
                                                entry.file_name
                                            )}
                                        </td>
                                    )}
                                    <td className="px-4 py-2.5">
                                        <span className="flex items-center gap-1.5">
                                            {entry.actor_name}
                                            {entry.actor_type && (
                                                <Badge variant="secondary">{entry.actor_type === 'client' ? t('Client') : t('Staff')}</Badge>
                                            )}
                                        </span>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-2.5 font-mono text-xs">{entry.ip_address ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={pagination} />
            </div>
        </AppLayout>
    );
}
