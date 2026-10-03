import { router } from '@inertiajs/react';
import { useState } from 'react';
import ReactCrop, { type PercentCrop } from 'react-image-crop';
import 'react-image-crop/dist/ReactCrop.css';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

export interface LogoCropBox {
    x: number;
    y: number;
    width: number;
    height: number;
}

interface LogoCropDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The uploaded image, never a previous crop of it. */
    sourceUrl: string;
    /** The last crop, in the upload's pixels, or null to start from the whole image. */
    savedCrop: LogoCropBox | null;
}

const WHOLE: PercentCrop = { unit: '%', x: 0, y: 0, width: 100, height: 100 };

/**
 * Draw a box on the uploaded logo and keep only that part of it.
 *
 * The box is held in percent while it is being drawn, so it survives the
 * dialog resizing, and turned into the upload's own pixels only when it is
 * saved. The server cuts the new file from the upload: nothing is cropped
 * in the browser.
 *
 * `image-orientation: none` shows the stored pixels exactly as the server
 * reads them. A browser would otherwise rotate a phone photo by its
 * orientation tag, the server cannot, and the box would land on the wrong
 * part of the picture.
 */
export default function LogoCropDialog({ open, onOpenChange, sourceUrl, savedCrop }: LogoCropDialogProps) {
    const { t } = useTranslation();
    const [crop, setCrop] = useState<PercentCrop>(WHOLE);
    const [natural, setNatural] = useState<{ width: number; height: number } | null>(null);
    const [error, setError] = useState<string | undefined>();
    const [saving, setSaving] = useState(false);

    const onImageLoad = (image: HTMLImageElement) => {
        const width = image.naturalWidth;
        const height = image.naturalHeight;

        setNatural({ width, height });
        setError(undefined);
        setCrop(
            savedCrop === null
                ? WHOLE
                : {
                      unit: '%',
                      x: (savedCrop.x / width) * 100,
                      y: (savedCrop.y / height) * 100,
                      width: (savedCrop.width / width) * 100,
                      height: (savedCrop.height / height) * 100,
                  },
        );
    };

    const toPixels = (box: PercentCrop, size: { width: number; height: number }): LogoCropBox => {
        const x = Math.max(0, Math.round((box.x / 100) * size.width));
        const y = Math.max(0, Math.round((box.y / 100) * size.height));

        return {
            x,
            y,
            width: Math.max(1, Math.min(size.width - x, Math.round((box.width / 100) * size.width))),
            height: Math.max(1, Math.min(size.height - y, Math.round((box.height / 100) * size.height))),
        };
    };

    const save = () => {
        if (natural === null || crop.width === 0 || crop.height === 0) {
            return;
        }

        setSaving(true);

        router.patch(
            route('branding.logo.crop'),
            { ...toPixels(crop, natural) },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errors) => setError(errors.logo ?? errors.width ?? errors.x ?? Object.values(errors)[0]),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('Crop logo')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Drag the box and its corners to choose the part of the image to show. The uploaded image is kept, so you can change this later.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="bg-muted/40 flex justify-center rounded border p-2">
                    {/* The height limit goes on the crop wrapper: the library's
                        stylesheet gives the image `max-height: inherit`, so a
                        limit on the image itself is overridden, and a tall logo
                        would push the bottom handles out of reach. */}
                    <ReactCrop
                        crop={crop}
                        onChange={(_, percent) => setCrop(percent)}
                        keepSelection
                        minWidth={8}
                        minHeight={8}
                        style={{ maxHeight: '56vh' }}
                    >
                        <img src={sourceUrl} alt="" onLoad={(e) => onImageLoad(e.currentTarget)} style={{ imageOrientation: 'none' }} />
                    </ReactCrop>
                </div>

                {natural !== null && (
                    <p className="text-muted-foreground text-sm">
                        {(() => {
                            const box = toPixels(crop, natural);

                            return t(':width × :height pixels', { width: String(box.width), height: String(box.height) });
                        })()}
                    </p>
                )}

                <InputError message={error} />

                <DialogFooter>
                    <Button variant="outline" onClick={() => setCrop(WHOLE)} disabled={saving}>
                        {t('Select all')}
                    </Button>
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                        {t('Cancel')}
                    </Button>
                    <Button onClick={save} disabled={saving || natural === null}>
                        {t('Save crop')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
