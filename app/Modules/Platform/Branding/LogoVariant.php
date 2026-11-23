<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding;

/**
 * Which of the two logos: the main one, shown everywhere, or the one shown
 * instead of it to anyone using the dark theme (#1845).
 *
 * Each has the same three columns — the image shown, the upload it was
 * cropped from, the crop box — so uploading, cropping, restoring and
 * removing are one piece of code that is told which set to use.
 */
enum LogoVariant: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function pathColumn(): string
    {
        return $this === self::Light ? 'logo_path' : 'logo_dark_path';
    }

    public function originalColumn(): string
    {
        return $this === self::Light ? 'logo_original_path' : 'logo_dark_original_path';
    }

    public function cropColumn(): string
    {
        return $this === self::Light ? 'logo_crop' : 'logo_dark_crop';
    }

    /** The form field the upload arrives in, and where its errors go. */
    public function field(): string
    {
        return $this === self::Light ? 'logo' : 'logo_dark';
    }
}
