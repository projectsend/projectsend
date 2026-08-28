<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Identity\PasswordVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/confirm-password');
    }

    /**
     * Confirm the user's password.
     *
     * Through PasswordVerification, so this asks the same question the
     * sign-in form asks: is this the account's password, from wherever
     * that account's password lives. Checking only the local hash refused
     * every directory-provisioned account the password it actually has --
     * their local hash is a Str::password(64) nobody has ever seen -- and
     * this screen stands in front of enrolling in two-factor, so those
     * accounts could not enrol at all.
     */
    public function store(Request $request, PasswordVerification $passwords): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if (! $passwords->verify($user, (string) $request->string('password'))) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
