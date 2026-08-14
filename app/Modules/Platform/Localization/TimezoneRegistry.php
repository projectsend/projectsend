<?php

declare(strict_types=1);

namespace App\Modules\Platform\Localization;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use DateTimeZone;

/**
 * Which clock a given viewer reads the application by.
 *
 * The server stays UTC — `config/app.php` is never touched, and nothing
 * here calls `date_default_timezone_set()`, which is precisely how v1 did
 * it and precisely why v1 could only ever have one timezone for everybody.
 * Every timestamp is stored and compared in UTC; a zone is applied at the
 * two edges where it means something: rendering a date, and answering
 * "which calendar day was that?".
 *
 * Resolution runs preference-first, same shape as LocaleRegistry: the
 * signed-in account's own choice, then the installation's setting, then
 * APP_TIMEZONE, then UTC. Anonymous visitors — public listing pages, share
 * links — have no account, so they get the installation's setting, which
 * is the v1 behaviour for everyone the app knows nothing about.
 *
 * Every step validates before it accepts. tzdata drops and renames
 * identifiers between releases, so a zone that was real when it was saved
 * can stop existing under the host's next PHP update; handed one,
 * `DateTimeZone` throws, and a throw inside a date formatter takes out the
 * whole response. Falling through to the next candidate renders the right
 * page in the wrong zone, which is a much smaller problem — the same
 * trade-off `intl-locale.ts` documents on the frontend after a bad locale
 * tag blanked the dashboard.
 */
class TimezoneRegistry
{
    /**
     * @var list<string>|null
     */
    private ?array $identifiers = null;

    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * Every zone this PHP build knows, including UTC itself.
     *
     * Deliberately not filtered down to the ten continent groups the way
     * v1's picker was: v1 could not offer `UTC` in its own dropdown, so an
     * install seeded with it had a value it was unable to re-select. The
     * grouping that list was reaching for belongs to the picker, and lives
     * in grouped() below.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->identifiers ??= DateTimeZone::listIdentifiers();
    }

    public function isValid(string $timezone): bool
    {
        return in_array($timezone, $this->all(), true);
    }

    /**
     * The zone to fall back on when the viewer has expressed no usable
     * preference of their own — and the only zone anonymous visitors ever
     * see.
     */
    public function default(): string
    {
        $configured = $this->settings->get(Setting::Timezone);

        // Empty means the operator has never chosen one here, so the
        // environment still has the say it always had.
        if (! is_string($configured) || $configured === '') {
            $configured = (string) config('app.timezone');
        }

        return $this->isValid($configured) ? $configured : 'UTC';
    }

    public function resolve(?User $user): string
    {
        $preference = $user?->timezone;

        return is_string($preference) && $this->isValid($preference)
            ? $preference
            : $this->default();
    }

    /**
     * The identifier list shaped for the picker, sorted, each carrying
     * the offset it is on *today*.
     *
     * The label keeps the region in it ("Europe / Madrid") rather than
     * splitting the list into region groups. The picker searches labels,
     * so this is what lets someone type either "Europe" or "Madrid" and
     * get there; grouping would have hidden the region from the one box
     * they are typing into.
     *
     * The offset is computed rather than stored because it is not a
     * property of the zone — half the world moves twice a year, so
     * "Europe/Madrid is UTC+01:00" is true in January and wrong in July.
     * Recomputing per request keeps the hint honest and costs nothing
     * next to the page it is rendered on.
     *
     * @return list<array{value: string, label: string, offset: string}>
     */
    public function options(): array
    {
        $now = new \DateTimeImmutable('now');

        return array_map(fn (string $identifier): array => [
            'value' => $identifier,
            'label' => str_replace(['/', '_'], [' / ', ' '], $identifier),
            'offset' => $this->offsetLabel($identifier, $now),
        ], $this->all());
    }

    /**
     * "UTC-03:00" — a plain ASCII hyphen, not a minus sign, so the string
     * survives a copy-paste into a search box.
     */
    private function offsetLabel(string $identifier, \DateTimeImmutable $at): string
    {
        $seconds = (new DateTimeZone($identifier))->getOffset($at);
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('UTC%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
