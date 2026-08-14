<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Localization\LocaleRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which of the installed translation catalogues the language switcher
 * offers (staff-only). Installing a pack is a matter of dropping a file
 * into lang/; this is where an administrator decides whether their clients
 * are actually shown it.
 */
class LanguageSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly LocaleRegistry $locales,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('system/settings/languages', [
            'installed' => $this->locales->installed(),
            'enabled' => $this->locales->enabled(),
            'default_locale' => $this->locales->defaultLocale(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled_locales' => ['present', 'array'],
            'enabled_locales.*' => ['string', Rule::in($this->locales->installed())],
            'default_locale' => ['required', 'string', Rule::in($this->locales->installed())],
        ]);

        // English is not optional — it is the translation key rather than a
        // catalogue, and an install with no language at all is not a state
        // this screen is allowed to produce. The frontend locks its
        // checkbox; this is the half that actually enforces it.
        $enabled = array_values(array_unique([...$validated['enabled_locales'], 'en']));

        sort($enabled);

        // Checked against the list being saved, not the one currently
        // stored: switching a language off and making it the default in the
        // same save has to fail rather than half-apply.
        if (! in_array($validated['default_locale'], $enabled, true)) {
            throw ValidationException::withMessages([
                'default_locale' => __('The default language has to be one of the languages you are offering.'),
            ]);
        }

        $this->settings->set(Setting::EnabledLocales, $enabled);
        $this->settings->set(Setting::DefaultLocale, $validated['default_locale']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'languages']);

        return back();
    }
}
