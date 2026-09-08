import { ExternalLink } from 'lucide-react';

import { Button } from '@/components/ui/button';

export interface DashboardCallout {
    title: string;
    body: string;
    action_label: string | null;
    action_url: string | null;
    tone: 'info' | 'warning' | string;
}

/**
 * One band across the top of the dashboard, whose words come from
 * whatever asked for it — see ResolvingDashboardCallout. Nothing here
 * knows what it is saying, which is the point.
 *
 * Coloured enough to be read and not enough to alarm: a tinted left edge
 * and a matching wash, rather than a saturated block. The first caller
 * tells a hosted customer their plan has limits and a bigger one exists,
 * which is a thing worth noticing and not a thing worth interrupting for
 * — so it must not look like an outage.
 *
 * Both palettes are declared per tone rather than derived, so the dark
 * variant is a deliberate colour rather than whatever the light one
 * happens to become.
 */
const TONES: Record<string, string> = {
    info: 'border-l-sky-500 bg-sky-50 dark:bg-sky-950/40',
    warning: 'border-l-amber-500 bg-amber-50 dark:bg-amber-950/40',
};

export function DashboardCalloutBand({ callout }: { callout: DashboardCallout }) {
    const tone = TONES[callout.tone] ?? TONES.info;

    return (
        <div className={`mb-6 flex flex-col gap-3 rounded-lg border border-l-4 p-4 sm:flex-row sm:items-center sm:justify-between ${tone}`}>
            <div className="min-w-0">
                <p className="text-sm font-semibold">{callout.title}</p>
                <p className="text-muted-foreground mt-1 text-sm">{callout.body}</p>
            </div>

            {callout.action_label && callout.action_url && (
                <Button asChild variant="outline" size="sm" className="shrink-0 bg-transparent">
                    {/* Always a new tab: the destination is outside this
                        installation, and taking somebody out of the app
                        they are working in is not what a dashboard link
                        should do. */}
                    <a href={callout.action_url} target="_blank" rel="noopener noreferrer">
                        {callout.action_label}
                        <ExternalLink className="size-3.5" />
                    </a>
                </Button>
            )}
        </div>
    );
}
