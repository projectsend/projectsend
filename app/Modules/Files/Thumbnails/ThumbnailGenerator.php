<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails;

use App\Modules\Files\Thumbnails\Events\RenderingImage;
use claviska\SimpleImage;
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

    public function generate(
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

        $image->toFile($destinationPath, $mimeType);
    }
}
