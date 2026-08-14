import { useState } from 'react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export function FilePreviewDialog({
    fileId,
    fileName,
    className,
    children,
}: {
    fileId: number;
    fileName: string;
    className?: string;
    children: React.ReactNode;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn('block appearance-none border-0 bg-transparent p-0 text-left', className)}
            >
                {children}
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-3xl">
                    <DialogTitle className="sr-only">{t('Preview of :name', { name: fileName })}</DialogTitle>
                    {open && <img src={route('files.preview', fileId)} alt={fileName} className="max-h-[80vh] w-full rounded object-contain" />}
                </DialogContent>
            </Dialog>
        </>
    );
}
