<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/password', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Changing a password is how someone reacts to a session they think
        // is stolen, so it has to actually end that session. AuthenticateSession
        // (registered on the web group) compares each request's stored
        // password hash against the current one and logs out on mismatch;
        // this re-stamps the current session so the person doing the change
        // stays signed in while every other session falls over on its next
        // request.
        Auth::logoutOtherDevices($validated['password']);

        app(ActivityLogger::class)->log(Action::PasswordUpdated, $user);

        return back();
    }
}
