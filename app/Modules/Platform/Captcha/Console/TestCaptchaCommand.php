<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha\Console;

use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use Illuminate\Console\Command;

/**
 * Check the configured secret key against the provider.
 *
 * The same probe the settings screen's Test keys button runs, for anyone
 * diagnosing an installation over SSH — and, more usefully, for a
 * deployment script that wants to know its keys survived the move before
 * the first visitor finds out they did not.
 */
class TestCaptchaCommand extends Command
{
    protected $signature = 'projectsend:captcha-test';

    protected $description = 'Check the configured CAPTCHA keys against the provider';

    public function handle(Captcha $captcha, CaptchaVerifier $verifier): int
    {
        $active = $captcha->active();

        if ($active === null) {
            $this->warn('No CAPTCHA is configured, so there is nothing to test.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Testing %s with %s keys…',
            $active->provider->label(),
            $active->managed ? 'the platform’s' : 'this installation’s',
        ));

        $result = $verifier->test($active->provider, $active->secretKey);

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
