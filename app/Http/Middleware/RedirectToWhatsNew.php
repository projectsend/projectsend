<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Platform\Updates\UpdateWelcome;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes the administrator to the welcome page the first time they arrive
 * after an update.
 *
 * Attached to the dashboard alone rather than to the whole web group.
 * The dashboard is where a login lands and where the sidebar's logo
 * points, so it catches the arrival either way — including the common
 * case on a self-hosted server, where the person who ran update.sh was
 * already signed in and never logs in at all. Applying it to every
 * request instead would mean intercepting somebody mid-download or
 * mid-upload to congratulate them, which is a worse trade than missing
 * an administrator who happens to bookmark /files.
 */
class RedirectToWhatsNew
{
    public function __construct(private readonly UpdateWelcome $welcome) {}

    public function handle(Request $request, Closure $next): Response
    {
        // GET only: a redirect swallows a POST body, and nothing that
        // writes should ever be answered with a greeting.
        if ($request->isMethod('GET')) {
            $user = $request->user();

            if ($user !== null && $this->welcome->isWaitingFor($user)) {
                return redirect()->route('system.whats-new');
            }
        }

        return $next($request);
    }
}
