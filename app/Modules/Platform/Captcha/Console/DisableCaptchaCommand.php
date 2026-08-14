<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha\Console;

use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;

/**
 * Switch the CAPTCHA off from the command line.
 *
 * The situation this exists for should not arise: a wrong secret key and
 * an unreachable provider both fail open precisely so that nobody is ever
 * shut out of their own installation by this feature. But "should not
 * arise" is not "cannot", and the alternative to a one-line command is an
 * administrator editing a database table by hand, guessing which of
 * several rows matters.
 *
 * PROJECTSEND_CAPTCHA_DISABLED does the same thing for anyone who would
 * rather touch .env than run artisan.
 */
class DisableCaptchaCommand extends Command
{
    protected $signature = 'projectsend:captcha-off';

    protected $description = 'Switch off the CAPTCHA on public forms';

    public function handle(Settings $settings): int
    {
        $settings->set(Setting::CaptchaProvider, 'none');

        // Keys are left where they are: this is a way back in, not a way
        // to lose a credential somebody will want again in ten minutes.
        Captcha::forgetDisplayCache();
        CaptchaVerifier::forgetOutage();

        $this->info('CAPTCHA is off. Your keys are still stored — switch it back on at /system/settings/captcha.');

        return self::SUCCESS;
    }
}
