<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Modules\Platform\Branding\Watermark\WatermarkPosition;

/**
 * A single-row settings table — see the migration's comment for why no
 * tenant/owner column is needed.
 *
 * @property int $id
 * @property string|null $logo_path
 * @property string|null $logo_original_path
 * @property array{x: int, y: int, width: int, height: int}|null $logo_crop
 * @property bool $watermark_enabled
 * @property string|null $watermark_path
 * @property WatermarkPosition $watermark_position
 * @property int $watermark_size
 * @property int $watermark_opacity
 * @property bool $hide_attribution
 * @property bool $show_site_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BrandingSetting extends Model
{
    protected $table = 'branding_settings';

    protected $guarded = [];

    protected $casts = [
        'watermark_enabled' => 'boolean',
        'show_site_name' => 'boolean',
        'logo_crop' => 'array',
        'watermark_position' => WatermarkPosition::class,
        'watermark_size' => 'integer',
        'watermark_opacity' => 'integer',
        'hide_attribution' => 'boolean',
    ];

    /**
     * Mirrors the migration's column defaults, so an unsaved instance
     * answers the same as a freshly created row would. That is what lets
     * the settings screen render `new BrandingSetting` on an install that
     * has never touched branding, instead of either creating a row on a
     * GET or restating these numbers a second time in the controller.
     */
    protected $attributes = [
        'watermark_enabled' => false,
        'show_site_name' => false,
        'watermark_position' => 'bottom-right',
        'watermark_size' => 30,
        'watermark_opacity' => 60,
        'hide_attribution' => false,
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }

    /**
     * The file the logo was cut from: the upload itself when it was never
     * cropped, since then `logo_path` is that upload. A crop always starts
     * from here, never from a previous crop.
     */
    public function logoSourcePath(): ?string
    {
        return $this->logo_original_path ?? $this->logo_path;
    }

    public function logoSourceUrl(): ?string
    {
        $path = $this->logoSourcePath();

        return $path === null ? null : Storage::disk('public')->url($path);
    }

    public function logoIsCropped(): bool
    {
        return $this->logo_original_path !== null;
    }

    public function watermarkUrl(): ?string
    {
        return $this->watermark_path === null ? null : Storage::disk('public')->url($this->watermark_path);
    }

    /**
     * The artwork to stamp on a thumbnail being rendered right now, or
     * null when this installation is not watermarking.
     *
     * Phrased as "which image, if any" rather than as a boolean because
     * the toggle alone is not enough to act on: removing the image
     * leaves the toggle standing, and a row restored from a backup can
     * carry an `enabled` that its file no longer backs. Answering both
     * halves at once means a caller cannot check one and use the other.
     */
    public function activeWatermarkPath(): ?string
    {
        return $this->watermark_enabled ? $this->watermark_path : null;
    }

    public function watermarksThumbnails(): bool
    {
        return $this->activeWatermarkPath() !== null;
    }
}
