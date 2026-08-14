<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Models\User;
use App\Modules\Identity\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fresh install has no staff user; until one exists every request is
 * sent to the first-run setup screen. The database is the only source of
 * truth — no install flags. Client accounts do not count: setup is about
 * having an administrator.
 */
class EnsureSetupIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup', 'setup.store', 'locale.update')) {
            return $next($request);
        }

        if (User::query()->where('type', UserType::Staff)->exists()) {
            return $next($request);
        }

        return redirect()->route('setup');
    }
}
