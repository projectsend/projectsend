<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails;

use App\Models\User;

/**
 * Who a rendered image is being made for.
 *
 * Renditions are cached on disk and reused, so anything that makes one
 * viewer's copy differ from another's has to be part of the cache key or
 * the two would serve each other's bytes. This is that key, and it is
 * deliberately coarse: the only distinction the app makes is between the
 * people who run this installation and everyone they share files with.
 *
 * Core attaches no meaning to the difference beyond "cache them apart"
 * and "an external viewer never gets the original where a rendering was
 * asked for" — what actually differs is decided by RenderingImage
 * listeners (the cloud-modules watermark is the first, and marks only
 * `External`).
 */
enum ImageAudience: string
{
    /** Staff of this installation: /files, the file editor, anything behind the admin sidebar. */
    case Staff = 'staff';

    /** Clients in the portal, and anonymous visitors on a public listing. */
    case External = 'external';

    /**
     * A viewer is external unless they are demonstrably staff — including
     * when there is no viewer at all, which is the anonymous public
     * listing. Failing this open would mean a signed-out visitor getting
     * the internal variant of every image.
     */
    public static function forViewer(?User $viewer): self
    {
        return $viewer?->isStaff() === true ? self::Staff : self::External;
    }

    /**
     * Where this audience's cached files live inside a rendition's own
     * directory.
     *
     * Staff keeps the bare path this app has always used for thumbnails,
     * rather than both audiences moving into a subdirectory of their own:
     * every thumbnail cached before the split was generated with no
     * listener altering it, which is exactly what a staff thumbnail still
     * is. A symmetric layout would have been tidier and would have
     * orphaned every one of those files on the disk, with nothing left
     * deriving their paths to clean them up.
     */
    public function pathPrefix(): string
    {
        return match ($this) {
            self::Staff => '',
            self::External => 'external/',
        };
    }
}
