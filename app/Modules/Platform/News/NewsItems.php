<?php

declare(strict_types=1);

namespace App\Modules\Platform\News;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Reads back what FetchNewsCommand cached — no capability check (both
 * editions get news) and no permission check (that happens one layer up,
 * in whatever controller decides whether to call current() at all — see
 * DashboardController), matching LatestReleaseInfo's own division of
 * responsibility.
 */
class NewsItems
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * @return list<array{title: string, date: string, content: string, link: string}>
     */
    public function current(): array
    {
        $items = $this->settings->get(Setting::NewsItems);

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            fn (mixed $item): bool => is_array($item)
                && is_string($item['title'] ?? null)
                && is_string($item['date'] ?? null)
                && is_string($item['content'] ?? null)
                && is_string($item['link'] ?? null),
        ));
    }
}
