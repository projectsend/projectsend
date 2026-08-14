import { Link, router } from '@inertiajs/react';

import { xsrfToken } from '@/lib/xsrf';
import { Circle, CircleDot } from 'lucide-react';
import { useEffect, useState } from 'react';

import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

interface NotificationEntry {
    id: number;
    created_at: string;
    read_at: string | null;
    template: string;
    replacements: Record<string, string>;
    url: string | null;
}

function postJson(url: string): Promise<void> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
    }).then(() => undefined);
}

/**
 * The bell dropdown's body: a widget mounted across every page, not
 * owned by one — fetches/mutates directly rather than through an
 * Inertia page visit, mirroring use-zip-download.ts's manual
 * XSRF-tokened fetch pattern.
 */
export function NotificationPanelContent({ onUnreadCountChange }: { onUnreadCountChange: (count: number) => void }) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const [entries, setEntries] = useState<NotificationEntry[] | null>(null);

    useEffect(() => {
        fetch(route('notifications.recent'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((body: { entries: NotificationEntry[] }) => setEntries(body.entries));
    }, []);

    /** Only marks read (used when opening an entry) — never toggles back to unread. */
    const markRead = (entry: NotificationEntry): Promise<void> => {
        if (entry.read_at !== null) return Promise.resolve();

        setEntries((current) => current?.map((e) => (e.id === entry.id ? { ...e, read_at: new Date().toISOString() } : e)) ?? current);
        onUnreadCountChange(-1);

        return postJson(route('notifications.read', entry.id));
    };

    /** The explicit per-row toggle — flips either direction. */
    const toggleRead = (entry: NotificationEntry) => {
        const markingUnread = entry.read_at !== null;

        setEntries(
            (current) => current?.map((e) => (e.id === entry.id ? { ...e, read_at: markingUnread ? null : new Date().toISOString() } : e)) ?? current,
        );
        onUnreadCountChange(markingUnread ? 1 : -1);

        postJson(route(markingUnread ? 'notifications.unread' : 'notifications.read', entry.id));
    };

    const markAllRead = () => {
        const unreadCount = entries?.filter((e) => e.read_at === null).length ?? 0;
        setEntries((current) => current?.map((e) => ({ ...e, read_at: e.read_at ?? new Date().toISOString() })) ?? current);
        onUnreadCountChange(-unreadCount);

        postJson(route('notifications.read-all'));
    };

    const openEntry = (entry: NotificationEntry) => {
        // Wait for the mark-read request to settle before navigating —
        // firing both concurrently lets the navigation's own Inertia
        // visit race (and potentially interrupt) the mark-read fetch.
        markRead(entry).finally(() => {
            if (entry.url) router.visit(entry.url);
        });
    };

    return (
        <div className="w-80">
            <div className="flex items-center justify-between px-3 py-2">
                <p className="text-sm font-semibold">{t('Notifications')}</p>
                <button type="button" onClick={markAllRead} className="text-muted-foreground hover:text-foreground text-xs">
                    {t('Mark all read')}
                </button>
            </div>

            {entries === null ? (
                <p className="text-muted-foreground px-3 pb-3 text-sm">{t('Loading…')}</p>
            ) : entries.length === 0 ? (
                <p className="text-muted-foreground px-3 pb-3 text-sm">{t('You have no notifications yet.')}</p>
            ) : (
                <ul className="max-h-80 divide-y overflow-y-auto">
                    {entries.map((entry) => (
                        <li key={entry.id} className={`flex items-start gap-1 ${entry.read_at === null ? 'bg-muted/40' : ''}`}>
                            <button
                                type="button"
                                onClick={() => openEntry(entry)}
                                className="hover:bg-accent min-w-0 flex-1 px-3 py-2 text-left text-sm"
                            >
                                <span className="block break-words whitespace-normal">{t(entry.template, entry.replacements)}</span>
                                <span className="text-muted-foreground text-xs">{dateTime(entry.created_at)}</span>
                            </button>
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    toggleRead(entry);
                                }}
                                className="text-muted-foreground hover:text-foreground shrink-0 p-2 pr-3"
                                aria-label={entry.read_at === null ? t('Mark as read') : t('Mark as unread')}
                                title={entry.read_at === null ? t('Mark as read') : t('Mark as unread')}
                            >
                                {entry.read_at === null ? <CircleDot className="text-primary size-3.5" /> : <Circle className="size-3.5" />}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="border-t px-3 py-2">
                <Link href={route('notifications.index')} className="text-primary text-xs hover:underline">
                    {t('View all')}
                </Link>
            </div>
        </div>
    );
}
