<?php

declare(strict_types=1);

namespace App\Modules\Identity\TwoFactor;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Notifications\TwoFactorResetNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * An administrator removing somebody else's second factor.
 *
 * This exists because of an asymmetry: enrolling in two-factor
 * authentication is something only you can do, but *losing* it — a wiped
 * phone, a reinstalled authenticator app, recovery codes in a drawer at
 * the old office — locks you out of an account nobody else can open
 * either. Without this, the only remedies are a database edit or deleting
 * the account, and deleting a staff account means reassigning everything
 * it owns to get somebody back in through the front door.
 *
 * The three things that happen here belong together and are the reason
 * this is a service rather than four lines in a controller. Clearing the
 * credentials is the smallest part; the audit entry and the email to the
 * account holder are what make the action accountable, and a surface that
 * forgot either would turn a support tool into a silent takeover route.
 *
 * What is deliberately *not* here is who may do it. That is an authority
 * question, and it already has answers: `can:edit_users` /
 * `can:edit_clients` on the route, and StaffAccounts::guardTarget() for
 * "may this actor touch this particular account".
 */
class TwoFactorAdministration
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
    ) {}

    /**
     * Remove the target's second factor. Returns whether one was in force
     * — a half-finished enrolment is still cleared, but there is nothing
     * to report about it.
     *
     * If the installation enforces two-factor authentication for the
     * target's user type, EnforceTwoFactor walks them straight back into
     * enrolment on their next request. That is the intended outcome: this
     * un-sticks an account, it does not exempt one.
     */
    public function reset(User $target): bool
    {
        if (! $this->twoFactor->clear($target)) {
            return false;
        }

        $this->activity->log(Action::TwoFactorReset, subject: $target);

        if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $target->notify(new TwoFactorResetNotification);
        }

        return true;
    }
}
