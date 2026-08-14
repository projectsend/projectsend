import { MessageSquare } from 'lucide-react';
import { useState } from 'react';

import { CommentThread } from '@/components/comments/comment-thread';
import { type CommentsShellProps } from '@/components/comments/shells/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The default theme's comment shell: a plain dialog from the file row,
 * matching the neutral bordered look the rest of this theme uses.
 *
 * A shell owns the chrome and nothing else — see the `types` module for
 * the contract every shell shares.
 */
export function CommentsShellDefault({ fileId, fileName, endpoint, count = 0, unread = 0, inline = false, defaultOpen = false }: CommentsShellProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(defaultOpen);

    if (inline) {
        return (
            <section className="rounded-md border p-4">
                <h2 className="mb-3 text-sm font-medium">{t('Comments')}</h2>
                <CommentThread fileId={fileId} endpoint={endpoint} />
            </section>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="relative h-7 gap-1 px-2 text-xs">
                    <MessageSquare className="size-3.5" />
                    {count > 0 && count}
                    {unread > 0 && <span className="absolute top-0.5 right-0.5 size-1.5 rounded-full bg-amber-500" />}
                    <span className="sr-only">{t('Comments')}</span>
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{fileName}</DialogTitle>
                </DialogHeader>
                {/* Mounted only while open, so opening a row is what loads
                    its thread — a file list must not fire one request per
                    row on render. */}
                {open && <CommentThread fileId={fileId} endpoint={endpoint} />}
            </DialogContent>
        </Dialog>
    );
}
