<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Platform\Localization\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform's SetLocale reads the session, which API requests don't have —
 * calling it here would throw. Same resolution order minus that step:
 * the token owner's own preference, then Accept-Language, then the app
 * default.
 *
 * Worth doing rather than defaulting everything to English: validation
 * messages are the one part of an API response written for a human, and a
 * 422 is usually read by the same person who set the locale in the UI.
 */
class SetApiLocale
{
    public function __construct(
        private readonly LocaleRegistry $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (! is_string($locale) || ! $this->locales->isEnabled($locale)) {
            $locale = $request->getPreferredLanguage($this->locales->preferenceOrder())
                ?? $this->locales->defaultLocale();
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
