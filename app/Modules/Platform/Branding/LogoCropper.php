<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding;

use App\Modules\Platform\Branding\Models\BrandingSetting;
use claviska\SimpleImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cutting the logo down to part of itself, and putting the whole of it back.
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
    public function crop(BrandingSetting $setting, array $box): void
    {
        $source = $setting->logoSourcePath();
        $disk = Storage::disk('public');

        if ($source === null || ! $disk->exists($source)) {
            throw ValidationException::withMessages(['logo' => __('Upload a logo before cropping it.')]);
        }

        $absolute = $disk->path($source);
        $size = @getimagesize($absolute);

        if ($size === false) {
            throw ValidationException::withMessages(['logo' => __('This logo cannot be cropped. Upload it again.')]);
        }

        [$width, $height] = $size;

        if ($width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['logo' => __('This image is too large to crop. Upload a smaller one.')]);
        }

        if ($box['x'] + $box['width'] > $width || $box['y'] + $box['height'] > $height) {
            throw ValidationException::withMessages(['width' => __('The crop must stay inside the image.')]);
        }

        // The whole picture is the upload itself: no second copy of it.
        if ($box['x'] === 0 && $box['y'] === 0 && $box['width'] === $width && $box['height'] === $height) {
            $this->restore($setting);

            return;
        }

        // Same extension as the upload, which took it from the content
        // when it was stored (see BrandingController::storeImage).
        $cropped = 'branding/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);

        (new SimpleImage($absolute))
            ->crop($box['x'], $box['y'], $box['x'] + $box['width'], $box['y'] + $box['height'])
            ->toFile($disk->path($cropped), $size['mime']);

        $previous = $setting->logoIsCropped() ? $setting->logo_path : null;

        $setting->update([
            'logo_original_path' => $source,
            'logo_path' => $cropped,
            'logo_crop' => $box,
        ]);

        if ($previous !== null) {
            $disk->delete($previous);
        }
    }

    public function restore(BrandingSetting $setting): void
    {
        if (! $setting->logoIsCropped()) {
            return;
        }

        $cropped = $setting->logo_path;

        $setting->update([
            'logo_path' => $setting->logo_original_path,
            'logo_original_path' => null,
            'logo_crop' => null,
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
    public function files(BrandingSetting $setting): array
    {
        return array_values(array_filter([$setting->logo_path, $setting->logo_original_path]));
    }
}
