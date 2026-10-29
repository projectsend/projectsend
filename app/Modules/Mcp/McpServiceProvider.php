<?php

declare(strict_types=1);

namespace App\Modules\Mcp;

use App\Modules\Audit\ActivityOrigin;
use App\Modules\Audit\Events\ResolvingActivityOrigin;
use App\Modules\Mcp\Http\Controllers\PartUploadController;
use App\Modules\Mcp\Http\Middleware\EnsureConnectorCapability;
use App\Modules\Mcp\Http\Middleware\RequireBearerToken;
use App\Modules\Mcp\OAuth\Clients\PublicAddress;
use App\Modules\Mcp\OAuth\Console\PruneCommand;
use App\Modules\Mcp\Support\ConnectorPlan;
use App\Modules\Mcp\Support\McpContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

/**
 * The MCP server an AI assistant connects to. Both editions, behind the
 * `ai.connector` capability, which a hosted plan can subtract.
 *
 * One endpoint, `/mcp`, on every instance, and one set of nineteen tools
 * behind it (docs/mcp.md). What a given assistant may do is decided per
 * call by the token that authenticated it and by the app's own policies — never by hiding
 * tools (only an API token's abilities prune the list, because no
 * conversation can change them), and never by a second permission
 * system. What a client account lacks is listed and answered with the gate.
 *
 * ## One door
 *
 * The route is guarded by `auth:sanctum`, the app's own token guard.
 * That is deliberate and it is most of the design (site
 * docs/mcp-oauth-design.md): a personal API token minted on Settings →
 * API tokens and a token minted by the OAuth consent are the same kind
 * of thing to the app — a row in `personal_access_tokens` with a name,
 * abilities and an expiry — so expiry, revocation, the deactivated-
 * account check and the audit of creation and revocation are all
 * already written. The connector adds none of them and can break none
 * of them.
 *
 * ## Plans
 *
 * Everything this module says about plans comes from ConnectorPlan,
 * which asks a hosted edition through ResolvingConnectorPlan. On a
 * self-hosted installation nobody answers: only staff connect, and no
 * answer mentions a paid plan.
 *
 * ## Outside the API's prefix, on purpose
 *
 * `/mcp` is not under `/api/v1` because the API is staff-only by
 * middleware, and where clients may connect they have to reach this.
 * Every call still answers to the person's live permissions inside each
 * tool.
 */
class McpServiceProvider extends ServiceProvider
{
    public const ENDPOINT = '/mcp';

    /** The signed PUT for an upload's parts; `Uploads::sign()` signs URLs for it. */
    public const PART_ROUTE = 'mcp.uploads.parts.put';

    public function register(): void
    {
        // One answer about plans per request, asked the first time it is needed.
        $this->app->scoped(ConnectorPlan::class);

        // One resolver for the process, so a test can hand in a fake one
        // and never touch DNS.
        $this->app->singleton(PublicAddress::class, static fn (): PublicAddress => new PublicAddress);
    }

    public function boot(): void
    {
        // Registered unconditionally and gated per request: a capability that changes takes effect
        // on the next call, and a route that exists and refuses is
        // easier to diagnose than one that silently is not there.
        Mcp::web(self::ENDPOINT, ProjectSendServer::class)
            // `laravel/mcp` adds a bare `error="invalid_token"` header to
            // every 401 and would overwrite ours, which carries the
            // resource metadata a client discovers the server from.
            ->withoutMiddleware(AddWwwAuthenticateHeader::class)
            ->middleware([
                // First, so it can turn the guard's refusal into the 401 an
                // MCP client knows how to act on.
                RequireBearerToken::class,
                'auth:sanctum',
                // The app's own: a deactivated account's token stops
                // working here the same day it stops working on the API.
                'api-active',
                // A personal API token here is held to its own abilities, as
                // on the API. A connection's token is left alone (it carries
                // scopes, not permission keys); see LimitPermissionsToToken.
                'token-ceiling',
                EnsureConnectorCapability::class,
                // Its own named bucket, so it never shares an allowance with
                // whatever else forgot to name theirs. Per token, because the
                // limit is the plan's (decision 14) and a token is the unit.
                'throttle:'.((int) config('mcp-connector.calls_per_minute', 120)).',1,mcp',
            ]);

        // Where the bytes of a part go. Signed, and nothing else: the
        // URL is the credential, as on the app's own upload page. Its
        // own throttle bucket, generous, because a large file is many
        // parts and the caller is the assistant the person authorised.
        Route::put('/mcp/uploads/{session}/parts/{part}', PartUploadController::class)
            ->middleware(['signed', 'throttle:600,1,mcp-parts'])
            ->whereNumber('part')
            ->name(self::PART_ROUTE);

        // The authorization server: its routes, and the daily sweep of
        // what its tables no longer need (design §2 and §5.4).
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('mcp:prune')->daily();
        });

        // Who is acting, for the activity log. The logger asks this only
        // when the actor carries no token, which the server arranges
        // (see ProjectSendServer::boot); the answer is "AI assistant",
        // with the connector's name beside the entry, so a person can
        // tell what they did from what their assistant did for them.
        Event::listen(ResolvingActivityOrigin::class, static function (ResolvingActivityOrigin $event): void {
            $account = McpContext::account();

            if ($account === null) {
                return;
            }

            $event->origin = ActivityOrigin::Mcp;
            $event->credentialName = $account->connector->name;
        });
    }
}
