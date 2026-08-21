import { Eye } from 'lucide-react';
import { useState } from 'react';

import { FilePreviewBody, previewDialogWidth } from '@/components/file-preview-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { previewKind } from '@/lib/previews';

/**
 * The explicit way to open a file for a look, sitting with a row's other
 * actions — the twin of <DownloadAction>, and shaped like it on purpose:
 * a theme renders it, never the rule behind it.
 *
 * <FilePreviewDialog> already makes a thumbnail clickable, and that stays
 * the nicest way in for a photograph. It is not enough on its own: a PDF
 * or an audio file has no thumbnail, only a generic icon, and nothing
 * about a generic icon says "click me". This is what makes the affordance
 * findable rather than discoverable by accident.
 *
 * Two shapes, because the surfaces genuinely differ. A roomy list row has
 * space for a labelled button beside Download and reads better for it; a
 * dense table row or a grid card's footer does not, and gets the eye icon
 * instead. That is the one place a theme decides anything here — pass
 * `iconOnly`.
 *
 * Renders nothing when there is no preview to offer (`previewUrl` null,
 * or a type no browser plays), so a caller never needs its own guard.
 */
export function PreviewAction({
    previewUrl,
    mimeType,
    fileName,
    variant = 'outline',
    size = 'sm',
    iconOnly = false,
    className,
    iconClassName = 'size-4',
}: {
    previewUrl: string | null;
    mimeType: string;
    fileName: string;
    variant?: 'outline' | 'ghost' | 'default' | 'secondary';
    size?: 'sm' | 'lg' | 'default' | 'icon';
    /** Render the label for screen readers only, for dense rows and cards. */
    iconOnly?: boolean;
    className?: string;
    iconClassName?: string;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const kind = previewKind(mimeType);

    if (previewUrl === null || kind === null) {
        return null;
    }

    const label = t('Preview');

    return (
        <>
            <Button variant={variant} size={size} className={className} onClick={() => setOpen(true)} title={label}>
                <Eye className={iconClassName} />
                {iconOnly ? <span className="sr-only">{label}</span> : label}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className={previewDialogWidth(kind)}>
                    <DialogTitle className="sr-only">{t('Preview of :name', { name: fileName })}</DialogTitle>
                    {open && <FilePreviewBody previewUrl={previewUrl} mimeType={mimeType} fileName={fileName} />}
                </DialogContent>
            </Dialog>
        </>
    );
}
