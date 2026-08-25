<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Support\WriteSafeRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account loses access immediately, not at next login:
 * any open session is terminated on the following request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return WriteSafeRedirect::apply($request, redirect()->route('login')->withErrors([
                'email' => __('Your account has been deactivated.'),
            ]));
        }

        return $next($request);
    }
}
