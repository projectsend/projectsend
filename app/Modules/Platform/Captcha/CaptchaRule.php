<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Request;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Verifies one token, as a validation rule.
 *
 * A rule rather than an explicit call in each controller, because all four
 * protected forms already build a rule array — so enforcement is one array
 * entry, "switched off" is an empty array rather than an `if`, and the
 * error lands under a field key that both the Inertia forms and the JSON
 * comment endpoint already render.
 *
 * It also removes, structurally, the worst bug in v1's version: there is
 * one rule, so there is exactly one verification per request. v1's
 * registration path called the provider twice for reCAPTCHA v2 — the
 * second call on an already-consumed token — which failed every time and
 * made self-registration impossible to complete.
 */
class CaptchaRule implements ValidationRule
{
    public function __construct(
        private readonly CaptchaForm $form,
    ) {}

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            // Reached only when a caller drops `required`; the message
            // still has to make sense to whoever sees it.
            $fail(__('Complete the security check and try again.'));

            return;
        }

        $result = app(CaptchaVerifier::class)->verify($value, $this->form, Request::ip());

        if ($result->allowsRequest()) {
            return;
        }

        $fail(__('Security check failed. Please try again.'));
    }
}
