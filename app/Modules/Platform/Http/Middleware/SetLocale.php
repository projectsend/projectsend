<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Localization\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale: authenticated user preference (once users
 * carry one), then session, then the Accept-Language header, then the app
 * default the administrator chose. Only locales an administrator has
 * enabled are honoured — a
 * preference pointing at a language that has since been switched off falls
 * through to the next candidate rather than erroring.
 */
class SetLocale
{
    public function __construct(
        private readonly LocaleRegistry $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (! is_string($locale) || ! $this->locales->isEnabled($locale)) {
            $locale = $request->session()->get('locale');
        }

        if (! is_string($locale) || ! $this->locales->isEnabled($locale)) {
            $locale = $request->getPreferredLanguage($this->locales->preferenceOrder())
                ?? $this->locales->defaultLocale();
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
