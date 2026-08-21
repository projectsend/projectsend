<?php

declare(strict_types=1);

namespace App\Modules\Files\Preview;

use App\Modules\Files\Thumbnails\ThumbnailGenerator;

/**
 * What kind of inline view, if any, a stored file gets — the single
 * answer to "may these bytes be served inline, and what element renders
 * them?", shared by FileThumbnailController::preview (signed in) and
 * PublicGroupsController::preview (anonymous).
 *
 * SECURITY: this is an allowlist, and it is the boundary. Preview serves
 * a file's own bytes inline, from this app's origin, labelled with the
 * mime type stored on the row — so anything a browser executes script
 * from would be same-origin script execution with the viewer's session.
 * Never add text/html, image/svg+xml, or any other document type, and
 * never derive this list from Setting::AllowedUploadExtensions: that
 * setting matches on the *extension* while mime_type is sniffed from the
 * *bytes* (ChunkedUploadsController::complete), so a .txt holding HTML is
 * stored as text/html and would arrive here looking allowed.
 *
 * Deliberately narrower than "files a browser might cope with": every
 * type below is one every current browser decodes natively. Formats like
 * video/quicktime, video/x-msvideo and video/x-matroska are left out
 * because an embedded player for them shows a black rectangle. They
 * upload and download exactly as before — only the inline view is
 * withheld.
 *
 * Distinct from ThumbnailGenerator::SUPPORTED_MIME_TYPES, which answers a
 * narrower question: which types this app can *decode and re-encode*
 * itself, and therefore has renditions, a cache and a watermark hook for.
 * Image delegates to it rather than restating it, so the two cannot drift.
 */
enum PreviewKind: string
{
    /** Rendered with <img>; the only kind with thumbnails and renditions. */
    case Image = 'image';

    /** Rendered with <video controls>. */
    case Video = 'video';

    /** Rendered with <audio controls>. */
    case Audio = 'audio';

    /** Rendered in a sandboxed <iframe>, by the browser's own viewer. */
    case Pdf = 'pdf';

    /** @var list<string> */
    private const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'video/ogg',
    ];

    /**
     * More spellings than there are formats: the mime type is whatever
     * finfo made of the bytes, and it is not consistent across systems —
     * a .wav is audio/x-wav on one box and audio/vnd.wave on another, and
     * an .m4a can come back as audio/mp4 or audio/x-m4a.
     *
     * @var list<string>
     */
    private const AUDIO_MIME_TYPES = [
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

    public static function forMime(string $mimeType): ?self
    {
        if (ThumbnailGenerator::supports($mimeType)) {
            return self::Image;
        }

        if (in_array($mimeType, self::VIDEO_MIME_TYPES, true)) {
            return self::Video;
        }

        if (in_array($mimeType, self::AUDIO_MIME_TYPES, true)) {
            return self::Audio;
        }

        if ($mimeType === 'application/pdf') {
            return self::Pdf;
        }

        return null;
    }

    public static function supports(string $mimeType): bool
    {
        return self::forMime($mimeType) !== null;
    }
}
