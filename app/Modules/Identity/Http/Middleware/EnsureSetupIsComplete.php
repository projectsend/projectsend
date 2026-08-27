<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Models\User;
use App\Modules\Identity\UserType;
use App\Support\WriteSafeRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fresh install has no staff user; until one exists every request is
 * sent to the first-run setup screen. The database is the only source of
 * truth — no install flags. Client accounts do not count: setup is about
 * having an administrator.
 *
 * Trashed staff count. "Has this installation been set up" is not the
 * same question as "does it have a working administrator right now", and
 * only the first one belongs here: a soft-deleted staff row is still
 * evidence that setup happened, and an installation that has lost its
 * last administrator needs a recovery path, not a stranger filling in
 * the first-run form. Deleting a staff account is guarded against
 * reaching zero (StaffAccounts::guardLastAdministrator), so this is the
 * second lock rather than the first — but the first one is asked at five
 * separate doors, and this one is asked once.
 */
class EnsureSetupIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup', 'setup.store', 'locale.update')) {
            return $next($request);
        }

        if (User::query()->withTrashed()->where('type', UserType::Staff)->exists()) {
            return $next($request);
        }

        return WriteSafeRedirect::apply($request, redirect()->route('setup'));
    }
}
