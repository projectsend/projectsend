<?php

declare(strict_types=1);

namespace App\Modules\Platform\News\Console;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Stevebauman\Purify\Facades\Purify;

/**
 * Both editions — unlike CheckForUpdatesCommand, this isn't gated on any
 * Capability: dashboard news is informational content, not an update
 * action, so Cloud tenants see it too.
 *
 * The feed returns raw HTML in `content` (links, paragraphs) — sanitized
 * here, once, before it's ever cached or sent to the frontend, so the
 * dashboard can render it directly without its own sanitization step.
 */
class FetchNewsCommand extends Command
{
    protected $signature = 'projectsend:fetch-news';

    protected $description = 'Fetch the ProjectSend news feed for the dashboard (both editions, runs daily)';

    private const FEED_URL = 'https://projectsend.org/serve/news';

    private const MAX_ITEMS = 5;

    private const ALLOWED_HTML = 'a[href],p,br,strong,em,ul,ol,li';

    public function __construct(
        private readonly Settings $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $response = Http::withHeaders(['User-Agent' => 'ProjectSend'])
            ->timeout(10)
            ->get(self::FEED_URL);

        if (! $response->successful()) {
            $this->warn('Could not reach the news feed.');

            return self::FAILURE;
        }

        $raw = $response->json();

        if (! is_array($raw)) {
            $this->warn('News feed response was not a JSON array — skipping.');

            return self::SUCCESS;
        }

        $items = collect($raw)
            ->map(fn (mixed $entry): ?array => $this->normalize($entry))
            ->filter()
            ->sortByDesc('date')
            ->take(self::MAX_ITEMS)
            ->values()
            ->all();

        $this->settings->set(Setting::NewsItems, $items);
        $this->settings->set(Setting::NewsLastFetchedAt, now()->toIso8601String());

        $this->info('Fetched '.count($items).' news item(s).');

        return self::SUCCESS;
    }

    /**
     * @return array{title: string, date: string, content: string, link: string}|null
     */
    private function normalize(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $title = $entry['title'] ?? null;
        $date = $entry['date'] ?? null;
        $content = $entry['content'] ?? null;
        $link = $entry['link'] ?? null;

        if (! is_string($title) || ! is_string($date) || ! is_string($content) || ! is_string($link)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('d-m-Y', $date);
        } catch (\Throwable) {
            $parsed = false;
        }

        if (! $parsed instanceof Carbon) {
            return null;
        }

        // Plain Y-m-d, not a full timestamp — matches the dashboard's
        // existing shortDate() helper, which appends its own T00:00:00
        // (same convention as the Transfers chart's date points).
        $parsedDate = $parsed->toDateString();

        // The feed separates paragraphs with raw \r\n, not <p> tags —
        // convert to <br> (already allowlisted) before purifying, or
        // they'd collapse into one run-on blob once rendered as HTML.
        $cleaned = Purify::config(['HTML.Allowed' => self::ALLOWED_HTML])->clean(nl2br($content));

        return [
            // The feed HTML-encodes title (e.g. "&#8217;") even though
            // it's rendered as plain JSX text on the dashboard, not HTML —
            // decode here so it displays as a real apostrophe instead of
            // the literal entity string.
            'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5),
            'date' => $parsedDate,
            'content' => is_string($cleaned) ? $cleaned : '',
            'link' => $link,
        ];
    }
}
