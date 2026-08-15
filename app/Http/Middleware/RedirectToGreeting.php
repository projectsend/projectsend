<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Platform\Onboarding\InstallationWelcome;
use App\Modules\Platform\Updates\UpdateWelcome;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes the administrator to whichever page is waiting for them: the
 * getting-started list on a new installation, or the what's-new page the
 * first time they arrive after an update.
 *
 * Attached to the dashboard alone rather than to the whole web group.
 * The dashboard is where a login lands and where the sidebar's logo
 * points, so it catches the arrival either way — including the common
 * case on a self-hosted server, where the person who ran update.sh was
 * already signed in and never logs in at all. Applying it to every
 * request instead would mean intercepting somebody mid-download or
 * mid-upload to congratulate them, which is a worse trade than missing
 * an administrator who happens to bookmark /files.
 *
 * One middleware for both because they are the same interruption, and a
 * second one on the same route would have to know about the first to
 * avoid arguing with it. Installation wins: the two cannot both be
 * waiting in practice — an update marker is only raised for an
 * installation that already existed — but if they ever were, somebody
 * who has just installed this does not need release notes for a version
 * they never ran.
 */
class RedirectToGreeting
{
    public function __construct(
        private readonly InstallationWelcome $installation,
        private readonly UpdateWelcome $update,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // GET only: a redirect swallows a POST body, and nothing that
        // writes should ever be answered with a greeting.
        if ($request->isMethod('GET')) {
            $user = $request->user();

            if ($user !== null) {
                if ($this->installation->isWaitingFor($user)) {
                    return redirect()->route('system.getting-started');
                }

                if ($this->update->isWaitingFor($user)) {
                    return redirect()->route('system.whats-new');
                }
            }
        }

        return $next($request);
    }
}
