<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Modules\Platform\Branding\Models\BrandingSetting;

/**
 * This installation's branding — its logo and its thumbnail watermark —
 * over the host's API.
 *
 * Read-only on purpose: uploading an image is a multipart flow with
 * content-sniffing rules that only make sense with a file picker in front
 * of them (see the web controller), and nothing has asked to automate it.
 * An integration that wants to render this installation's branding — an
 * email builder, a status page — only needs to read it.
 *
 * This controller knows nothing about authentication, rate limiting,
 * error formats or which edition it is running in. The host supplies all
 * of that: the module is registered through RegisteringApiModules, which
 * mounts these routes inside the API's own auth stack and behind
 * `capability:branding.customize`. That is the whole point of the seam —
 * a package declares paths and controllers, and nothing else.
 */
class BrandingController extends Controller
{
    /**
     * Get this installation's logo.
     *
     * Returns a null `logo_url` when no logo has been uploaded, which is
     * the normal state rather than an error.
     */
    public function show(): JsonResponse
    {
        $setting = BrandingSetting::query()->first();

        return response()->json([
            'data' => [
                'logo_url' => $setting?->logoUrl(),
                'updated_at' => $setting?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the watermark applied to this installation's rendered images.
     *
     * Applies to the thumbnails and previews clients and anonymous
     * public visitors see; what this installation's own staff see is
     * never marked. The stored files, and every download of them, are
     * never altered either way.
     *
     * `enabled` is false whenever no watermark is being drawn, including
     * when the toggle is on but its image has since been removed —
     * it answers "is this installation watermarking?", not "which way is
     * the switch pointing?". `position` is one of `top-left`,
     * `top-center`, `top-right`, `middle-left`, `center`, `middle-right`,
     * `bottom-left`, `bottom-center`, `bottom-right`; `size` is the
     * percentage of the image the mark is fitted into, and `opacity`
     * a percentage.
     *
     * Read-only, same as the logo: an integration rendering its own
     * derivative images can reproduce the mark, but uploading one is a
     * multipart flow with content-sniffing rules that only make sense
     * behind a file picker.
     */
    public function watermark(): JsonResponse
    {
        // Falls back to an unsaved instance so an installation that has
        // never opened the branding screen answers with the defaults it
        // would start from, rather than a payload of nulls a caller would
        // have to invent its own meaning for.
        $setting = BrandingSetting::query()->first() ?? new BrandingSetting;

        return response()->json([
            'data' => [
                'enabled' => $setting->watermarksThumbnails(),
                'image_url' => $setting->watermarkUrl(),
                'position' => $setting->watermark_position->value,
                'size' => $setting->watermark_size,
                'opacity' => $setting->watermark_opacity,
            ],
        ]);
    }
}
