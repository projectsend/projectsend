<?php

declare(strict_types=1);

namespace App\Modules\Platform\Announcements\Events;

/**
 * A single message a package wants shown across the top of the dashboard.
 *
 * Not a widget, on purpose. The widget grid is a closed list of keys that
 * dashboard.tsx renders one by one, and each viewer arranges it — so a
 * message that matters would sit wherever somebody happened to drag it,
 * or under a fold, or switched off. A callout is one band above the grid:
 * seen, and not competing with the columns for space.
 *
 * **Core knows nothing about what it says.** Title, body, the label on the
 * button and where the button goes all come from the listener. The first
 * caller is the hosted edition telling a free instance what a paid plan
 * would give it, which is commercial copy belonging to one offering and
 * has no place in the public repository.
 *
 * One at a time, deliberately. A dashboard that can accumulate banners
 * accumulates them, and the second one is what teaches people to skip the
 * first. A listener that finds `$callout` already set should leave it
 * alone rather than overwrite it.
 */
class ResolvingDashboardCallout
{
    /**
     * @var array{title: string, body: string, action_label: string|null, action_url: string|null, tone: string}|null
     */
    public ?array $callout = null;

    public function __construct(
        /** Whether the viewer is a staff account. */
        public readonly bool $isStaff,
    ) {}

    /**
     * `tone` picks the accent the band is drawn in. Two values, because
     * two is what the difference is worth: `info` for something worth
     * knowing, `warning` for something worth acting on. Anything else
     * falls back to `info` rather than rendering unstyled.
     */
    public function show(string $title, string $body, ?string $actionLabel = null, ?string $actionUrl = null, string $tone = 'info'): void
    {
        if ($this->callout !== null) {
            return;
        }

        $this->callout = [
            'title' => $title,
            'body' => $body,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'tone' => in_array($tone, ['info', 'warning'], true) ? $tone : 'info',
        ];
    }
}
