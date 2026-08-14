import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Trash2 } from 'lucide-react';

import { ConfirmDialog } from '@/components/confirm-dialog';
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

interface CommentEntry {
    id: number;
    body: string;
    author_name: string;
    author_type: 'staff' | 'client' | 'guest';
    visibility: string;
    visibility_label: string;
    conversation: string | null;
    pending: boolean;
    ip_address: string | null;
    created_at: string | null;
    edited_at: string | null;
    file: { id: number; name: string };
    file_url: string;
    can_approve: boolean;
    can_delete: boolean;
}

interface Filters {
    status: string | null;
    visibility: string | null;
    author_type: string | null;
    search: string | null;
    file: string | null;
    from: string | null;
    to: string | null;
}

interface CommentsIndexProps {
    entries: CommentEntry[];
    pagination: PaginationMeta;
    filters: Filters;
    pending_total: number;
    visibilities: { value: string; label: string }[];
}

/**
 * Every comment on the installation: search it, filter it, approve what is
 * held and delete what should not stand.
 *
 * This replaced a page that listed only comments awaiting approval, which
 * meant a comment nobody had to decide about could not be found at all
 * unless you remembered which file it was on.
 *
 * The list is narrowed server-side by the same rule a single file's thread
 * uses, so what is missing from it is missing on purpose: another person's
 * "only me" note, and conversations belonging to clients this viewer is not
 * assigned to.
 */
export default function CommentsIndex({ entries, pagination, filters, pending_total, visibilities }: CommentsIndexProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const { values, set, reset, hasFilters } = useListQuery(
        'comments.index',
        {
            status: filters.status ?? ALL,
            visibility: filters.visibility ?? ALL,
            author_type: filters.author_type ?? ALL,
            search: filters.search ?? '',
            file: filters.file ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
        },
        { status: ALL, visibility: ALL, author_type: ALL, search: '', file: '', from: '', to: '' },
    );

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Comments'), href: '/comments' }];

    const authorLabel = (entry: CommentEntry) =>
        entry.author_type === 'guest' ? t('Visitor') : entry.author_type === 'staff' ? t('Staff') : t('Client');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Comments')} />

            <div className="px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading title={t('Comments')} description={t('Every comment on files you can see, newest first')} />

                    {/* A shortcut, not a statistic: the number is the reason
                        somebody came here, and clicking it is what they were
                        going to do next. */}
                    {pending_total > 0 && values.status !== 'pending' && (
                        <Button variant="outline" onClick={() => set('status', 'pending')}>
                            {t(':count awaiting approval', { count: pending_total })}
                        </Button>
                    )}
                </div>

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Status')} htmlFor="filter-status">
                        <Select value={values.status} onValueChange={(v) => set('status', v)}>
                            <SelectTrigger id="filter-status" className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All comments')}</SelectItem>
                                <SelectItem value="pending">{t('Awaiting approval')}</SelectItem>
                                <SelectItem value="approved">{t('Published')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>

                    <FilterField label={t('Audience')} htmlFor="filter-visibility">
                        <Select value={values.visibility} onValueChange={(v) => set('visibility', v)}>
                            <SelectTrigger id="filter-visibility" className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('Any audience')}</SelectItem>
                                {visibilities.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {t(option.label)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>

                    <FilterField label={t('Written by')} htmlFor="filter-author-type">
                        <Select value={values.author_type} onValueChange={(v) => set('author_type', v)}>
                            <SelectTrigger id="filter-author-type" className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('Anyone')}</SelectItem>
                                <SelectItem value="staff">{t('Staff')}</SelectItem>
                                <SelectItem value="client">{t('Clients')}</SelectItem>
                                <SelectItem value="guest">{t('Visitors')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>

                    <FilterField label={t('Search')} htmlFor="filter-search">
                        <Input
                            id="filter-search"
                            type="search"
                            placeholder={t('Text or author')}
                            className="w-56"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>

                    <FilterField label={t('File')} htmlFor="filter-file">
                        <Input
                            id="filter-file"
                            type="search"
                            placeholder={t('File name')}
                            className="w-48"
                            value={values.file}
                            onChange={(e) => set('file', e.target.value, true)}
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
                    columns={[t('Comment'), t('Author'), t('File'), t('Date'), { label: t('Actions'), srOnly: true }]}
                    isEmpty={entries.length === 0}
                    emptyMessage={<>{hasFilters ? t('No comments match these filters.') : t('No comments yet.')}</>}
                >
                    {entries.map((entry) => (
                        <tr key={entry.id} className="border-b align-top last:border-0">
                            <td className="max-w-md px-4 py-2.5">
                                <p className="whitespace-pre-wrap">{entry.body}</p>
                                <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                    <Badge variant="outline" className="text-[10px]">
                                        {t(entry.visibility_label)}
                                    </Badge>
                                    {entry.conversation !== null && (
                                        <Badge variant="secondary" className="text-[10px]">
                                            {t('With :name', { name: entry.conversation })}
                                        </Badge>
                                    )}
                                    {entry.pending && (
                                        <Badge className="bg-amber-500 text-[10px] text-white hover:bg-amber-500">{t('Awaiting approval')}</Badge>
                                    )}
                                    {entry.edited_at && <span className="text-muted-foreground text-xs">{t('edited')}</span>}
                                </div>
                            </td>
                            <td className="px-4 py-2.5 whitespace-nowrap">
                                <div className="font-medium">{entry.author_name}</div>
                                <div className="text-muted-foreground text-xs">{authorLabel(entry)}</div>
                                {/* The one handle that makes repeat spam
                                    actionable — an account is already
                                    identified by who it is. */}
                                {entry.ip_address && <div className="text-muted-foreground font-mono text-xs">{entry.ip_address}</div>}
                            </td>
                            <td className="px-4 py-2.5">
                                <Link href={entry.file_url} className="hover:text-primary hover:underline">
                                    {entry.file.name}
                                </Link>
                            </td>
                            <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">{entry.created_at && dateTime(entry.created_at)}</td>
                            <td className="px-4 py-2.5 text-right whitespace-nowrap">
                                <div className="flex justify-end gap-1">
                                    {entry.can_approve && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.post(route('comments.approve', entry.id), {}, { preserveScroll: true })}
                                        >
                                            <Check className="size-4" />
                                            {t('Approve')}
                                        </Button>
                                    )}
                                    {entry.can_delete && (
                                        <ConfirmDialog
                                            title={t('Delete this comment?')}
                                            description={t('This cannot be undone.')}
                                            confirmLabel={t('Delete')}
                                            onConfirm={() => router.delete(route('comments.moderate.destroy', entry.id), { preserveScroll: true })}
                                            trigger={
                                                <Button size="sm" variant="ghost" className="text-destructive">
                                                    <Trash2 className="size-4" />
                                                    <span className="sr-only">{t('Delete')}</span>
                                                </Button>
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
