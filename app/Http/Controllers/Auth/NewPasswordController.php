<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Ldap\LdapAuthenticator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function __construct(
        private readonly LdapAuthenticator $ldap,
    ) {}

    /**
     * Show the password reset page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'expired' => $this->linkIsSpent(
                (string) $request->string('email'),
                (string) $request->route('token'),
            ),
        ]);
    }

    /**
     * Whether this link is one store() is certain to refuse.
     *
     * The scaffolding renders the form without looking at the token, so an
     * expired link asked for a new password, asked for it a second time to
     * confirm, and only then answered "this password reset token is
     * invalid" — naming a word nobody outside the code knows, after the
     * work rather than before it. Reset links last an hour and people open
     * them late; that is ordinary, not an error to be scolded for.
     *
     * store() still validates and remains the rule. This is the screen
     * being honest a minute earlier.
     *
     * **An address that is missing or belongs to nobody is not an expired
     * link, and is drawn as the form was before.** Two reasons, and the
     * second is the one that matters: a page that said "expired" for a
     * real address and something else for an unknown one would answer
     * whether an account exists here, to anybody who typed a guess — the
     * property /forgot-password already protects by saying "a link will be
     * sent if the account exists".
     */
    private function linkIsSpent(string $email, string $token): bool
    {
        if ($email === '' || $token === '') {
            return false;
        }

        $broker = Password::broker();
        $user = $broker->getUser(['email' => $email]);

        if ($user === null) {
            return false;
        }

        return ! $broker->tokenExists($user, $token);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                // A directory account's password lives in the directory and
                // the local hash is not consulted at all, which is what
                // isDirectoryAccount() means. Writing one here reported
                // success and changed nothing anybody could use -- including
                // when the directory it points at is gone, which is exactly
                // when somebody reaches for a reset.
                //
                // Refused here rather than where the link is asked for: that
                // endpoint answers "A reset link will be sent if the account
                // exists" to everybody on purpose, and a refusal there would
                // tell a stranger both that an address is an account and how
                // it signs in. By this point the caller holds a token that
                // was emailed to the address, so the explanation reaches the
                // account holder and nobody else.
                //
                // Throwing before the write also leaves the token unspent:
                // PasswordBroker deletes it after the callback returns, so
                // the link still works if an administrator converts the
                // account in the meantime.
                if ($this->ldap->isDirectoryAccount($user)) {
                    throw ValidationException::withMessages([
                        'email' => [__('This account signs in through your directory, so its password is not set here. Ask an administrator if you cannot sign in.')],
                    ]);
                }

                $attributes = [
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ];

                // `social` records that the account came into existence
                // without anybody choosing a password, which AuthSource
                // states outright -- along with "a social account may later
                // set a real password". This is that moment, and nothing
                // else in the application writes it: the Connected accounts
                // screen reads `auth_source === Local` as
                // `has_local_password`, so without this line its refusal
                // goes on asking for a password that has just been set.
                //
                // The two branches of this method are the same rule read
                // twice: `social` is where the account came from and the
                // hash here is what signs it in, so choosing one settles it;
                // `ldap` is the authentication path itself, so nothing
                // chosen here settles anything.
                if ($user->auth_source === AuthSource::Social) {
                    $attributes['auth_source'] = AuthSource::Local;
                }

                $user->forceFill($attributes)->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status == Password::PasswordReset) {
            return to_route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
