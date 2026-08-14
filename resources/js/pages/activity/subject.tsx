import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import Heading from '@/components/heading';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
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

interface ActivitySubjectProps {
    entries: ActivityEntry[];
    pagination: PaginationMeta;
    subject_name: string;
    back_url: string;
}

export default function ActivitySubject({ entries, pagination, subject_name, back_url }: ActivitySubjectProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Activity log'), href: '/activity' },
        { title: subject_name, href: back_url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Activity history for :name', { name: subject_name })} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={t('Activity history for :name', { name: subject_name })} description={t('Every recorded action, newest first')} />
                    <Button variant="outline" asChild>
                        <Link href={back_url}>
                            <ArrowLeft className="size-4" />
                            {t('Back')}
                        </Link>
                    </Button>
                </div>

                <TableShell
                    columns={[t('Date'), t('Account'), t('Action')]}
                    isEmpty={entries.length === 0}
                    emptyMessage={<>{t('No activity recorded yet.')}</>}
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
