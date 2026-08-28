<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Capabilities\CapabilityUnavailable;
use App\Support\ApiSurface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level capability enforcement: `->middleware('capability:users.manage')`.
 *
 * API requests get a machine-readable 403; web requests get a 404 so
 * unavailable features are absent, not teased.
 *
 * Which of the two a request is comes from the route (see ApiSurface), not
 * from its Accept header. Whether an endpoint exists in this edition is a
 * property of the installation; deciding it from what the caller is
 * willing to parse answered the same API route 403 or 404 depending on
 * nothing but a header, and routes/api.php promises the 403.
 *
 * The API half throws CapabilityUnavailable rather than returning a body,
 * so the refusal goes through ProblemDetails like every other API error
 * instead of being the one response shaped differently from the rest.
 */
class EnsureCapability
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
    ) {}

    public function handle(Request $request, Closure $next, string $capabilityKey): Response
    {
        $capability = Capability::from($capabilityKey);

        if ($this->capabilities->has($capability)) {
            return $next($request);
        }

        if (ApiSurface::matches($request)) {
            throw new CapabilityUnavailable($capability, $this->capabilities->edition());
        }

        abort(404);
    }
}
