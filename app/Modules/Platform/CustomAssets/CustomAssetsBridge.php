<?php

declare(strict_types=1);

namespace App\Modules\Platform\CustomAssets;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Http\Request;
use ProjectSend\CommunityModules\Modules\CustomAssets\AssetPosition;
use ProjectSend\CommunityModules\Modules\CustomAssets\AssetSurface;
use ProjectSend\CommunityModules\Modules\CustomAssets\Rendering\CustomAssetRenderer;

/**
 * The host's side of the optional Custom Assets integration — the root
 * Blade view calls this, never the package directly.
 *
 * Two refusals, both deliberate:
 *
 * 1. No capability, no output. Custom Assets is Community-only, and the
 *    module already refuses to let anyone *author* an asset without
 *    `custom_assets.manage`. Rendering takes the same gate so the write
 *    check is not the only thing standing between a Cloud install and
 *    arbitrary page injection: a row that predates that check, arrives in
 *    a restored backup, or is written straight to the table must still
 *    render nothing here.
 *
 * 2. No package, no output. The module is Community-exclusive and is
 *    meant to be physically absent from the Cloud deployment (see its own
 *    docblock). It is present today only because this repo requires it for
 *    development, so the layout must not break the day that dependency is
 *    dropped — hence the class_exists() guard rather than a direct call.
 *
 * Content is echoed raw by the caller ({!! !!}), which is the whole point
 * of the feature; the trust boundary is the staff-only, permission-gated
 * authoring screen, not this class.
 */
class CustomAssetsBridge
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * @param  string  $position  one of AssetPosition's values: head, body_top, body_bottom
     */
    public function render(string $position, ?Request $request = null): string
    {
        if (! $this->capabilities->has(Capability::CustomAssets)) {
            return '';
        }

        if (! class_exists(CustomAssetRenderer::class)) {
            return '';
        }

        $slot = AssetPosition::tryFrom($position);

        if ($slot === null) {
            return '';
        }

        $user = ($request ?? request())->user();

        return app(CustomAssetRenderer::class)->render(
            $slot,
            AssetSurface::current($user, $user !== null && $user->isStaff()),
        );
    }
}
