<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\TwoFactor\TwoFactorEnforcement;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\WriteSafeRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the installation enforces two-factor authentication for the
 * user's type, an un-enrolled user can only reach the 2FA setup screen
 * (and the exits: logout, locale) until they enable it.
 */
class EnforceTwoFactor
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        $value = $this->settings->get(Setting::TwoFactorEnforcement);

        $enforcement = (is_string($value) ? TwoFactorEnforcement::tryFrom($value) : null)
            ?? TwoFactorEnforcement::None;

        if (! $enforcement->appliesTo($user->type)) {
            return $next($request);
        }

        // password.confirm is on this list because the two-factor mutation
        // routes now require it: without the exemption, enrolling would
        // redirect to the confirm-password screen, which this middleware
        // would redirect straight back to two-factor.show — a loop that
        // locks the user out of the only exit.
        if ($request->routeIs('two-factor.*', 'password.confirm', 'logout', 'locale.update')) {
            return $next($request);
        }

        return WriteSafeRedirect::apply($request, redirect()->route('two-factor.show')->with('two_factor_enforced_notice', true));
    }
}
