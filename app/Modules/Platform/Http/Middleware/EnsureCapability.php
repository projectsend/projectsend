<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Capabilities\CapabilityUnavailable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level capability enforcement: `->middleware('capability:users.manage')`.
 *
 * API requests get a machine-readable 403; web requests get a 404 so
 * unavailable features are absent, not teased.
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

        if ($request->expectsJson()) {
            throw new CapabilityUnavailable($capability, $this->capabilities->edition());
        }

        abort(404);
    }
}
