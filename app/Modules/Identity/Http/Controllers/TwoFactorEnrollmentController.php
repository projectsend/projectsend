<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\TwoFactor\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorEnrollmentController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly ActivityLogger $activity,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        $pendingSecret = $user->two_factor_confirmed_at === null ? $user->two_factor_secret : null;

        return Inertia::render('settings/two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pendingSecret !== null,
            'qr_code_svg' => $pendingSecret !== null ? $this->twoFactor->qrCodeSvg($user, $pendingSecret) : null,
            'secret' => $pendingSecret,
            'recovery_codes' => $request->session()->get('two_factor_recovery_codes'),
            'enforced' => (bool) $request->session()->get('two_factor_enforced_notice', false),
        ]);
    }

    /**
     * Start enrollment: generate a secret awaiting confirmation.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->hasTwoFactorEnabled()) {
            $user->forceFill([
                'two_factor_secret' => $this->twoFactor->generateSecret(),
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        }

        return back();
    }

    /**
     * Confirm enrollment with a code from the authenticator app.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $request->validate(['code' => ['required', 'string']]);

        if ($user->hasTwoFactorEnabled() || $user->two_factor_secret === null) {
            return back();
        }

        if (! $this->twoFactor->verify($user, (string) $request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->activity->log(Action::TwoFactorEnabled, $user);

        return back()->with('two_factor_recovery_codes', $recoveryCodes);
    }

    /**
     * Replace the recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->hasTwoFactorEnabled()) {
            return back();
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $recoveryCodes])->save();

        $this->activity->log(Action::TwoFactorRecoveryCodesRegenerated, $user);

        return back()->with('two_factor_recovery_codes', $recoveryCodes);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if ($this->twoFactor->clear($user)) {
            $this->activity->log(Action::TwoFactorDisabled, $user);
        }

        return back();
    }
}
