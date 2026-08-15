import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { MessagesSquare } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The invitation to the community, on both of the pages that greet an
 * administrator — after an install and after an update.
 *
 * One component because it was two identical copies within an hour of
 * each other, and the pair would have drifted the first time either page
 * was touched alone. Both are the same offer made at the same kind of
 * moment, so they should keep looking like it.
 *
 * Carried on `accent` rather than `muted` so it reads as a feature
 * instead of a footnote. That token is the brand colour at surface
 * strength and already has a dark-mode counterpart, so this stays legible
 * in both without a hardcoded purple — and follows the palette on an
 * installation whose branding replaces ours.
 */
export default function DiscordInvitation() {
    const { t } = useTranslation();
    const { links } = usePage<SharedData>().props;

    return (
        <div className="bg-accent border-primary/20 flex flex-col items-center gap-4 rounded-lg border p-6 text-center">
            <MessagesSquare className="text-primary size-8" strokeWidth={1.5} />

            <div className="space-y-1">
                <p className="text-accent-foreground font-medium">{t('Come and say hello')}</p>
                <p className="text-accent-foreground/80 text-sm">
                    {t(
                        'ProjectSend has a Discord: release news, help when something is not behaving, and other people running the same software. We are in it too.',
                    )}
                </p>
            </div>

            {/* Solid rather than outline: an outline button's hover state is
                this exact background colour, so on this card it would
                disappear under the cursor. */}
            <Button asChild>
                <a href={links.discord} target="_blank" rel="noreferrer">
                    {t('Join the Discord')}
                </a>
            </Button>
        </div>
    );
}
