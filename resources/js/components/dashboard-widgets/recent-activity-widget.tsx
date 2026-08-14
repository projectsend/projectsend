import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import { activityActorLabel as actorLabel } from '@/lib/activity-actor';

export interface RecentEntry {
    id: number;
    created_at: string;
    actor_name: string | null;
    actor_type: string | null;
    /** Separates an unauthenticated visitor from the scheduler; both have no actor. */
    origin: string;
    template: string;
    replacements: Record<string, string>;
}

export function RecentActivityViewAll() {
    const { t } = useTranslation();

    return (
        <Button variant="ghost" size="sm" asChild>
            <Link href="/activity">{t('View all')}</Link>
        </Button>
    );
}

export function RecentActivityWidget({ recent }: { recent: RecentEntry[] }) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    return (
        <div>
            <div className="space-y-2">
                {recent.length === 0 && <p className="text-muted-foreground text-sm">{t('No activity recorded yet.')}</p>}
                {recent.map((entry) => (
                    <div key={entry.id} className="flex items-baseline justify-between gap-4 text-sm">
                        <p className="min-w-0 truncate">
                            <span className="font-medium">{t(actorLabel(entry).key)}</span>{' '}
                            <span className="text-muted-foreground">{t(entry.template, entry.replacements)}</span>
                        </p>
                        <span className="text-muted-foreground shrink-0 text-xs">{dateTime(entry.created_at)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
