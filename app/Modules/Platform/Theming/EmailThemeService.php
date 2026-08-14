<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming;

use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Resolves the tenant's active outgoing-email theme (Setting::EmailTheme)
 * against the available EmailThemeRegistry entries — used by
 * ThemedMailChannel at send time, not read from config directly, so it
 * stays correct in a long-running queue worker even if the setting
 * changes between jobs.
 */
class EmailThemeService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly EmailThemeRegistry $themes,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    public function currentThemeKey(): string
    {
        $value = $this->settings->get(Setting::EmailTheme);

        return $this->themes->resolve(is_string($value) ? $value : 'default', $this->capabilities);
    }
}
