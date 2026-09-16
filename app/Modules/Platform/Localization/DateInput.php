<?php

declare(strict_types=1);

namespace App\Modules\Platform\Localization;

use App\Models\User;
use Carbon\Carbon;

/**
 * A stored instant, read and written as a calendar day in the zone of
 * whoever is looking.
 *
 * Every expiry in the application is stored as an instant, and every
 * person sets one with a date input. "The 12th" means the end of the 12th
 * where *they* live — otherwise something asked to expire on the 12th dies
 * partway through the 11th for anyone west of Greenwich, and gives anyone
 * east of it most of a day nobody promised.
 *
 * The two halves have to agree, which is the whole reason they sit
 * together: a form is rendered with asShown() and posts the same string
 * back untouched with every other edit, so a caller compares against
 * asShown() to tell "the editor changed the date" from "the editor changed
 * something else and the date came along for the ride". Re-deriving on
 * every save instead moves the instant by the difference between two
 * people's zones each time somebody edits anything.
 *
 * Shared by a file's expiry (FileExpiry) and a client account's.
 */
class DateInput
{
    public function __construct(
        private readonly TimezoneRegistry $timezones,
    ) {}

    /**
     * The stored instant as the calendar day a form should show, in the
     * viewer's zone. Null when there is no instant.
     */
    public function asShown(?Carbon $instant, ?User $viewer): ?string
    {
        return $instant?->copy()->setTimezone($this->timezones->resolve($viewer))->toDateString();
    }

    /**
     * The instant a submitted value actually names.
     *
     * A bare `YYYY-MM-DD` is a calendar day and means the end of it where
     * the setter is — what every date input posts. Anything carrying a
     * time is an instant somebody named on purpose and is stored as it
     * arrives: the API can express a moment, and a date input cannot.
     */
    public function instant(?string $value, ?User $setter): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? LocalDay::end($value, $this->timezones->resolve($setter))
            : Carbon::parse($value);
    }
}
