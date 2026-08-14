<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

/**
 * The CAPTCHA configuration actually in force, once the question of
 * whose keys these are has been settled.
 *
 * Everything downstream — the verifier, the browser payload, the settings
 * screen's status strip — works from this rather than re-deriving it, so
 * "are we on managed keys?" is answered exactly once per request.
 */
final readonly class ResolvedCaptcha
{
    public function __construct(
        public CaptchaProvider $provider,
        public string $siteKey,
        public string $secretKey,
        public float $threshold,
        /** Whether these are the platform's own keys rather than this installation's. */
        public bool $managed,
    ) {}
}
