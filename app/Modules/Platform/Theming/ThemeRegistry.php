<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Closure;

/**
 * A pool of selectable themes, populated by whichever service providers
 * register into it (core free themes from PlatformServiceProvider; premium
 * themes from the private cloud-modules package's ThemesServiceProvider,
 * when installed) — never a closed enum, since core must not need to know
 * premium theme keys at compile time.
 *
 * Bound as two separate singletons (PublicThemeRegistry, EmailThemeRegistry)
 * so the two surfaces never mix entries, while sharing this one
 * implementation. See PlatformServiceProvider for the bindings.
 *
 * Convention (not enforced in code, just a rule to follow): a new "look"
 * should register under the **same key** in both registries — e.g.
 * 'drive' in PublicThemeRegistry and 'drive' in EmailThemeRegistry — so
 * Setting::Theme and Setting::EmailTheme, though independently
 * selectable, read as one coherent pair per look. A theme existing on
 * only one side of a pair is the exception (the historical
 * compact/minimal and gallery/branded pairs predate this convention and
 * don't follow it), not the pattern to copy going forward.
 *
 * Before adding a theme, or changing anything a theme page depends on,
 * read docs/theming-files-checklist.md (this is PublicThemeRegistry —
 * public pages + client portal) or docs/theming-email-checklist.md
 * (EmailThemeRegistry).
 */
class ThemeRegistry
{
    /**
     * @var array<string, ThemeDefinition>
     */
    private array $themes = [];

    /**
     * @param  (Closure(): array<string, mixed>)|null  $context
     */
    public function register(string $key, string $label, string $description, ?string $requires = null, ?Closure $context = null): void
    {
        $this->themes[$key] = new ThemeDefinition($key, $label, $description, $requires, $context);
    }

    /**
     * Themes selectable/renderable in the current edition. The only real
     * enforcement point for gated themes — a theme requiring a capability
     * the current edition lacks simply never appears here.
     *
     * "default" always sorts first, regardless of registration order —
     * which provider boots first (core vs. the premium package) must
     * never decide this, so it's enforced here rather than left to
     * insertion order.
     *
     * @return list<ThemeDefinition>
     */
    public function available(CapabilityRegistry $capabilities): array
    {
        $themes = array_values(array_filter(
            $this->themes,
            fn (ThemeDefinition $theme): bool => $theme->requires === null
                || $capabilities->has(Capability::from($theme->requires)),
        ));

        usort($themes, fn (ThemeDefinition $a, ThemeDefinition $b): int => ($b->key === 'default') <=> ($a->key === 'default'));

        return $themes;
    }

    /**
     * The key to actually render: the stored value if it's a real,
     * currently-available theme, else the safe "default" fallback — a
     * downgraded tenant or a self-hosted install without a premium
     * package must never see a broken page.
     */
    public function resolve(string $key, CapabilityRegistry $capabilities): string
    {
        foreach ($this->available($capabilities) as $theme) {
            if ($theme->key === $key) {
                return $key;
            }
        }

        return 'default';
    }

    public function has(string $key): bool
    {
        return isset($this->themes[$key]);
    }

    public function get(string $key): ?ThemeDefinition
    {
        return $this->themes[$key] ?? null;
    }
}
