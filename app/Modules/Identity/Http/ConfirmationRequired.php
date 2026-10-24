<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Http\Middleware\RequirePasswordConfirmation;
use App\Modules\Identity\Reauthentication;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Prove it is you first", as the app's screens expect to hear it.
 *
 * An Inertia request gets the 423 that password-confirmation-dialog.tsx
 * turns into a dialog over the page; anything else is sent to the
 * confirm-password screen. Shared by the password.confirm middleware and
 * by the few writes that ask for the current password in their own form,
 * when the account has no password to type there (Reauthentication).
 */
final class ConfirmationRequired
{
    public static function response(Request $request): Response
    {
        if ($request->header('X-Inertia')) {
            $user = $request->user();

            return response()->json([
                'message' => 'Password confirmation required.',
                ...($user === null
                    ? ['has_password' => true, 'confirm_with' => null]
                    : app(Reauthentication::class)->screen($user)),
            ], 423, [RequirePasswordConfirmation::HEADER => 'required']);
        }

        return $request->isMethod('GET')
            ? redirect()->guest(route('password.confirm'))
            : redirect()->route('password.confirm');
    }

    /** @throws HttpResponseException */
    public static function throw(Request $request): never
    {
        throw new HttpResponseException(self::response($request));
    }
}
