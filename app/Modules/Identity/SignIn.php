<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Everything that happens *after* a credential checks out.
 *
 * `LoginRequest::authenticate()` runs in three phases — identify and
 * verify, then account state, then two-factor and the session. Phase 1
 * differs per credential source: a password form checks a hash or a
 * directory bind, a provider callback checks an OAuth exchange. Phases 2
 * and 3 do not differ at all, and the way they get broken is by being
 * written twice.
 *
 * Re-implementing them at a second entry point is exactly how a product
 * ships "SSO skips the approval queue" or "SSO skips two-factor" — both
 * of which look like features until somebody notices. So they live here,
 * and every way into this application goes through them.
 *
 * Two methods rather than one, and no exception plumbing, because the
 * callers refuse in genuinely different idioms: a form renders a field
 * error, a provider callback redirects with a flash. Handing both a
 * ValidationException would make one of them lie about where the problem
 * is.
 */
class SignIn
{
    /**
     * The id of an account that has passed phase 1 and is waiting on its
     * second factor. Shared with TwoFactorChallengeController, which is
     * the other half of this handshake.
     */
    public const TWO_FACTOR_ID = 'two_factor.login_id';

    public const TWO_FACTOR_REMEMBER = 'two_factor.remember';

    /**
     * Phase 2 — the translated reason this account may not sign in, or
     * null when it may.
     *
     * Only ever called with a verified credential in hand, which is what
     * makes it safe to be specific: the account state is revealed to its
     * owner and to nobody else. Calling this before verifying a
     * credential would turn it into an account enumeration oracle.
     */
    public function refusalReason(User $user): ?string
    {
        if ($user->active) {
            return null;
        }

        return $user->account_requested
            ? __('Your account request has not been approved yet.')
            : __('Your account has been deactivated.');
    }

    /**
     * Phase 3 — start the session, or park the account for its second
     * factor.
     *
     * Returns true when a two-factor challenge is pending, in which case
     * no session has been created and the caller should redirect to
     * `two-factor.challenge`.
     *
     * Auth::login rather than Auth::attempt: the credential was already
     * verified upstream, and possibly not against a password at all. It
     * still fires the Login event, which is what writes the activity-log
     * entry.
     */
    public function begin(User $user, bool $remember): bool
    {
        if ($user->hasTwoFactorEnabled()) {
            Session::put([
                self::TWO_FACTOR_ID => $user->id,
                self::TWO_FACTOR_REMEMBER => $remember,
            ]);

            return true;
        }

        Auth::login($user, $remember);

        return false;
    }
}
