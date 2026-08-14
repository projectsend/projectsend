import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Circle, CircleDot } from 'lucide-react';

import Heading from '@/components/heading';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface NotificationEntry {
    id: number;
    created_at: string;
    read_at: string | null;
    template: string;
    replacements: Record<string, string>;
    url: string | null;
}

interface NotificationsIndexProps {
    entries: NotificationEntry[];
    pagination: PaginationMeta;
}

export default function NotificationsIndex({ entries, pagination }: NotificationsIndexProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Notifications'), href: '/notifications' }];

    /** Only marks read (used when opening an entry), then hands off to onFinish. */
    const markRead = (entry: NotificationEntry, onFinish: () => void) => {
        if (entry.read_at !== null) {
            onFinish();
            return;
        }
        // onFinish sequences the navigation after this visit settles,
        // instead of firing both concurrently — Inertia serializes
        // visits, so an unsequenced router.visit() right after this call
        // could race (and interrupt) this one.
        router.post(route('notifications.read', entry.id), {}, { preserveScroll: true, onFinish });
    };

    /** The explicit per-row toggle — flips either direction, no navigation involved. */
    const toggleRead = (entry: NotificationEntry) => {
        const routeName = entry.read_at !== null ? 'notifications.unread' : 'notifications.read';
        router.post(route(routeName, entry.id), {}, { preserveScroll: true });
    };

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    const hasUnread = entries.some((entry) => entry.read_at === null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Notifications')} />

            <div className="p-4">
                <div className="mb-4 flex items-center justify-between">
                    <Heading title={t('Notifications')} />
                    <Button variant="outline" size="sm" onClick={markAllRead} disabled={!hasUnread}>
                        {t('Mark all read')}
                    </Button>
                </div>

                {entries.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{t('You have no notifications yet.')}</p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {entries.map((entry) => (
                            <li
                                key={entry.id}
                                className={`flex items-start justify-between gap-4 px-4 py-3 ${entry.read_at === null ? 'bg-muted/40' : ''}`}
                            >
                                <button
                                    type="button"
                                    onClick={() => markRead(entry, () => entry.url && router.visit(entry.url))}
                                    className="min-w-0 flex-1 text-left"
                                >
                                    <p className="text-sm break-words whitespace-normal">{t(entry.template, entry.replacements)}</p>
                                    <p className="text-muted-foreground text-xs">{dateTime(entry.created_at)}</p>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => toggleRead(entry)}
                                    className="text-muted-foreground hover:text-foreground mt-1 shrink-0"
                                    aria-label={entry.read_at === null ? t('Mark as read') : t('Mark as unread')}
                                    title={entry.read_at === null ? t('Mark as read') : t('Mark as unread')}
                                >
                                    {entry.read_at === null ? <CircleDot className="text-primary size-4" /> : <Circle className="size-4" />}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination meta={pagination} />
            </div>
        </AppLayout>
    );
}
