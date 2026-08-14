import { Loader2 } from 'lucide-react';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { type ZipDownloadStatus } from '@/hooks/use-zip-download';

export function ZipDownloadDialog({ status, error, onClose }: { status: ZipDownloadStatus; error: string | null; onClose: () => void }) {
    const { t } = useTranslation();

    return (
        <Dialog open={status !== 'idle'} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Download as zip')}</DialogTitle>
                </DialogHeader>
                {status === 'preparing' && (
                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Loader2 className="size-4 animate-spin" />
                        {t('Preparing your download…')}
                    </p>
                )}
                {status === 'ready' && <p className="text-sm">{t('Your download should start automatically.')}</p>}
                {status === 'failed' && <p className="text-destructive text-sm">{error ?? t('Something went wrong. Please try again.')}</p>}
            </DialogContent>
        </Dialog>
    );
}
