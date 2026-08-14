<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Localization\LocaleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request, LocaleRegistry $locales): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales->enabled())],
        ]);

        $request->session()->put('locale', $validated['locale']);

        // Persisted so queued emails (no session to read) render in the
        // recipient's actual preferred language, not the app default.
        $request->user()?->update(['locale' => $validated['locale']]);

        return back();
    }
}
