import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface ScheduledTask {
    command: string;
    label: string;
    status: 'success' | 'failed' | null;
    message: string | null;
    /** What the task found, for the tasks that find something. Never set on a failure. */
    detail: string | null;
    duration_ms: number | null;
    ran_at: string | null;
}

interface FailedJob {
    id: string;
    connection: string;
    queue: string;
    exception: string | null;
    failed_at: string;
}

type Tab = 'tasks' | 'failed';

interface SchedulerSettingsProps {
    tab: Tab;
    tasks: ScheduledTask[];
    failed_jobs: FailedJob[];
    failed_pagination: PaginationMeta;
    failed_total: number;
    pending_jobs_count: number;
}

export default function SchedulerSettings({
    tab,
    tasks,
    failed_jobs,
    failed_pagination,
    failed_total,
    pending_jobs_count,
}: SchedulerSettingsProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Scheduler'), href: '/system/settings/scheduler' },
    ];

    const retry = (id: string) => {
        router.post(route('system-settings.scheduler.retry', id), {}, { preserveScroll: true });
    };

    const destroy = (id: string) => {
        router.delete(route('system-settings.scheduler.destroy', id), { preserveScroll: true });
    };

    const destroyAll = () => {
        router.delete(route('system-settings.scheduler.destroy-all'), { preserveScroll: true });
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: 'tasks', label: t('Scheduled tasks') },
        { key: 'failed', label: t('Failed jobs') },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Scheduler settings')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('Scheduler')}
                    description={t('Whether scheduled tasks are actually running, and any queued email that failed to send')}
                />

                <div className="border-border flex gap-1 border-b">
                    {tabs.map(({ key, label }) => (
                        <Link
                            key={key}
                            href={route('system-settings.scheduler.index', key === 'failed' ? { tab: 'failed' } : {})}
                            preserveScroll
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                                tab === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'
                            }`}
                        >
                            {label}
                            {key === 'failed' && failed_total > 0 && (
                                <span className="text-muted-foreground ml-1.5 text-xs">({failed_total})</span>
                            )}
                        </Link>
                    ))}
                </div>

                {tab === 'tasks' && (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-muted-foreground text-left text-xs">
                                <tr>
                                    <th className="px-4 py-2 font-medium">{t('Task')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Status')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Last ran')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Duration')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Message')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tasks.map((task) => (
                                    <tr key={task.command}>
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium">{t(task.label)}</p>
                                            <p className="text-muted-foreground text-xs">{task.command}</p>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {task.status === 'success' && <Badge variant="success">{t('Succeeded')}</Badge>}
                                            {task.status === 'failed' && <Badge variant="destructive">{t('Failed')}</Badge>}
                                            {task.status === null && <Badge variant="outline">{t('Never run')}</Badge>}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">
                                            {task.ran_at ? dateTime(task.ran_at) : '—'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">
                                            {task.duration_ms !== null ? `${task.duration_ms} ms` : '—'}
                                        </td>
                                        {/* A failure's own message wins: what the
                                            last successful run found is not the
                                            answer to why this one broke. */}
                                        <td className="text-muted-foreground max-w-xs px-4 py-2.5 break-words">{task.message ?? task.detail ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {tab === 'failed' && (
                    <div>
                        <div className="flex items-start justify-between gap-4">
                            <HeadingSmall
                                title={t('Failed jobs')}
                                description={`${t('Pending')}: ${pending_jobs_count} · ${t('Permanently failed')}: ${failed_total}`}
                            />

                            {failed_total > 0 && (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="outline" size="sm" className="text-destructive hover:text-destructive shrink-0">
                                            {t('Delete all failed')}
                                        </Button>
                                    }
                                    title={t('Delete all failed jobs?')}
                                    description={t(
                                        'This permanently removes every failed job from the list without retrying any of them. This cannot be undone.',
                                    )}
                                    confirmLabel={t('Delete all')}
                                    onConfirm={destroyAll}
                                />
                            )}
                        </div>

                        {failed_jobs.length === 0 ? (
                            <p className="text-muted-foreground mt-4 text-sm">{t('No failed jobs.')}</p>
                        ) : (
                            <>
                                <div className="mt-4 overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/40 text-muted-foreground text-left text-xs">
                                            <tr>
                                                <th className="px-4 py-2 font-medium">{t('Queue')}</th>
                                                <th className="px-4 py-2 font-medium">{t('Error')}</th>
                                                <th className="px-4 py-2 font-medium">{t('Failed at')}</th>
                                                <th className="px-4 py-2 font-medium"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {failed_jobs.map((job) => (
                                                <tr key={job.id}>
                                                    <td className="px-4 py-2.5 whitespace-nowrap">
                                                        {job.connection} / {job.queue}
                                                    </td>
                                                    <td className="text-muted-foreground max-w-md px-4 py-2.5 break-words">
                                                        {job.exception ?? '—'}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">
                                                        {dateTime(job.failed_at)}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right whitespace-nowrap">
                                                        <div className="flex justify-end gap-1">
                                                            <Button variant="outline" size="sm" onClick={() => retry(job.id)}>
                                                                {t('Retry')}
                                                            </Button>
                                                            <ConfirmDialog
                                                                trigger={
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-destructive hover:text-destructive"
                                                                    >
                                                                        {t('Delete')}
                                                                    </Button>
                                                                }
                                                                title={t('Delete failed job?')}
                                                                description={t('This removes it from the failed jobs list without retrying it.')}
                                                                confirmLabel={t('Delete')}
                                                                onConfirm={() => destroy(job.id)}
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Pagination meta={failed_pagination} />
                            </>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
