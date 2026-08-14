<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails\Events;

use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use claviska\SimpleImage;

/**
 * The extension point through which a package alters what a rendered
 * image looks like — a watermark, today, from cloud-modules.
 *
 * Dispatched by ThumbnailGenerator once per rendered image, after the
 * source has been oriented and scaled into the rendition's box but
 * *before* it is encoded to disk. A listener mutates `$image` in place;
 * with no listener registered the image is written exactly as core
 * produced it, which is the documented default.
 *
 * Passing the in-memory SimpleImage rather than the written file's path
 * is deliberate: a listener that had to re-open, edit and re-save the
 * finished file would put every watermarked JPEG through a second lossy
 * encode for no reason.
 *
 * A package listens by *string* class name, never by importing this
 * class, so it stays buildable and testable with no host application
 * present — see docs/extension-points-architecture.md:
 *
 *     Event::listen('App\Modules\Files\Thumbnails\Events\RenderingImage', ...);
 *
 * Listeners run inside rendering, so a listener that throws fails the
 * request that asked for the image. Core does not swallow that: a
 * silently-undecorated image is worse than a visible error, and "my
 * optional decoration must not break the page" is the listener's own
 * problem to handle, close to the code that knows which failures are
 * tolerable. See ThumbnailWatermarker in cloud-modules.
 */
final class RenderingImage
{
    public function __construct(
        public readonly SimpleImage $image,
        public readonly string $mimeType,
        /**
         * Who this one is for. Core caches the audiences apart, so a
         * listener is free to draw them differently — and must consult
         * this rather than assuming every image is alike. The watermark,
         * for instance, marks what clients and anonymous visitors see
         * and leaves the staff file manager plain.
         */
        public readonly ImageAudience $audience,
        /**
         * Which size of copy this is — a listing icon or a full look at
         * the picture. Also part of the cache key, so a listener may
         * treat them differently; the watermark scales itself relative
         * to the canvas and so needs no special case.
         */
        public readonly ImageRendition $rendition,
    ) {}
}
