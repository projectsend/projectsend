<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding;

use App\Modules\Platform\Branding\Models\BrandingSetting;
use claviska\SimpleImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cutting a logo down to part of itself, and putting the whole of it back.
 * Either logo: the main one or the dark-mode one (LogoVariant).
 *
 * The uploaded file is never changed. A crop is a new file written from it,
 * so cropping again starts from everything that was uploaded rather than
 * from the last crop, and restoring is pointing back at the upload.
 *
 * Coordinates are in the upload's stored pixels, which is what GD reads. The
 * cropper shows the image the same way (`image-orientation: none`), because
 * the images here have no exif extension to rotate a phone photo by its
 * orientation tag: if the browser rotated it and GD did not, the box would
 * land on the wrong part of the picture.
 */
class LogoCropper
{
    /**
     * GD holds four bytes a pixel, and a 2 MB file can still describe a very
     * large image if it compresses well. 25 million pixels is about 100 MB of
     * memory, and far beyond any logo.
     */
    public const MAX_PIXELS = 25_000_000;

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $box
     */
    public function crop(BrandingSetting $setting, array $box, LogoVariant $variant = LogoVariant::Light): void
    {
        $source = $setting->logoSourcePath($variant);
        $disk = Storage::disk('public');
        $field = $variant->field();

        if ($source === null || ! $disk->exists($source)) {
            throw ValidationException::withMessages([$field => __('Upload a logo before cropping it.')]);
        }

        $absolute = $disk->path($source);
        $size = @getimagesize($absolute);

        if ($size === false) {
            throw ValidationException::withMessages([$field => __('This logo cannot be cropped. Upload it again.')]);
        }

        [$width, $height] = $size;

        if ($width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => __('This image is too large to crop. Upload a smaller one.')]);
        }

        if ($box['x'] + $box['width'] > $width || $box['y'] + $box['height'] > $height) {
            throw ValidationException::withMessages(['width' => __('The crop must stay inside the image.')]);
        }

        // The whole picture is the upload itself: no second copy of it.
        if ($box['x'] === 0 && $box['y'] === 0 && $box['width'] === $width && $box['height'] === $height) {
            $this->restore($setting, $variant);

            return;
        }

        // Same extension as the upload, which took it from the content
        // when it was stored (see BrandingController::storeImage).
        $cropped = 'branding/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);

        (new SimpleImage($absolute))
            ->crop($box['x'], $box['y'], $box['x'] + $box['width'], $box['y'] + $box['height'])
            ->toFile($disk->path($cropped), $size['mime']);

        $previous = $setting->logoIsCropped($variant) ? $setting->logoPath($variant) : null;

        $setting->update([
            $variant->originalColumn() => $source,
            $variant->pathColumn() => $cropped,
            $variant->cropColumn() => $box,
        ]);

        if ($previous !== null) {
            $disk->delete($previous);
        }
    }

    public function restore(BrandingSetting $setting, LogoVariant $variant = LogoVariant::Light): void
    {
        if (! $setting->logoIsCropped($variant)) {
            return;
        }

        $cropped = $setting->logoPath($variant);

        $setting->update([
            $variant->pathColumn() => $setting->logoSourcePath($variant),
            $variant->originalColumn() => null,
            $variant->cropColumn() => null,
        ]);

        if ($cropped !== null) {
            Storage::disk('public')->delete($cropped);
        }
    }

    /**
     * Every file the logo occupies: the one shown, and the upload behind it.
     *
     * @return list<string>
     */
    public function files(BrandingSetting $setting, LogoVariant $variant = LogoVariant::Light): array
    {
        return array_values(array_filter([
            $setting->logoPath($variant),
            $setting->getAttribute($variant->originalColumn()),
        ], fn (mixed $path): bool => is_string($path) && $path !== ''));
    }
}
