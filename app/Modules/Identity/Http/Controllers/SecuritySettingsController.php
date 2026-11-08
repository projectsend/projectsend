<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\UserType;
use App\Modules\Mcp\OAuth\Grants;
use App\Modules\Mcp\OAuth\Models\McpConnection;
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

        $current = $this->settings->get(Setting::TwoFactorEnforcement);
        $before = (is_string($current) ? TwoFactorEnforcement::tryFrom($current) : null) ?? TwoFactorEnforcement::None;
        $after = TwoFactorEnforcement::from($validated['two_factor_enforcement']);

        $this->settings->set(Setting::TwoFactorEnforcement, $validated['two_factor_enforcement']);

        $revoked = $this->endCredentialsBeyondEnforcement($before, $after);
        $this->settings->set(Setting::PasswordMinLength, $validated['password_min_length']);
        $this->settings->set(Setting::PasswordRejectBreached, $validated['password_reject_breached']);

        $this->activity->log(Action::SettingsUpdated, context: [
            'section' => 'security',
            'two_factor_enforcement' => $validated['two_factor_enforcement'],
            'password_min_length' => $validated['password_min_length'],
            'password_reject_breached' => $validated['password_reject_breached'],
            'credentials_revoked_for' => $revoked,
        ]);

        return back();
    }

    /**
     * Requiring two-factor is enforced at the browser (EnforceTwoFactor),
     * and an API token or an assistant connection never passes through it:
     * one made before the requirement kept working for an account that
     * never enrolled. So when the requirement starts to cover somebody it
     * did not, and they have no second factor, what they hold outside the
     * browser ends now. A new one is made from a web session, which asks
     * them to enrol first.
     *
     * Returns how many accounts lost something.
     */
    private function endCredentialsBeyondEnforcement(TwoFactorEnforcement $before, TwoFactorEnforcement $after): int
    {
        $types = array_values(array_filter(
            UserType::cases(),
            fn (UserType $type): bool => $after->appliesTo($type) && ! $before->appliesTo($type),
        ));

        if ($types === []) {
            return 0;
        }

        $grants = app(Grants::class);
        $count = 0;

        User::query()
            ->whereIn('type', array_map(fn (UserType $type): string => $type->value, $types))
            ->whereNull('two_factor_confirmed_at')
            ->where(fn ($holders) => $holders
                ->whereHas('tokens')
                ->orWhereIn('id', McpConnection::query()->whereNull('revoked_at')->select('user_id')))
            ->each(function (User $user) use ($grants, &$count): void {
                $user->tokens()->delete();
                $grants->revokeAllFor($user, 'two-factor required');
                $count++;
            });

        return $count;
    }
}
