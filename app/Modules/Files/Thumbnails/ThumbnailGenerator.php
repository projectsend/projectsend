<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails;

use App\Modules\Files\Thumbnails\Events\RenderingImage;
use claviska\SimpleImage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Wraps claviska/simpleimage — the same GD-backed library v1 uses — to
 * produce a bounded rendition of an uploaded image. bestFit() (not
 * thumbnail()) so a portrait or landscape original keeps its aspect ratio
 * inside the box instead of being cropped to a square, and never scales
 * up, so a small original is served at its own size.
 *
 * Named for the thumbnail it originally only made; it produces every
 * ImageRendition now, previews included.
 */
class ThumbnailGenerator
{
    /**
     * Guards against decompression-bomb-style images: a huge pixel count
     * can consume excessive memory/CPU to decode even from a small file
     * on disk.
     */
    private const MAX_SOURCE_MEGAPIXELS = 40;

    /**
     * Raster formats SimpleImage/GD can decode. SVGs are already
     * vector/small, so the frontend renders the original file directly
     * instead of asking for a thumbnail.
     *
     * @var list<string>
     */
    public const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public static function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    /**
     * The path one audience's cached copy of one rendition of a file
     * lives (or would live) at on the local 'files' disk, or null when
     * the mime type has no rendition at all — the single authoritative
     * definition shared by whatever generates it (FileThumbnailController,
     * PublicGroupsController) and whatever cleans it up (FileDiskCleanup).
     *
     * Keyed on both because a RenderingImage listener may draw them
     * differently; see ImageAudience and ImageRendition.
     */
    public static function pathFor(int $fileId, string $mimeType, ImageAudience $audience, ImageRendition $rendition): ?string
    {
        if (! self::supports($mimeType)) {
            return null;
        }

        return $rendition->directory().'/'.$audience->pathPrefix().$fileId.'.'.self::extensionFor($mimeType);
    }

    /**
     * Every cached rendition of one file, for every audience — what
     * deleting the file has to remove. Derived from the two enums rather
     * than spelled out, so adding a rendition or an audience cannot
     * leave bytes behind.
     *
     * @return list<string>
     */
    public static function pathsFor(int $fileId, string $mimeType): array
    {
        $paths = [];

        foreach (ImageRendition::cases() as $rendition) {
            foreach (ImageAudience::cases() as $audience) {
                $path = self::pathFor($fileId, $mimeType, $audience, $rendition);

                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    private static function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }

    /**
     * How long a render may hold the lock before another request is
     * entitled to assume it died. Generous: a 40-megapixel decode is a
     * second or two, and an external source is copied local first.
     */
    private const LOCK_SECONDS = 120;

    /**
     * How long to wait for the request that got there first.
     *
     * Waiting costs an idle worker — about 35 MB. Rendering costs that
     * plus four bytes per source pixel, up to 160 MB at the megapixel
     * ceiling. Waiting is the cheap option by an order of magnitude,
     * which is the whole reason this exists.
     *
     * Configurable because the right number depends on how long a decode
     * takes here, and that is a property of the machine rather than of
     * the application: a small VPS reading a large source off a slow disk
     * wants longer than this, and nothing in the code can know that.
     */
    private const DEFAULT_LOCK_WAIT_SECONDS = 15;

    /**
     * Render one image, once, however many requests ask at the same time.
     *
     * **Why the lock.** Renditions are generated on demand and cached by
     * existence, and nothing between the callers stopped two requests
     * rendering the same image at once. The atomic rename below settles
     * which file survives — it never stopped both from decoding. So N
     * concurrent requests for one cold rendition were N full-size decodes,
     * each holding four bytes per source pixel.
     *
     * That is not an attack. A public listing emits a thumbnail URL per
     * file, a browser opens six or more connections at once, and the first
     * visit to a gallery of ordinary camera images is six simultaneous
     * decodes on a container sized for one. It kills the container, and
     * because a killed render writes nothing, the cache never warms: the
     * page dies again on the next visit. `PublicGroupsController` reaches
     * here with no account at all.
     *
     * **Why waiting rather than refusing.** The request that waits holds
     * an idle worker. The request that renders holds a worker plus the
     * whole source bitmap. Six waiters cost what one renderer costs, so
     * blocking is the cheap answer even when it looks like the slow one.
     *
     * **Why the re-check after acquiring.** The winner has finished by the
     * time a waiter gets in, so the file it was waiting for is already
     * there. Re-reading is what turns a wait into a cache hit rather than
     * a second render of the same image.
     */
    public function generate(
        string $sourcePath,
        string $destinationPath,
        string $mimeType,
        ImageAudience $audience,
        ImageRendition $rendition,
    ): void {
        // Keyed on the destination, which already encodes the file, the
        // audience and the rendition — two requests collide here exactly
        // when they would have written the same path.
        $lock = Cache::lock('rendition:'.sha1($destinationPath), self::LOCK_SECONDS);

        try {
            $lock->block($this->lockWaitSeconds());
        } catch (LockTimeoutException) {
            // Deliberately not rendering anyway. Falling through on
            // timeout would reinstate exactly the pile-on this exists to
            // stop, at the moment the system is already struggling — one
            // failed thumbnail is a better outcome than a container that
            // dies and takes the warm cache with it.
            throw new RuntimeException('Timed out waiting for another request to render this image.');
        }

        try {
            // Somebody else rendered it while we waited. An empty file is
            // not a rendition — same rule the callers apply, and the same
            // reason: nothing invalidates one once it is cached.
            if (is_file($destinationPath) && filesize($destinationPath) > 0) {
                return;
            }

            $this->render($sourcePath, $destinationPath, $mimeType, $audience, $rendition);
        } finally {
            $lock->release();
        }
    }

    /**
     * Clamped to at least a second: a zero would make every concurrent
     * request fail instead of waiting, which is the opposite of the point
     * and exactly what a stray empty environment variable produces.
     */
    private function lockWaitSeconds(): int
    {
        $configured = config('projectsend.rendition_lock_wait_seconds');

        return max(1, is_numeric($configured) ? (int) $configured : self::DEFAULT_LOCK_WAIT_SECONDS);
    }

    private function render(
        string $sourcePath,
        string $destinationPath,
        string $mimeType,
        ImageAudience $audience,
        ImageRendition $rendition,
    ): void {
        $dimensions = @getimagesize($sourcePath);

        if ($dimensions === false) {
            throw new RuntimeException('Could not read image dimensions.');
        }

        [$width, $height] = $dimensions;

        if ($width * $height > self::MAX_SOURCE_MEGAPIXELS * 1_000_000) {
            throw new RuntimeException('Image is too large to render.');
        }

        $bound = $rendition->maxDimension();

        $image = new SimpleImage;
        $image->fromFile($sourcePath)
            ->autoOrient()
            ->bestFit($bound, $bound);

        // The seam packages hook to decorate a rendered image — a
        // watermark, today. Dispatched before the encode so a listener's
        // changes cost no extra round trip through the codec; with nothing
        // listening the image is written exactly as produced above.
        Event::dispatch(new RenderingImage($image, $mimeType, $audience, $rendition));

        // Written beside the destination and renamed into place, so the
        // cached path never exists half-finished. Both callers test only
        // that the path exists and then serve whatever is there
        // (FileThumbnailController::render, PublicGroupsController::
        // thumbnail), and nothing ever invalidates a rendition —
        // RenderedImageCache::flush() runs on an event no core code raises.
        // A render that died partway would therefore be served as the
        // rendition from then on.
        //
        // It also settles the race: two requests rendering the same file at
        // once used to encode into one path together. rename() within a
        // directory is atomic and replaces what is there, so now the loser
        // leaves a complete rendition behind rather than a mixture of two.
        $temporaryPath = $destinationPath.'.'.bin2hex(random_bytes(8)).'.partial';

        try {
            $image->toFile($temporaryPath, $mimeType);

            if (! rename($temporaryPath, $destinationPath)) {
                throw new RuntimeException('Could not move the rendered image into place.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
