<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails;

/**
 * The bounded, cached copies this app makes of an uploaded image.
 *
 * Both are *renderings*, not the file: the app decodes the original,
 * scales it into a box, lets RenderingImage listeners decorate it, and
 * serves that. What separates them is only how big the box is and what
 * they are for — a row icon versus a look at the picture.
 *
 * The original itself is never a rendition. It is what `download`
 * returns, untouched, and what staff get from `preview`.
 */
enum ImageRendition: string
{
    /** The icon beside a filename in a listing. */
    case Thumbnail = 'thumbnail';

    /** Opening a file to look at it, without downloading it. */
    case Preview = 'preview';

    /**
     * The longest side, in pixels, this rendition is scaled to fit
     * within. Only ever scales down — a small original stays its own
     * size rather than being blown up.
     */
    public function maxDimension(): int
    {
        return match ($this) {
            self::Thumbnail => 300,
            // Big enough to fill a laptop screen and read detail in,
            // small enough that rendering and caching one per file is
            // not a second copy of the library. Anyone who wants the
            // actual pixels downloads the file.
            self::Preview => 1600,
        };
    }

    /** Where this rendition's cached files live on the local 'files' disk. */
    public function directory(): string
    {
        return match ($this) {
            self::Thumbnail => 'thumbnails',
            self::Preview => 'previews',
        };
    }
}
