<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Passwords\PasswordPolicy;
use App\Modules\Identity\TwoFactor\TwoFactorEnforcement;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System-wide security settings (staff-only): who is required to have
 * two-factor authentication enabled, and what makes an acceptable
 * password.
 */
class SecuritySettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('system/settings/security', [
            'two_factor_enforcement' => $this->settings->get(Setting::TwoFactorEnforcement),
            'password_min_length' => $this->settings->get(Setting::PasswordMinLength),
            'password_reject_breached' => $this->settings->get(Setting::PasswordRejectBreached),
            // The bounds the form offers come from the policy rather than
            // from a literal here, so the field and the rule below can
            // never drift apart.
            'password_min_length_floor' => PasswordPolicy::MIN_LENGTH,
            'password_min_length_ceiling' => PasswordPolicy::MAX_LENGTH,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'two_factor_enforcement' => ['required', Rule::enum(TwoFactorEnforcement::class)],
            // This is the authoritative floor: PasswordPolicy clamps on
            // read as a backstop, but refusing the save is what tells an
            // administrator their number was rejected instead of silently
            // storing something the application will not honour.
            'password_min_length' => [
                'required',
                'integer',
                'min:'.PasswordPolicy::MIN_LENGTH,
                'max:'.PasswordPolicy::MAX_LENGTH,
            ],
            'password_reject_breached' => ['required', 'boolean'],
        ]);

        $this->settings->set(Setting::TwoFactorEnforcement, $validated['two_factor_enforcement']);
        $this->settings->set(Setting::PasswordMinLength, $validated['password_min_length']);
        $this->settings->set(Setting::PasswordRejectBreached, $validated['password_reject_breached']);

        $this->activity->log(Action::SettingsUpdated, context: [
            'section' => 'security',
            'two_factor_enforcement' => $validated['two_factor_enforcement'],
            'password_min_length' => $validated['password_min_length'],
            'password_reject_breached' => $validated['password_reject_breached'],
        ]);

        return back();
    }
}
