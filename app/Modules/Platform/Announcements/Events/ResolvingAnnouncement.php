<?php

declare(strict_types=1);

namespace App\Modules\Platform\Announcements\Events;

/**
 * A single message a package wants put in front of staff.
 *
 * Shown twice, from one source: a band across the top of the dashboard,
 * and an icon beside the notification bell that opens the same words on
 * every other page. One event rather than two because "the same message"
 * is the requirement — two props would drift the day somebody edits one.
 *
 * Not a widget, on purpose. The widget grid is a closed list of keys that
 * dashboard.tsx renders one by one, and each viewer arranges it — so a
 * message that matters would sit wherever somebody happened to drag it,
 * or under a fold, or switched off. A band above the grid is seen without
 * competing with the columns for space.
 *
 * **Core knows nothing about what it says.** Title, body, the label on the
 * button and where the button goes all come from the listener. The first
 * caller is the hosted edition telling a free instance what a paid plan
 * would give it, which is commercial copy belonging to one offering and
 * has no place in the public repository.
 *
 * One at a time, deliberately. A dashboard that can accumulate banners
 * accumulates them, and the second one is what teaches people to skip the
 * first. A listener that finds one already set should leave it alone
 * rather than overwrite it.
 */
class ResolvingAnnouncement
{
    /**
     * @var array{title: string, body: string, action_label: string|null, action_url: string|null, tone: string}|null
     */
    public ?array $announcement = null;

    public function __construct(
        /** Whether the viewer is a staff account. */
        public readonly bool $isStaff,
    ) {}

    /**
     * Audience is declared by the listener and enforced here, rather than
     * each listener remembering to check `isStaff`.
     *
     * The first version of this refused clients outright, which was right
     * for the only message that existed — a hosted instance telling its
     * administrator about their plan. It stopped being right when a
     * message needed to reach the *clients* of a shared instance, and the
     * safe way to allow that is not to drop the guard: it is to make
     * every caller say who it is talking to, so a listener that forgets
     * reaches nobody rather than everybody.
     */
    public const AUDIENCE_STAFF = 'staff';

    public const AUDIENCE_CLIENTS = 'clients';

    /**
     * `audience` is required and has no default. A message for staff and
     * a message for the people they share with are different messages,
     * and a signature that let one be mistaken for the other would put
     * the mistake in the quiet direction.
     *
     * `tone` picks the accent the band is drawn in. Two values, because
     * two is what the difference is worth: `info` for something worth
     * knowing, `warning` for something worth acting on. Anything else
     * falls back to `info` rather than rendering unstyled.
     */
    public function show(
        string $title,
        string $body,
        string $audience,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        string $tone = 'info',
    ): void {
        if ($this->announcement !== null) {
            return;
        }

        // Silently ignored rather than thrown, and deliberately: a
        // listener aimed at the wrong audience should show nothing, not
        // break the page it was trying to decorate. An unrecognised value
        // reaches nobody for the same reason.
        $intended = match ($audience) {
            self::AUDIENCE_STAFF => $this->isStaff,
            self::AUDIENCE_CLIENTS => ! $this->isStaff,
            default => false,
        };

        if (! $intended) {
            return;
        }

        $this->announcement = [
            'title' => $title,
            'body' => $body,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'tone' => in_array($tone, ['info', 'warning'], true) ? $tone : 'info',
        ];
    }
}
