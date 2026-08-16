<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Capabilities\Edition;

/**
 * Where "ProjectSend" points, which is not the same address for both
 * editions.
 *
 * Each edition has its own front door — projectsend.org for the software
 * you run yourself, projectsend.cloud for the hosted service — and the
 * link belongs to whichever one the reader is actually using. It matters
 * beyond the About screen: the "Powered by ProjectSend" line at the foot
 * of every outgoing email and on every client-facing page is the one
 * place a recipient meets the product for the first time, and sending a
 * hosted customer's recipients to the self-hosting instructions is the
 * wrong door.
 *
 * The donation link is dropped on a managed installation for a simpler
 * reason: those customers are already paying for this. Asking again, on
 * the screen that thanks them for choosing it, reads as not having
 * noticed.
 *
 * Resolved here rather than in the config file so both readers get the
 * same answer — the Inertia shared props and the two mail footers, which
 * read `config('projectsend.links.website')` straight out of Blade.
 */
class OfficialLinks
{
    public function __construct(private readonly CapabilityRegistry $capabilities) {}

    /**
     * @return array{website: string, source: string, discord: string, open_collective?: string}
     */
    public function toArray(): array
    {
        /** @var array<string, string> $links */
        $links = config('projectsend.links');

        $resolved = [
            'website' => $this->website(),
            'source' => $links['source'] ?? '',
            'discord' => $links['discord'] ?? '',
        ];

        // Omitted rather than hidden by the page, so a surface added later
        // cannot ask a paying customer for a donation by forgetting to
        // check.
        if (! $this->managed()) {
            $resolved['open_collective'] = $links['open_collective'] ?? '';
        }

        return $resolved;
    }

    public function website(): string
    {
        /** @var array<string, string> $links */
        $links = config('projectsend.links');

        return $this->managed()
            ? ($links['website_cloud'] ?? $links['website'])
            : $links['website'];
    }

    private function managed(): bool
    {
        return $this->capabilities->edition() === Edition::Cloud;
    }
}
