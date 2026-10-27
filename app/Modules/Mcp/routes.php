<?php

declare(strict_types=1);

use App\Modules\Mcp\OAuth\Http\Controllers\AuthorizeController;
use App\Modules\Mcp\OAuth\Http\Controllers\ConnectionsController;
use App\Modules\Mcp\OAuth\Http\Controllers\DiscoveryController;
use App\Modules\Mcp\OAuth\Http\Controllers\RegisterController;
use App\Modules\Mcp\OAuth\Http\Controllers\RevokeController;
use App\Modules\Mcp\OAuth\Http\Controllers\TokenController;
use App\Modules\Mcp\OAuth\Http\Middleware\AllowCrossOrigin;
use Illuminate\Support\Facades\Route;

/*
 * The authorization server of the AI connector (design §2.2). Every
 * route is registered always and refuses per request, like the rest of
 * this module. The programmatic endpoints carry no session and no
 * CSRF: a client program calls them. The consent and the settings page
 * are the person's, in the browser, behind the app's own sign-in.
 */

Route::middleware([AllowCrossOrigin::class])->group(function (): void {
    Route::get('/.well-known/oauth-protected-resource/mcp', [DiscoveryController::class, 'protectedResource'])
        ->name('mcp.oauth.protected-resource');
    // Clients try the root form too, in that order.
    Route::get('/.well-known/oauth-protected-resource', [DiscoveryController::class, 'protectedResource'])
        ->name('mcp.oauth.protected-resource.root');
    Route::get('/.well-known/oauth-authorization-server', [DiscoveryController::class, 'authorizationServer'])
        ->name('mcp.oauth.authorization-server');

    // An open write, treated as one: ten an hour from one address.
    Route::post('/oauth/mcp/register', RegisterController::class)
        ->middleware('throttle:10,60,mcp-register')
        ->name('mcp.oauth.register');
    Route::post('/oauth/mcp/token', TokenController::class)
        ->middleware('throttle:60,1,mcp-token')
        ->name('mcp.oauth.token');
    Route::post('/oauth/mcp/revoke', RevokeController::class)
        ->middleware('throttle:60,1,mcp-token')
        ->name('mcp.oauth.revoke');
    Route::options('/oauth/mcp/{endpoint}', fn () => response()->noContent())
        ->whereIn('endpoint', ['register', 'token', 'revoke']);
});

Route::middleware(['web', 'auth'])->group(function (): void {
    // Showing the screen can fetch a client_id URL somebody else chose, so
    // it is not a free way to make the server send requests.
    Route::get('/oauth/mcp/authorize', [AuthorizeController::class, 'show'])
        ->middleware('throttle:30,1,mcp-authorize')
        ->name('mcp.oauth.authorize');
    // A password is typed here: the same allowance as the app's own
    // confirm-password screen.
    Route::post('/oauth/mcp/authorize', [AuthorizeController::class, 'store'])
        ->middleware('throttle:6,1,mcp-consent')
        ->name('mcp.oauth.authorize.store');

    Route::get('/settings/ai-connections', [ConnectionsController::class, 'index'])
        ->name('mcp.connections.index');
    Route::delete('/settings/ai-connections/{connection}', [ConnectionsController::class, 'destroy'])
        ->whereNumber('connection')
        ->name('mcp.connections.destroy');
});
