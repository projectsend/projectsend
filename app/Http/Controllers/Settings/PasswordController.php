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
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\ValidationException;
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

        // An account that signs in through a provider has no password to
        // prove, so this screen cannot ask it for one, and the session
        // alone is not enough: a stolen session that could choose the
        // password became the account for good (GHSA-4r8h-mwfm-f5f4). Its
        // first password comes from a link emailed to its own address,
        // which sendLink() asks for and NewPasswordController completes.
        if ($user->auth_source === AuthSource::Social) {
            throw ValidationException::withMessages([
                'password' => __('Ask for a link by email to set your first password.'),
            ]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

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

    /**
     * Email an account that signs in through a provider a link to set its
     * first password.
     *
     * The ordinary reset link, to the account's own address: whoever sets
     * the password has to read that inbox, which a stolen session cannot
     * do. Opening it completes through NewPasswordController, which turns
     * the account local, and changing the password signs out every session
     * holding the old one, the one that asked for the link included.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        // An account with a password uses the form above; a directory
        // account's password is not this installation's to set.
        abort_unless($user->auth_source === AuthSource::Social, 403);

        $status = PasswordBroker::broker()->sendResetLink(['email' => $user->email]);

        // Their own account, so being told to wait reveals nothing.
        if ($status === PasswordBroker::ResetThrottled) {
            throw ValidationException::withMessages([
                'link' => __('A link was sent a moment ago. Check your email, or try again in a minute.'),
            ]);
        }

        return back()->with('status', 'password-link-sent');
    }
}
