<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * The single "is a newer version known, and what does it look like"
 * check — shared by the dashboard's System card (informational, gated
 * only on view_system_info + edition) and HandleInertiaRequests' topbar
 * notice (actionable, additionally gated on manage_updates). Both read
 * the same cache CheckForUpdatesCommand writes; neither ever makes a
 * live HTTP call.
 */
class LatestReleaseInfo
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array{version: string, title: string, notes: string, url: string, published_at: string}|null
     */
    public function current(): ?array
    {
        if (! $this->capabilities->has(Capability::SystemUpdates)) {
            return null;
        }

        $latestVersion = $this->string(Setting::LatestKnownVersion);

        if ($latestVersion === '' || ! version_compare($latestVersion, (string) config('projectsend.version'), '>')) {
            return null;
        }

        return [
            'version' => $latestVersion,
            'title' => $this->string(Setting::LatestReleaseTitle),
            'notes' => $this->string(Setting::LatestReleaseNotes),
            'url' => $this->string(Setting::LatestReleaseUrl),
            'published_at' => $this->string(Setting::LatestReleasePublishedAt),
        ];
    }

    private function string(Setting $setting): string
    {
        $value = $this->settings->get($setting);

        return is_string($value) ? $value : '';
    }
}
