<?php

declare(strict_types=1);

namespace App\Modules\Platform\Localization;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Two different questions, deliberately kept apart.
 *
 * *Installed* is discovered from the lang directory rather than a config
 * list: translation packs are distributed dynamically (downloadable from a
 * service), so a locale is installed exactly when its lang/{locale}.json
 * catalog is present. English is always installed — app strings use English
 * text as the key, so it needs no catalog.
 *
 * *Enabled* is what the language switcher actually offers: the subset an
 * administrator ticked on /system/settings/languages. Installing a pack no
 * longer puts it in front of clients on its own. English is always enabled
 * for the same reason it is always installed, whatever the setting says —
 * so a bad row can never leave an install with no language at all.
 *
 * Everything that resolves or validates a request locale wants `enabled()`;
 * only the settings screen that manages the list wants `installed()`.
 */
class LocaleRegistry
{
    /**
     * @var list<string>|null
     */
    private ?array $installed = null;

    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * @return list<string>
     */
    public function installed(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $locales = ['en'];

        foreach (glob(lang_path('*.json')) ?: [] as $catalog) {
            $locales[] = basename($catalog, '.json');
        }

        sort($locales);

        return $this->installed = array_values(array_unique($locales));
    }

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        // Not memoized, unlike installed(): the settings screen saves and
        // re-reads within the same request, and an intersection over a
        // handful of strings is not worth a staleness bug.
        $enabled = $this->settings->get(Setting::EnabledLocales);

        $enabled = is_array($enabled)
            ? array_filter($enabled, is_string(...))
            : [];

        return array_values(array_filter(
            $this->installed(),
            fn (string $locale): bool => $locale === 'en' || in_array($locale, $enabled, true),
        ));
    }

    public function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabled(), true);
    }

    /**
     * The language to fall back on when the visitor has expressed no
     * usable preference of their own.
     *
     * Never returns something the switcher does not offer: a default that
     * was later switched off, or an APP_LOCALE nobody enabled, degrades to
     * English rather than stranding every visitor in a language the
     * interface will not let them leave.
     */
    public function defaultLocale(): string
    {
        $configured = $this->settings->get(Setting::DefaultLocale);

        // Empty means the operator has never chosen one here, so the
        // environment still has the say it always had.
        if (! is_string($configured) || $configured === '') {
            $configured = (string) config('app.locale');
        }

        return $this->isEnabled($configured) ? $configured : 'en';
    }

    /**
     * The enabled locales with the default first, for
     * Request::getPreferredLanguage(): handed a list, it returns the head
     * when the Accept-Language header matches nothing, so the ordering
     * *is* how the default gets applied.
     *
     * @return list<string>
     */
    public function preferenceOrder(): array
    {
        $default = $this->defaultLocale();

        return [$default, ...array_values(array_diff($this->enabled(), [$default]))];
    }
}
