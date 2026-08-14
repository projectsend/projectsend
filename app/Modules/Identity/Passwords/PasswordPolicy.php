<?php

declare(strict_types=1);

namespace App\Modules\Identity\Passwords;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Validation\Rules\Password;

/**
 * The one definition of what makes an acceptable password here.
 *
 * Every field in the application that *sets* a password validates with
 * `Password::defaults()`, and AppServiceProvider points that at this
 * class — so there is a single place to change, rather than the fourteen
 * call sites that would each have to be remembered.
 *
 * Deliberately not a composition policy. v1 offered "require an
 * uppercase / a number / a special character" and NIST SP 800-63B
 * advises against exactly those, because they push people towards
 * `Password1!` and away from length. The two things that measurably help
 * — how long it is, and whether it already appears in a breach corpus —
 * are what this exposes.
 *
 * Nothing here runs at login. A password is checked against this policy
 * when it is chosen, never when it is used, which is why tightening the
 * policy cannot lock out an existing account (including the ones the v1
 * migration carried across with a five-character password).
 */
class PasswordPolicy
{
    /**
     * Laravel's own default, and the lowest an installation may go.
     * A stored value below this is clamped rather than honoured, so
     * neither a hand-edited row nor a future bug can take an
     * installation under the framework's floor.
     */
    public const MIN_LENGTH = 8;

    public const MAX_LENGTH = 128;

    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function minLength(): int
    {
        $configured = $this->settings->get(Setting::PasswordMinLength);

        return max(self::MIN_LENGTH, min(self::MAX_LENGTH, (int) $configured));
    }

    public function rejectsBreached(): bool
    {
        return (bool) $this->settings->get(Setting::PasswordRejectBreached);
    }

    /**
     * The rule object handed to every password field.
     *
     * `uncompromised()` calls the k-anonymity range API at
     * haveibeenpwned, so it stays production-only regardless of the
     * setting: outside production it would put a network round-trip (and
     * a flaky one) into the test suite and local dev.
     */
    public function rule(): Password
    {
        $rule = Password::min($this->minLength());

        if ($this->rejectsBreached() && app()->isProduction()) {
            $rule = $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * What the forms tell the person choosing a password, shared with
     * every page through HandleInertiaRequests.
     *
     * `reject_breached` reports the setting rather than whether the check
     * will actually run, because it is a statement of this
     * installation's policy — the production gate above is an
     * implementation detail of how we keep tests offline, not something
     * an administrator configured.
     *
     * @return array{min_length: int, reject_breached: bool}
     */
    public function descriptor(): array
    {
        return [
            'min_length' => $this->minLength(),
            'reject_breached' => $this->rejectsBreached(),
        ];
    }
}
