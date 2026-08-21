import { useState } from 'react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { previewKind, type PreviewKind } from '@/lib/previews';

/** Wide enough to read detail in; audio has nothing to look at. */
export function previewDialogWidth(kind: PreviewKind): string {
    return kind === 'audio' ? 'max-w-lg' : 'max-w-4xl';
}

/**
 * What a preview actually shows, in whichever element the file's type
 * calls for — shared by both ways of opening one (clicking the thumbnail,
 * via FilePreviewDialog; or the explicit Preview action, via
 * PreviewAction), so the players themselves exist once.
 *
 * Rendered only while the dialog is open, so closing it stops playback and
 * drops the connection rather than leaving a video buffering behind a
 * hidden panel. Callers enforce that by mounting this conditionally.
 */
export function FilePreviewBody({ previewUrl, mimeType, fileName }: { previewUrl: string; mimeType: string; fileName: string }) {
    const { t } = useTranslation();
    const kind = previewKind(mimeType);

    if (kind === 'image') {
        return <img src={previewUrl} alt={fileName} className="max-h-[80vh] w-full rounded object-contain" />;
    }

    if (kind === 'video') {
        return (
            // preload="metadata" so opening the dialog costs a few
            // kilobytes and a duration, not the file — the rest arrives in
            // ranges once someone presses play.
            <video src={previewUrl} controls preload="metadata" className="max-h-[80vh] w-full rounded bg-black">
                {t('Your browser cannot play this file. Download it to view it.')}
            </video>
        );
    }

    if (kind === 'audio') {
        return (
            <audio src={previewUrl} controls preload="metadata" className="w-full">
                {t('Your browser cannot play this file. Download it to view it.')}
            </audio>
        );
    }

    if (kind === 'pdf') {
        return (
            // No `sandbox` attribute, and that is deliberate: Chrome
            // refuses to run its PDF viewer in a sandboxed frame at all
            // (ERR_BLOCKED_BY_CLIENT, with or without allow-same-origin),
            // so adding one does not harden this — it removes the feature.
            // Nor would it add anything if it worked: /protected-files/
            // already answers with `Content-Security-Policy: sandbox;
            // default-src 'none'`, which measurably does put a <video>
            // frame in an opaque origin — and which Chrome exempts its PDF
            // viewer from either way.
            //
            // What actually holds here is PreviewKind: only
            // `application/pdf` reaches this element, never text/html or
            // image/svg+xml, which are the types that would really execute
            // script against this origin. A PDF's own JavaScript runs
            // inside the browser's PDF sandbox, with no DOM and no cookies
            // — the same footing every site that displays a PDF stands on.
            <iframe src={previewUrl} title={t('Preview of :name', { name: fileName })} className="h-[80vh] w-full rounded border" />
        );
    }

    return null;
}

/**
 * A thumbnail (or a row's icon) turned into a click target that opens the
 * file for a look.
 *
 * This is the *implicit* way in — the picture you can obviously click.
 * The explicit one is <PreviewAction>, the labelled button or eye icon
 * that sits with a row's other actions; a surface generally offers both,
 * because a photograph invites a click and a PDF icon does not.
 *
 * Takes a URL rather than an id because the same dialog serves three
 * surfaces that address a file differently: staff and the client portal
 * build route('files.preview', id), while a public page is handed a
 * server-decided URL. Null means this viewer is not offered a preview at
 * all — the setting is off, or the server declined to advertise one — and
 * is treated exactly like an unpreviewable type.
 *
 * `children` is whatever the calling surface already draws — a thumbnail
 * for an image, that theme's own icon for anything else — so a theme keeps
 * its own look and only gains a click target. A type with no inline view
 * renders `children` untouched and adds nothing, so a caller whose
 * fallback looks the same either way can wrap unconditionally; one whose
 * fallback needs different markup (a grid tile that has to keep its own
 * box) branches on isPreviewable() itself.
 */
export function FilePreviewDialog({
    previewUrl,
    mimeType,
    fileName,
    className,
    children,
}: {
    previewUrl: string | null;
    mimeType: string;
    fileName: string;
    className?: string;
    children: React.ReactNode;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const kind = previewKind(mimeType);

    if (previewUrl === null || kind === null) {
        return <>{children}</>;
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                // Named for a screen reader, because what it wraps is a
                // decorative thumbnail (alt="") or a bare icon — without
                // this it announces as an unlabelled button.
                aria-label={t('Preview :name', { name: fileName })}
                title={t('Preview :name', { name: fileName })}
                className={cn(
                    'block cursor-pointer appearance-none border-0 bg-transparent p-0 text-left transition-opacity hover:opacity-80',
                    className,
                )}
            >
                {children}
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className={previewDialogWidth(kind)}>
                    <DialogTitle className="sr-only">{t('Preview of :name', { name: fileName })}</DialogTitle>
                    {open && <FilePreviewBody previewUrl={previewUrl} mimeType={mimeType} fileName={fileName} />}
                </DialogContent>
            </Dialog>
        </>
    );
}
