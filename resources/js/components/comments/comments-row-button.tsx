import { MessageSquare, MessageSquareWarning } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The comment affordance on a staff library row: how many comments are on
 * the file, whether any of them are waiting for a decision, and a way
 * straight into the conversation.
 *
 * Always rendered while commenting is switched on, even at zero — it is
 * how a conversation gets started, not only how an existing one is found.
 *
 * `pending` is only ever non-zero for a moderator (see
 * VisibleCommentScope::pendingCountsFor), so the alert state cannot appear
 * to somebody who has no way to clear it. It swaps the icon rather than
 * adding a dot beside it: at this size a dot reads as decoration, and
 * "somebody is waiting on you" deserves to be legible at a glance.
 *
 * Staff UI is not themed, so this is a plain shared component with no
 * per-theme shell — unlike the portal and public surfaces.
 */
export function CommentsRowButton({ count, pending = 0, onOpen }: { count: number; pending?: number; onOpen: () => void }) {
    const { t } = useTranslation();

    const waiting = pending > 0;

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={onOpen}
            className={waiting ? 'gap-1 text-amber-600 hover:text-amber-700 dark:text-amber-500' : 'gap-1'}
            title={waiting ? t(':count waiting for approval', { count: pending }) : t(':count comments', { count })}
        >
            {waiting ? <MessageSquareWarning className="size-4" /> : <MessageSquare className="size-4" />}
            {count > 0 && <span className="text-xs tabular-nums">{count}</span>}
            <span className="sr-only">{t('Comments')}</span>
        </Button>
    );
}
