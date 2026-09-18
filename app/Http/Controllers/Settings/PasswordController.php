<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\AuthSource;
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
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('settings/password', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // Whether there is a password here at all. An account
            // provisioned by a provider has a generated one nobody was
            // ever told, so asking for "your current password" asks for
            // something that does not exist — and until this, that was
            // the only door to a password, which is the only way to reach
            // two-factor enrolment. See update().
            'has_local_password' => $user->auth_source === AuthSource::Local,
            // A directory's password is not this installation's to change.
            'managed_elsewhere' => $user->auth_source === AuthSource::Ldap,
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        // An LDAP account's password lives in the directory. Changing the
        // hash here would change nothing anybody signs in with, so the
        // honest answer is to refuse rather than to appear to work.
        abort_if($user->auth_source === AuthSource::Ldap, 403);

        $setsFirstPassword = $user->auth_source === AuthSource::Social;

        $validated = $request->validate([
            // Not asked of an account that has never had one: it signs in
            // through a provider, and its stored hash is a generated
            // string nobody has seen. Asking anyway left those accounts
            // with no way to set a password — and so no way to enrol in
            // two-factor, which an installation can make compulsory.
            'current_password' => $setsFirstPassword ? ['nullable'] : ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $attributes = ['password' => Hash::make($validated['password'])];

        // The same line NewPasswordController writes when a provider
        // account resets its password, for the same reason: the hash is
        // now what signs this account in, and `has_local_password` is read
        // off this column all over the settings screens.
        if ($setsFirstPassword) {
            $attributes['auth_source'] = AuthSource::Local;
        }

        // forceFill, not update(): `auth_source` is guarded, so a mass
        // assignment drops it silently — which left the account still
        // reading as passwordless after it had a password.
        $user->forceFill($attributes)->save();

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
