<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding;

use App\Modules\Api\Events\RegisteringApiModules;
use App\Modules\Files\Thumbnails\Events\RenderingImage;
use App\Modules\Files\Thumbnails\Events\ResolvingImageRendering;
use App\Modules\Platform\Branding\Models\BrandingSetting;
use App\Modules\Platform\Branding\Watermark\ThumbnailWatermarker;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * An installation dressed in its own logo, and a watermark on what its
 * clients and visitors see.
 *
 * Lived in the private cloud-modules package until 2026-08-28, gated
 * Cloud-only. That was a fact about where the code had been written
 * rather than about who should have it: nothing here needs a hosted
 * platform, and a self-hosted installation wanting its own mark on the
 * pages it serves is the ordinary case rather than the exotic one.
 *
 * **What did not move.** Hiding the "Powered by ProjectSend" line is the
 * white-label half, and white-labelling is one of the things a hosted
 * customer pays for. Its listener still ships only in cloud-modules, so
 * an installation without that package has no code able to answer "hide
 * it" — flipping an edition variable buys nothing. This module carries
 * the column, because it owns the table, and no way to set it.
 *
 * **What a plan withholds is a separate question.** A free hosted plan
 * has branding subtracted from its environment, which the capability
 * registry applies; see PROJECTSEND_CAPABILITIES_DISABLED. The row is
 * never deleted by that, so a plan that lapses and resumes restores what
 * the customer had rather than asking them to build it again.
 */
class BrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registered unconditionally. The listeners ask whether branding
        // is available each time they fire, so an edition change, or a
        // plan change that subtracts the capability, takes effect on the
        // next request rather than needing a restart.
        // Through the module registry rather than routes/api.php, so the
        // URL stays /api/v1/modules/branding/* exactly as it was when this
        // shipped in a package. A caller's integration does not care which
        // repository the code moved to, and moving the path would be a
        // breaking change dressed up as a refactor.
        Event::listen(RegisteringApiModules::class, function (RegisteringApiModules $event): void {
            $event->register(
                slug: 'branding',
                routes: __DIR__.'/api-routes.php',
                capability: Capability::Branding->value,
            );
        });

        Event::listen(RenderingImage::class, [ThumbnailWatermarker::class, 'handle']);
        Event::listen(ResolvingImageRendering::class, [ThumbnailWatermarker::class, 'resolve']);

        // Gated like the screen that sets it. Without the capability there
        // is no branding page to reach, so a row that outlived a gate
        // change — a downgraded plan, a restored backup — would put
        // somebody's logo on every page of an installation offering no way
        // to see it, change it or take it off. Evaluated per request, so
        // uploading a logo or changing plan takes effect on the next one.
        Inertia::share('branding', fn (): array => [
            'logo_url' => $this->available()
                ? BrandingSetting::query()->first()?->logoUrl()
                : null,
        ]);
    }

    private function available(): bool
    {
        return $this->app->make(CapabilityRegistry::class)->has(Capability::Branding);
    }
}
