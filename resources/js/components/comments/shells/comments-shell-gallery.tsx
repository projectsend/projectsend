import { MessageSquare } from 'lucide-react';
import { useState } from 'react';

import { CommentThread } from '@/components/comments/comment-thread';
import { type CommentsShellProps } from '@/components/comments/shells/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The gallery theme's comment shell: a centred, rounded dialog in violet
 * — a lightbox panel over the thumbnail grid, the same shape this theme
 * already uses for filters.
 *
 * A grid has no row to expand in place, which is exactly why this theme
 * reaches for an overlay where `compact` reaches for a collapsible
 * (docs/theming-files-checklist.md, "Visual identity per theme").
 */
export function CommentsShellGallery({ fileId, fileName, endpoint, count = 0, unread = 0, inline = false, defaultOpen = false }: CommentsShellProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(defaultOpen);

    if (inline) {
        return (
            <section className="rounded-xl border border-violet-200 p-4 dark:border-violet-900">
                <h2 className="mb-3 text-sm font-medium text-violet-600">{t('Comments')}</h2>
                <CommentThread fileId={fileId} endpoint={endpoint} />
            </section>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="relative h-7 gap-1 rounded-xl px-2 text-xs text-violet-600 hover:text-violet-700">
                    <MessageSquare className="size-3.5" />
                    {count > 0 && count}
                    {unread > 0 && <span className="absolute top-0.5 right-0.5 size-1.5 rounded-full bg-violet-600" />}
                    <span className="sr-only">{t('Comments')}</span>
                </Button>
            </DialogTrigger>
            <DialogContent className="rounded-xl sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="text-violet-600">{fileName}</DialogTitle>
                </DialogHeader>
                {open && <CommentThread fileId={fileId} endpoint={endpoint} />}
            </DialogContent>
        </Dialog>
    );
}
