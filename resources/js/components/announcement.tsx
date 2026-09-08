import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ExternalLink, Megaphone } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';

export interface Announcement {
    title: string;
    body: string;
    action_label: string | null;
    action_url: string | null;
    tone: 'info' | 'warning' | string;
}

/**
 * One message, shown two ways, from one shared prop — see
 * ResolvingAnnouncement. The band is the dashboard; the icon beside the
 * notification bell carries the same words to every other page, so
 * somebody who never opens the dashboard still meets it once.
 *
 * Both live in this file deliberately. They have to say the same thing,
 * and the reliable way to keep two renderings of one message identical is
 * for them to share the component that renders it.
 *
 * Nothing here knows what it is saying. Title, body and button all arrive
 * from whatever listened.
 *
 * Coloured enough to be read and not enough to alarm: a tinted left edge
 * and a matching wash, rather than a saturated block. The first caller
 * tells a hosted customer their plan has limits and a bigger one exists,
 * which is worth noticing and not worth interrupting for — so it must not
 * look like an outage. Both palettes are declared per tone rather than
 * derived, so the dark variant is a deliberate colour rather than
 * whatever the light one happens to become.
 */
const TONES: Record<string, string> = {
    info: 'border-l-sky-500 bg-sky-50 dark:bg-sky-950/40',
    warning: 'border-l-amber-500 bg-amber-50 dark:bg-amber-950/40',
};

const DOT_TONES: Record<string, string> = {
    info: 'bg-sky-500',
    warning: 'bg-amber-500',
};

/** The words, and the button if there is one. Shared by both surfaces. */
function AnnouncementBody({ announcement, compact = false }: { announcement: Announcement; compact?: boolean }) {
    return (
        <>
            <div className="min-w-0">
                <p className="text-sm font-semibold">{announcement.title}</p>
                <p className="text-muted-foreground mt-1 text-sm">{announcement.body}</p>
            </div>

            {announcement.action_label && announcement.action_url && (
                <Button asChild variant="outline" size="sm" className={compact ? 'w-full bg-transparent' : 'shrink-0 bg-transparent'}>
                    {/* Always a new tab: the destination is outside this
                        installation, and taking somebody out of the app
                        they are working in is not what this should do. */}
                    <a href={announcement.action_url} target="_blank" rel="noopener noreferrer">
                        {announcement.action_label}
                        <ExternalLink className="size-3.5" />
                    </a>
                </Button>
            )}
        </>
    );
}

/** The dashboard band. */
export function AnnouncementBand({ announcement }: { announcement: Announcement }) {
    const tone = TONES[announcement.tone] ?? TONES.info;

    return (
        <div className={`mb-6 flex flex-col gap-3 rounded-lg border border-l-4 p-4 sm:flex-row sm:items-center sm:justify-between ${tone}`}>
            <AnnouncementBody announcement={announcement} />
        </div>
    );
}

/**
 * The header icon, beside the notification bell.
 *
 * Same shape as UpdateAvailableIcon next to it: absent entirely when
 * there is nothing to say, rather than a dead control. The dot marks it
 * without a count — there is only ever one of these, and "1" on a badge
 * would invite somebody to look for the second.
 */
export function AnnouncementIcon() {
    const { t } = useTranslation();
    const { announcement } = usePage<SharedData>().props;

    if (!announcement) {
        return null;
    }

    const dot = DOT_TONES[announcement.tone] ?? DOT_TONES.info;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label={announcement.title || t('Announcement')}>
                    <Megaphone className="size-5" />
                    <span className={`absolute top-0.5 right-0.5 size-2.5 rounded-full ${dot}`} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="flex w-80 flex-col gap-3 p-4">
                <AnnouncementBody announcement={announcement} compact />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
