<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

/**
 * The CAPTCHA services this installation can be configured against.
 *
 * All three are v1 parity, so an install coming across keeps whichever it
 * had rather than being told to re-key. They differ in ways the rest of
 * the module has to respect, and the three predicates below are the whole
 * of that difference:
 *
 *  - Turnstile and reCAPTCHA v2 render something the visitor interacts
 *    with; v3 is invisible and mints a token at submit time instead.
 *  - v3 is the only one that scores rather than decides, so it is the only
 *    one with a threshold.
 *  - v3 and Turnstile echo back the `action` the token was minted for; v2
 *    does not, and so cannot be bound to a form.
 */
enum CaptchaProvider: string
{
    case Turnstile = 'turnstile';
    case RecaptchaV2 = 'recaptcha_v2';
    case RecaptchaV3 = 'recaptcha_v3';

    /**
     * v1 hardcoded these in English inside each provider's constructor,
     * which is why "Cloudflare Turnstile" was untranslatable there.
     */
    public function label(): string
    {
        return match ($this) {
            self::Turnstile => __('Cloudflare Turnstile'),
            self::RecaptchaV2 => __('reCAPTCHA v2'),
            self::RecaptchaV3 => __('reCAPTCHA v3'),
        };
    }

    public function verifyUrl(): string
    {
        return match ($this) {
            self::Turnstile => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            self::RecaptchaV2, self::RecaptchaV3 => 'https://www.google.com/recaptcha/api/siteverify',
        };
    }

    /**
     * The vendor script this provider's widget needs.
     *
     * `render=explicit` for the two that draw a widget: auto-rendering
     * scans the document for a magic class, which is what made two widgets
     * on one page break in v1. v3 draws nothing and instead wants the site
     * key baked into the URL so `grecaptcha.execute` can be called later.
     */
    public function scriptUrl(string $siteKey): string
    {
        return match ($this) {
            self::Turnstile => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
            self::RecaptchaV2 => 'https://www.google.com/recaptcha/api.js?render=explicit',
            self::RecaptchaV3 => 'https://www.google.com/recaptcha/api.js?render='.urlencode($siteKey),
        };
    }

    /**
     * Whether a siteverify response carries a `score` that has to clear a
     * threshold, rather than a plain yes or no.
     */
    public function usesScore(): bool
    {
        return $this === self::RecaptchaV3;
    }

    /**
     * Whether siteverify echoes back the action the token was minted for.
     *
     * When it does, a token from the login form can be refused at the
     * registration form — the replay hole v1 left open by hardcoding the
     * action to "submit" and never reading it back. reCAPTCHA v2 returns
     * no action at all and cannot be bound this way; the residual risk is
     * bounded by tokens being single-use, short-lived, site-key-scoped and
     * behind the same throttles as everything else.
     */
    public function bindsAction(): bool
    {
        return $this !== self::RecaptchaV2;
    }
}
