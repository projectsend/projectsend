<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\AuthSource;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `password.confirm`, answered in place for the app's own screens.
 *
 * The framework's version redirects to the confirm-password screen and
 * relies on the "intended" URL to come back. For a GET that works. For
 * the writes this guards it cannot: Redirector::guest() only remembers
 * the exact URL of a GET, so a POST comes back to the page it was sent
 * from, freshly rendered, and whatever was typed into the form -- a
 * token's name and scopes, a release reason -- is gone, along with the
 * action itself.
 *
 * So an Inertia request gets a 423 instead, which the browser turns into
 * a password dialog over the page it is on (password-confirmation-dialog.tsx).
 * Nothing navigates, the form keeps its state, and once the password is
 * proved the same request is sent again. The check itself is the
 * framework's, unchanged: this only decides what the refusal looks like.
 * Anything else -- a plain form post, a JSON client -- is answered
 * exactly as before.
 */
class RequirePasswordConfirmation extends RequirePassword
{
    /**
     * Marks the 423 as this refusal and not any other, so the browser does
     * not open a password dialog in answer to something else.
     */
    public const HEADER = 'X-Password-Confirmation';

    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null)
    {
        // Middleware parameters arrive as strings ("password.confirm:,300").
        $timeout = $passwordTimeoutSeconds === null || $passwordTimeoutSeconds === '' ? null : (int) $passwordTimeoutSeconds;

        if ($request->header('X-Inertia') && $this->shouldConfirmPassword($request, $timeout)) {
            return $this->inertiaRefusal($request);
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }

    private function inertiaRefusal(Request $request): Response
    {
        $user = $request->user();

        return $this->responseFactory->json([
            'message' => 'Password confirmation required.',
            // The same question the confirm-password screen asks, and see
            // there for why it is Social and not "anything but Local": an
            // account provisioned by a provider has no password to type,
            // and the dialog has to offer it a way to set one instead.
            'has_password' => $user !== null && $user->auth_source !== AuthSource::Social,
        ], 423, [self::HEADER => 'required']);
    }
}
