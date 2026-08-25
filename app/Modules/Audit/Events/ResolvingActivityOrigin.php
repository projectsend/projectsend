<?php

declare(strict_types=1);

namespace App\Modules\Audit\Events;

use App\Models\User;
use App\Modules\Audit\ActivityOrigin;

/**
 * "This request has a signed-in actor and no personal access token — was
 * it really a browser?"
 *
 * Asked only in that one ambiguous case. A request carrying a Sanctum
 * token is the API, a request with nobody signed in is public or system,
 * and neither is in any doubt — so neither is offered here.
 *
 * The doubt exists because "no token" has always meant "a session", and
 * that stops being true the moment anything else can authenticate a
 * request. `ActivityOrigin` is a closed enum a package cannot extend, so
 * core has to publish both the case and this hook before a package can
 * say "that was mine". Without it a new credential would be recorded as
 * a person clicking in a browser — silently, and in the one table whose
 * whole purpose is answering "did I do that, or did something acting for
 * me?"
 *
 * Set `$origin` only if you recognise the credential on the current
 * request. Leaving it null means "not mine", which is the honest answer
 * for every listener that is not looking at its own guard.
 *
 * Listened to by *string* class name from a package, same as every other
 * hook here — see docs/extension-points-architecture.md.
 */
final class ResolvingActivityOrigin
{
    /**
     * What actually authenticated this request. Null until a listener
     * claims it, after which core stops assuming a browser session.
     */
    public ?ActivityOrigin $origin = null;

    /**
     * What to show beside the entry — the name of the connector or
     * application acting, not the person. Snapshotted into the same
     * column an API token's name goes in, for the same reason: revoking
     * the credential must not leave the entry pointing at nothing.
     */
    public ?string $credentialName = null;

    public function __construct(
        public readonly User $actor,
    ) {}
}
