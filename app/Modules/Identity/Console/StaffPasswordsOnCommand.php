<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\StaffSignInPolicy;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;

/**
 * Let staff sign in with a password again, from the command line.
 *
 * The way back in when the provider staff must use stops working while
 * it is still switched on — a client secret that expired, a tenant that
 * changed — and so nobody can reach the settings screen to change it.
 * Switching the provider off has the same effect (StaffSignInPolicy only
 * enforces a usable provider), but that needs somebody signed in too.
 *
 * Passwords staff already had work again at once. Anybody who never had
 * one can ask for a reset link as usual.
 */
class StaffPasswordsOnCommand extends Command
{
    protected $signature = 'projectsend:staff-passwords-on';

    protected $description = 'Let staff sign in with a password again, when the provider they must use is not working';

    public function handle(Settings $settings, StaffSignInPolicy $policy, ActivityLogger $activity): int
    {
        $provider = $policy->configuredProvider();

        if ($provider === null) {
            $this->info('Staff can already sign in with a password. Nothing changed.');

            return self::SUCCESS;
        }

        $settings->set(Setting::StaffSignInProvider, '');

        $activity->log(Action::SettingsUpdated, context: [
            'section' => 'staff-sign-in',
            'provider' => null,
            'via' => 'console',
        ]);

        $this->info("Staff can sign in with a password again, instead of only with {$provider->label()}.");
        $this->line('Choose a provider again at /system/settings/social-login once it is working.');

        return self::SUCCESS;
    }
}
