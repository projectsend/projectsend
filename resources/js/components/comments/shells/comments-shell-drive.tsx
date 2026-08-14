import { MessageSquare } from 'lucide-react';
import { useState } from 'react';

import { CommentThread } from '@/components/comments/comment-thread';
import { type CommentsShellProps } from '@/components/comments/shells/types';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The drive theme's comment shell: a right-side sheet in this theme's
 * blue, matching how it already reaches filters and how Drive itself
 * slides panels in from the edge.
 *
 * Generous spacing and a full-height panel rather than a small modal —
 * the same "spacious, colourful" language as the rest of the theme
 * (docs/theming-files-checklist.md, "Visual identity per theme").
 */
export function CommentsShellDrive({ fileId, fileName, endpoint, count = 0, unread = 0, inline = false, defaultOpen = false }: CommentsShellProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(defaultOpen);

    if (inline) {
        // No box: full-bleed on the page background, like this theme's rows.
        return (
            <section className="py-4">
                <h2 className="mb-3 text-xs font-medium tracking-wide text-blue-600 uppercase">{t('Comments')}</h2>
                <CommentThread fileId={fileId} endpoint={endpoint} />
            </section>
        );
    }

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="relative gap-1.5 text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950"
                >
                    <MessageSquare className="size-4" />
                    {count > 0 && <span className="text-sm">{count}</span>}
                    {unread > 0 && <span className="absolute top-1 right-1 size-1.5 rounded-full bg-blue-600" />}
                    <span className="sr-only">{t('Comments')}</span>
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle className="text-blue-600">{fileName}</SheetTitle>
                </SheetHeader>
                <div className="mt-4 px-4 pb-6">{open && <CommentThread fileId={fileId} endpoint={endpoint} />}</div>
            </SheetContent>
        </Sheet>
    );
}
