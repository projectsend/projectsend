import { type SharedData } from '@/types';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { UpdateInstructions } from '@/components/update-instructions';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

type UpdateNotice = NonNullable<SharedData['update_notice']>;

/**
 * The single "what's new" dialog for an available update — shared by
 * the topbar's persistent icon (UpdateAvailableIcon) and the dashboard
 * System card's "View release notes" link, so both surfaces show
 * exactly the same content instead of one linking straight to GitHub.
 */
export function UpdateReleaseDialog({ notice, open, onOpenChange }: { notice: UpdateNotice; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { t } = useTranslation();
    const { date } = useFormatDate();

    const publishedDate = date(notice.published_at);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('ProjectSend :version is available', { version: notice.version })}</DialogTitle>
                </DialogHeader>

                <div className="space-y-3 text-sm">
                    {notice.title && notice.title !== notice.version && <p className="font-medium">{notice.title}</p>}
                    {publishedDate !== '' && <p className="text-muted-foreground">{t('Published :date', { date: publishedDate })}</p>}

                    {notice.notes && <div className="bg-muted max-h-72 overflow-y-auto rounded-md p-3 whitespace-pre-wrap">{notice.notes}</div>}

                    <div className="border-t pt-3">
                        <UpdateInstructions kind={notice.install_kind} />
                    </div>

                    {notice.url && (
                        <a href={notice.url} target="_blank" rel="noreferrer" className="inline-block underline hover:no-underline">
                            {t('View release notes on GitHub')}
                        </a>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
