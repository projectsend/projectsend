import { MessageSquare } from 'lucide-react';
import { useState } from 'react';

import { CommentThread } from '@/components/comments/comment-thread';
import { type CommentsShellProps } from '@/components/comments/shells/types';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The compact theme's comment shell: a panel docked to the bottom of the
 * viewport, like the detail pane under a spreadsheet.
 *
 * Square corners, hairline borders, `text-xs`, monochrome — the same
 * deliberate spreadsheet language as this theme's table and its filter
 * toolbar (docs/theming-files-checklist.md, "Visual identity per theme").
 * It docks rather than floating because this theme's rows live in a real
 * `<table>`, where an expanding block inside a cell would break the column
 * grid that is the entire point of the look; a centred modal, meanwhile,
 * would be indistinguishable from `default`'s.
 */
export function CommentsShellCompact({ fileId, fileName, endpoint, count = 0, unread = 0, inline = false, defaultOpen = false }: CommentsShellProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(defaultOpen);

    if (inline) {
        return (
            <section className="border border-neutral-300 text-xs dark:border-neutral-700">
                <h2 className="bg-neutral-100 px-2 py-1 text-xs font-medium tracking-wide text-neutral-500 uppercase dark:bg-neutral-900 dark:text-neutral-400">
                    {t('Comments')}
                </h2>
                <div className="border-t border-neutral-300 p-2 dark:border-neutral-700">
                    <CommentThread fileId={fileId} endpoint={endpoint} dense />
                </div>
            </section>
        );
    }

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="ghost" size="sm" className="relative size-6 gap-0.5 rounded-none p-0 text-[10px] text-neutral-500">
                    <MessageSquare className="size-3.5" />
                    {count > 0 && count}
                    {unread > 0 && <span className="absolute top-0 right-0 size-1.5 rounded-full bg-neutral-500 dark:bg-neutral-400" />}
                    <span className="sr-only">{t('Comments')}</span>
                </Button>
            </SheetTrigger>
            <SheetContent
                side="bottom"
                className="max-h-[60vh] overflow-y-auto rounded-none border-t border-neutral-300 text-xs dark:border-neutral-700"
            >
                <SheetHeader className="px-3 py-2">
                    <SheetTitle className="text-xs font-medium tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
                        {t('Comments')} · {fileName}
                    </SheetTitle>
                </SheetHeader>
                <div className="px-3 pb-4">{open && <CommentThread fileId={fileId} endpoint={endpoint} dense />}</div>
            </SheetContent>
        </Sheet>
    );
}
