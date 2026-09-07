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
 * PROJECTSEND_CAPTCHA_DISABLED is the other half of the same escape
 * hatch, and not merely the .env spelling of this one: it is checked
 * first, ahead of the key source, so it is the only one of the two that
 * works on an installation running the platform's managed keys. This
 * command writes a setting those installations never read, and says so
 * rather than reporting a success it did not have.
 */
class DisableCaptchaCommand extends Command
{
    protected $signature = 'projectsend:captcha-off';

    protected $description = 'Switch off the CAPTCHA on public forms';

    public function handle(Settings $settings, Captcha $captcha): int
    {
        $settings->set(Setting::CaptchaProvider, 'none');

        // Keys are left where they are: this is a way back in, not a way
        // to lose a credential somebody will want again in ten minutes.
        Captcha::forgetDisplayCache();
        CaptchaVerifier::forgetOutage();

        // Managed keys are not this setting. Captcha::resolve() reaches
        // them from config and returns before it ever looks at
        // Setting::CaptchaProvider, so on an installation using them the
        // write above changed a value nothing reads. Saying "CAPTCHA is
        // off" there would be false, and false in the worst direction: an
        // operator who is still being challenged would stop looking,
        // having just been told the thing challenging them is gone.
        //
        // Read after the write rather than before it, because the write is
        // what makes the answer meaningful — if this still resolves to
        // something, the something is not ours to switch off.
        if ($captcha->managedKeysSelected()) {
            $this->warn('Nothing changed. This installation uses CAPTCHA keys supplied by the platform, and those do not come from the setting this command writes.');
            $this->line('Set PROJECTSEND_CAPTCHA_DISABLED=true in the environment and restart to switch it off.');

            return self::SUCCESS;
        }

        $this->info('CAPTCHA is off. Your keys are still stored — switch it back on at /system/settings/captcha.');

        return self::SUCCESS;
    }
}
