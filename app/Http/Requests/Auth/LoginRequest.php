<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Modules\Identity\Ldap\LdapProvisioner;
use App\Modules\Identity\PasswordVerification;
use App\Modules\Identity\SignIn;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Support\Rules;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Deliberately here rather than inside authenticate(): rules
            // run first, so a bot never reaches the credential check, and
            // an honest visitor whose token expired never burns one of
            // their five attempts.
            ...Rules::captcha(CaptchaForm::Login),
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Returns true when the credentials are valid but the account has
     * two-factor authentication enabled: no session is created and the
     * pending user id is stored for the challenge step.
     *
     * Three phases, deliberately in this order:
     *
     *  1. Identify and verify — is this password correct, from any source
     *     this installation accepts?
     *  2. Account state — is this account allowed to sign in at all?
     *  3. Two-factor, then the session.
     *
     * Splitting 1 from 2 is what lets a directory be consulted without
     * restating anything. The property that account state is only revealed
     * to somebody holding the right password now falls out of the ordering,
     * rather than being re-established by a second Auth::validate() inside
     * each branch — and rate limiting covers every credential source,
     * because every failure funnels through one refusal.
     *
     * @throws ValidationException
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->string('email'))->first();

        // A directory identity with no local account yet. Returns null
        // unless LDAP is on, auto-provisioning is on, and the bind
        // succeeds — so an unknown email costs nothing on an installation
        // that does not use a directory.
        if ($user === null) {
            $user = app(LdapProvisioner::class)->provision(
                (string) $this->string('email'),
                (string) $this->string('password'),
            );
        }

        $verified = $this->verifyCredentials($user);

        if ($verified === null) {
            $this->failWithInvalidCredentials();
        }

        $signIn = app(SignIn::class);

        $refusal = $signIn->refusalReason($verified);

        if ($refusal !== null) {
            // Reached only with correct credentials, so this reveals the
            // account state to its owner and to nobody else.
            throw ValidationException::withMessages(['email' => $refusal]);
        }

        // Phases 2 and 3 are shared with every other way into this
        // application — see SignIn. Rate limiting stays here, because it
        // is a property of this form (keyed on email and IP) rather than
        // of signing in.
        $pendingTwoFactor = $signIn->begin($verified, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());

        return $pendingTwoFactor;
    }

    /**
     * The account whose password checks out, or null.
     *
     * The rule itself -- local hash first, directory when the credentials
     * live there -- is PasswordVerification's, because this is no longer
     * the only screen that has to ask it. See that class.
     */
    private function verifyCredentials(?User $user): ?User
    {
        if ($user === null) {
            return null;
        }

        return app(PasswordVerification::class)->verify($user, (string) $this->string('password'))
            ? $user
            : null;
    }

    /**
     * @throws ValidationException
     */
    protected function failWithInvalidCredentials(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
