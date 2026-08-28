<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Watermark;

use claviska\SimpleImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Modules\Files\Thumbnails\Events\RenderingImage;
use App\Modules\Files\Thumbnails\Events\ResolvingImageRendering;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Branding\Models\BrandingSetting;
use Throwable;

/**
 * Stamps the configured mark onto an image the host is about to render,
 * for both of the host's rendering hooks:
 *
 * - `RenderingImage` — the drawing itself, on a thumbnail or a preview.
 * - `ResolvingImageRendering` — the host asking, before it decodes
 *   anything, whether this viewer has to be served a rendering at all.
 *   Answering yes is what turns a client's preview from the stored file
 *   into a watermarked copy; leaving it alone is what keeps previews
 *   free on installations that do not watermark.
 *
 * Both events are duck-typed (`object`, `$event->audience`) rather than
 * imported: this package is built and tested with no host application
 * present, so `use App\Modules\Files\...` would not resolve. See the
 * host's own docblocks and docs/extension-points-architecture.md in the
 * host repo.
 *
 * Nothing here throws. The host deliberately does not wrap listeners in
 * a try/catch — a listener that fails takes the request down with it —
 * and for a decoration that is the wrong trade: an unreadable or
 * since-deleted watermark file must degrade to a plain image, not to a
 * broken one on every listing row in the app. Failures are logged so the
 * setting can be fixed rather than silently doing nothing.
 *
 * The one asymmetry worth knowing: `wouldMark()` and `apply()` ask the
 * same question a moment apart, so a watermark switched off between the
 * two would yield a rendered-but-unmarked preview. That is a plain copy
 * of the original at preview size — the correct content, reached by a
 * slower path — and it self-corrects on the next request, since saving
 * the setting flushes the cache anyway.
 */
class ThumbnailWatermarker
{
    public function __construct(
        private readonly WatermarkPainter $painter,
    ) {}

    /**
     * The audience whose images go unmarked: this installation's own
     * staff. Watermarking exists for the copies that leave the building —
     * clients in the portal, anonymous visitors on a public listing — and
     * stamping the staff file manager and file editor too would only
     * obscure the originals from the people who uploaded them.
     */
    public function handle(RenderingImage $event): void
    {
        try {
            if ($event->audience === ImageAudience::Staff) {
                return;
            }

            $this->apply($event->image);
        } catch (Throwable $exception) {
            Log::warning('Could not watermark a rendered image: '.$exception->getMessage());
        }
    }

    /**
     * Whether an image must be rendered rather than served as stored.
     * Only ever sets the flag — never clears it, since another listener's
     * yes is not this one's to overrule.
     */
    public function resolve(ResolvingImageRendering $event): void
    {
        try {
            if ($event->audience === ImageAudience::Staff) {
                return;
            }

            if ($this->wouldMark()) {
                $event->required = true;
            }
        } catch (Throwable $exception) {
            // Leaves the host on its fast path, which serves the original
            // — the behaviour of every installation that does not
            // watermark, and never a failed request.
            Log::warning('Could not decide whether to watermark a preview: '.$exception->getMessage());
        }
    }

    /**
     * Whether there is a mark to draw at all: switched on, with an image
     * that is still on disk. Deliberately the same three conditions
     * apply() checks, so the host is never told to render something this
     * listener would then decline to touch.
     */
    private function wouldMark(): bool
    {
        if (! $this->capabilityAvailable()) {
            return false;
        }

        $markPath = BrandingSetting::query()->first()?->activeWatermarkPath();

        return $markPath !== null && Storage::disk('public')->exists($markPath);
    }

    private function apply(SimpleImage $canvas): void
    {
        if (! $this->capabilityAvailable()) {
            return;
        }

        $setting = BrandingSetting::query()->first();

        if ($setting === null) {
            return;
        }

        $markPath = $setting->activeWatermarkPath();

        if ($markPath === null) {
            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($markPath)) {
            Log::warning('Watermarking is on but its image is missing from disk: '.$markPath);

            return;
        }

        $this->painter->paint(
            $canvas,
            $disk->path($markPath),
            $setting->watermark_position,
            $setting->watermark_size,
            $setting->watermark_opacity,
        );
    }

    /**
     * Watermarking is part of the Cloud-exclusive Branding capability, so
     * it renders nothing where that capability is absent — the same
     * "no capability, no output" stance the host takes for Custom Assets.
     * The capability registry holds the one definition of
     * the check; the shared logo answers to it too.
     */
    private function capabilityAvailable(): bool
    {
        return app(CapabilityRegistry::class)->has(Capability::Branding);
    }
}
