<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Every cached rendition on the local 'files' disk, as one addressable
 * thing — so that "forget everything we rendered under the old rules"
 * has a single definition instead of a `deleteDirectory()` per rendition
 * scattered wherever a rendering setting can change.
 *
 * Always the local disk regardless of where the originals live: a
 * rendition is a derived artifact, never pushed to external storage.
 * See FileThumbnailController.
 */
class RenderedImageCache
{
    public function flush(): void
    {
        foreach (ImageRendition::cases() as $rendition) {
            try {
                Storage::disk('files')->deleteDirectory($rendition->directory());
            } catch (Throwable $exception) {
                // Everything rebuilds itself lazily on the next request, so
                // a storage hiccup here means "some stale images survive",
                // not "the settings change failed" — and the person who just
                // saved a setting should not get a 500 for it. Per rendition
                // rather than around the loop, so one failing directory does
                // not leave the others unflushed.
                Log::warning("Could not flush the {$rendition->value} cache: ".$exception->getMessage());
            }
        }
    }
}
