<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The write path for the silent browser detection that runs on first
 * sign-in (timezone-detector.tsx), which needs somewhere to post a zone
 * without a form around it.
 *
 * The profile screen does *not* come through here — it saves the timezone
 * with the rest of the profile, so that page keeps one Save button rather
 * than growing a second one for a single field. Both paths validate
 * through Rules::timezone(), so the format is defined once even though the
 * two writes are separate.
 *
 * Unlike the locale, nothing is put in the session: the whole point of
 * persisting to the column is that the resolved zone is the same on every
 * device this account signs in from, and a guest has no preference to
 * store in the first place.
 */
class TimezoneController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', ...Rules::timezone()],
        ]);

        $request->user()?->update(['timezone' => $validated['timezone']]);

        return back();
    }
}
