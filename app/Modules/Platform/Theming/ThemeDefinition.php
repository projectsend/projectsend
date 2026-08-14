<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming;

use Closure;

/**
 * One selectable theme: a key used in Setting values and Inertia/view
 * resolution paths, a human label for the picker, a short description
 * (shown under the label in the picker so a client can tell what a theme
 * is actually for — v1 had this and it was dropped in the rewrite until
 * 2026-08-05), and the capability (if any) gating it — null means free,
 * available in every edition.
 *
 * $requires is a Capability enum's string value, not the enum itself, so
 * that the private cloud-modules package's registration code (see
 * ThemesServiceProvider) never needs a hard dependency on the host app's
 * App\Modules\Platform\Capabilities\Capability class — that class (and
 * PublicThemeRegistry/EmailThemeRegistry themselves) doesn't exist in the
 * package's own isolated Testbench suite, only inside the real host app.
 * ThemeRegistry converts it back to a real Capability internally.
 *
 * $context is only meaningful for email themes: extra data resolved
 * fresh at send time (see ThemedMailChannel) and merged into the
 * header/footer view context — e.g. the Branded premium theme uses it
 * to pull the Branding module's current logo URL without core needing
 * to know that module exists.
 */
final class ThemeDefinition
{
    /**
     * @param  (Closure(): array<string, mixed>)|null  $context
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly ?string $requires = null,
        public readonly ?Closure $context = null,
    ) {}
}
