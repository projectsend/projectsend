// Mirrors App\Modules\Files\Preview\PreviewKind on the backend — which
// element, if any, shows this file inline. Read that enum's docblock
// before adding a type: this is a security allowlist on the server, and
// widening it here only produces a player pointed at a URL that 404s.
//
// Distinct from isThumbnailable() in ./thumbnails, which answers the
// narrower question of whether there is a thumbnail image to put in a
// listing row. Every thumbnailable file is previewable; a PDF is
// previewable with no thumbnail to click.
export type PreviewKind = 'image' | 'video' | 'audio' | 'pdf';

const VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];

const AUDIO_MIME_TYPES = [
    'audio/mpeg',
    'audio/wav',
    'audio/x-wav',
    'audio/vnd.wave',
    'audio/ogg',
    'audio/webm',
    'audio/mp4',
    'audio/x-m4a',
    'audio/aac',
    'audio/flac',
    'audio/x-flac',
];

const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

export function previewKind(mimeType: string): PreviewKind | null {
    if (IMAGE_MIME_TYPES.includes(mimeType)) {
        return 'image';
    }

    if (VIDEO_MIME_TYPES.includes(mimeType)) {
        return 'video';
    }

    if (AUDIO_MIME_TYPES.includes(mimeType)) {
        return 'audio';
    }

    if (mimeType === 'application/pdf') {
        return 'pdf';
    }

    return null;
}

export function isPreviewable(mimeType: string): boolean {
    return previewKind(mimeType) !== null;
}
