<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\SignIn;
use App\Modules\Identity\TwoFactor\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Second step of login for accounts with 2FA: the password was already
 * verified, the pending user id waits in the session until a valid TOTP
 * or recovery code arrives.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $throttleKey = 'two-factor.challenge.'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => (string) RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $valid = $request->filled('code')
            ? $this->twoFactor->verify($user, (string) $request->string('code'))
            : ($request->filled('recovery_code')
                && $this->twoFactor->consumeRecoveryCode($user, trim((string) $request->string('recovery_code'))));

        if (! $valid) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, (bool) $request->session()->pull(SignIn::TWO_FACTOR_REMEMBER, false));

        $request->session()->forget(SignIn::TWO_FACTOR_ID);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(SignIn::TWO_FACTOR_ID);

        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        $user = User::query()->find($id);

        return $user instanceof User && $user->active && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
