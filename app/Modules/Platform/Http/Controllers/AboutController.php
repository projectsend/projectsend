<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\System\SystemEnvironment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "What am I running, and where did it come from?" — the one screen that
 * answers it, and the place the sidebar's version line goes.
 *
 * Read-only, and open to any staff member rather than only those who can
 * edit settings: a support reply that starts "go to About and tell me
 * the version" should not depend on the person having configuration
 * rights.
 *
 * The environment block is the exception, and carries the dashboard
 * System widget's gate verbatim (view_system_info + SystemUpdates) so
 * the two screens cannot disagree about who may read it — where an
 * installation is managed for you, the PHP build is not your concern,
 * which is exactly what that capability already encodes.
 */
class AboutController extends Controller
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly SystemEnvironment $environment,
        private readonly Settings $settings,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        $canSeeEnvironment = $user->can('view_system_info')
            && $this->capabilities->has(Capability::SystemUpdates);

        return Inertia::render('system/about', [
            'license' => 'GNU General Public License v2',
            'environment' => $canSeeEnvironment ? $this->environment->toArray() : null,
            'updated' => $canSeeEnvironment ? $this->lastUpdate() : null,
        ]);
    }

    /**
     * When `projectsend:update` last brought this installation to a new
     * version.
     *
     * Both values are written by that command alone, and RunningCodeState
     * has until now been their only reader — which means the fact was
     * recorded and then only ever mentioned when something was *wrong*.
     * On a healthy installation nothing said when it was last updated,
     * which is the ordinary question of the two.
     *
     * Null on anything that has never been updated through the command: a
     * fresh install, or one older than the command itself. There is no
     * honest date to show there, and "unknown" is noise.
     *
     * @return array{version: string, at: string}|null
     */
    private function lastUpdate(): ?array
    {
        $version = $this->settings->get(Setting::AppliedVersion);
        $at = $this->settings->get(Setting::AppliedVersionAt);

        if (! is_string($version) || $version === '' || ! is_string($at) || $at === '') {
            return null;
        }

        return ['version' => $version, 'at' => $at];
    }
}
