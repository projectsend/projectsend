<?php

declare(strict_types=1);

namespace App\Modules\Files\Editing;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Localization\DateInput;
use Carbon\Carbon;

/**
 * Reading and writing a file's expiry in the zone of whoever is looking.
 *
 * The rule itself — a posted day means the end of that day where the
 * setter lives, and a form posts back what asShown() gave it — is
 * DateInput's, shared with a client account's expiry. This stays as the
 * file-shaped door onto it.
 *
 * Was three private copies — the staff editor, the API, and the client
 * portal — of which the API's was the only one that could read a
 * timestamp.
 */
class FileExpiry
{
    public function __construct(
        private readonly DateInput $dates,
    ) {}

    /**
     * The stored instant as the calendar day a form should show, in the
     * viewer's zone. Null when the file never expires.
     */
    public function asShown(File $file, ?User $viewer): ?string
    {
        return $this->dates->asShown($file->expires_at, $viewer);
    }

    /**
     * The instant a submitted value actually names. See DateInput::instant().
     */
    public function instant(?string $value, ?User $setter): ?Carbon
    {
        return $this->dates->instant($value, $setter);
    }
}
