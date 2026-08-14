<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails\Events;

/**
 * "Whatever RenderingImage listeners do now, they would do differently"
 * — dispatched by whoever changes such a setting, so core can throw away
 * the renditions it cached under the old rules.
 *
 * The return half of the RenderingImage seam. Rendered images are cached
 * on disk and never regenerated once written (see
 * FileThumbnailController), so a package that changes how they render
 * has no way to make its own change visible: every already-viewed file
 * keeps serving the stale bytes, potentially forever.
 *
 * It carries no payload, on purpose. That lets a package dispatch it by
 * *string* class name without constructing a host class it cannot
 * reference — the mirror image of how packages listen:
 *
 *     Event::dispatch('App\Modules\Files\Thumbnails\Events\ImageRenderingChanged');
 *
 * With no host present that dispatch is an inert no-op, which is what
 * keeps the package's own test suite runnable standalone.
 *
 * Flushing every rendition of every file is the right blunt instrument
 * here: rendering settings are global, they change rarely and
 * deliberately, and a rendition is a derived artifact that costs one
 * lazy regeneration to rebuild.
 */
final class ImageRenderingChanged {}
