<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

use App\Modules\Platform\Settings\Setting;

/**
 * The forms a CAPTCHA can be required on.
 *
 * Each case is two things at once: the switch that says whether this
 * installation protects that form, and the action name the token is
 * minted for. Keeping them on one enum is what makes a token bound to the
 * form it came from — v1 minted every token with the action "submit" and
 * never read it back, so a token from the login page was accepted at
 * registration.
 *
 * The value doubles as the action name, so it must stay inside
 * reCAPTCHA v3's permitted character set for actions ([A-Za-z/_]) —
 * underscores, never hyphens.
 */
enum CaptchaForm: string
{
    case Login = 'login';
    case Register = 'register';
    case PasswordReset = 'password_reset';
    case Comment = 'comment';

    public function action(): string
    {
        return $this->value;
    }

    public function setting(): Setting
    {
        return match ($this) {
            self::Login => Setting::CaptchaOnLogin,
            self::Register => Setting::CaptchaOnRegistration,
            self::PasswordReset => Setting::CaptchaOnPasswordReset,
            self::Comment => Setting::CaptchaOnPublicComments,
        };
    }
}
