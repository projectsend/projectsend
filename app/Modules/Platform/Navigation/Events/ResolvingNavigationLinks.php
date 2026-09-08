<?php

declare(strict_types=1);

namespace App\Modules\Platform\Navigation\Events;

/**
 * Extra links a package wants in the sidebar.
 *
 * The sidebar is built from a hardcoded array in app-sidebar.tsx, which
 * means a package could not contribute to it at all — the nav link was a
 * separate manual edit every time a package grew a screen, and being
 * manual it was forgotten. This is the seam that fixes that, in the shape
 * the extension-points document settles on: core dispatches
 * unconditionally, listeners add or do not, and with no listener the
 * default (no extra links) holds.
 *
 * **Core deliberately learns nothing about what is added.** A link's
 * label, its URL and the reason it exists all arrive from whoever
 * registers it. That is not fastidiousness: the first caller is the
 * hosted edition's link to its own customer portal, and a product URL
 * belonging to one commercial offering has no business sitting in the
 * public repository just because the sidebar happens to live here.
 *
 * Staff only, and enforced here rather than trusted to each listener:
 * these appear in the administration area, and a client's portal shows
 * only their own files.
 */
class ResolvingNavigationLinks
{
    /**
     * @var list<array{title: string, url: string, external: bool, icon: string|null}>
     */
    public array $links = [];

    public function __construct(
        /** Whether the viewer is a staff account. Listeners that only make
         *  sense for staff should check this rather than assume. */
        public readonly bool $isStaff,
    ) {}

    /**
     * `external` opens in a new tab and marks the link as leaving this
     * installation — a link that navigates a person away from the app
     * they are working in should say so before they click it, not after.
     */
    public function add(string $title, string $url, bool $external = false, ?string $icon = null): void
    {
        $this->links[] = [
            'title' => $title,
            'url' => $url,
            'external' => $external,
            'icon' => $icon,
        ];
    }
}
