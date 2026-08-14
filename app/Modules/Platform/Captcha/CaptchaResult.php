<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

/**
 * The outcome of verifying one token — three-valued, and the third value
 * is the point.
 *
 * v1 had two: verified, or not. Everything that was not a clean success
 * refused the request, which meant a network timeout, a 500 from Google,
 * and *an administrator's own mistyped secret key* all read as "this
 * visitor is a bot". On a login form that is an installation locking out
 * its own administrator because a third party had a bad afternoon, or
 * because a key was pasted with a trailing space.
 *
 * So a refusal the provider attributes to the visitor is `Failed`, and
 * everything else — including the provider telling us *our* credential is
 * wrong — is `Unavailable`, which lets the request through and is reported
 * to the operator instead of to the visitor.
 */
enum CaptchaResult
{
    case Passed;

    /** The provider answered, and blamed the visitor. */
    case Failed;

    /**
     * The provider could not be reached, did not answer usefully, or
     * rejected our own credentials. Never the visitor's fault.
     */
    case Unavailable;

    /**
     * Whether the request may proceed.
     *
     * The one place the fail-open policy is written down. It applies to
     * every protected form: what stops an unverified request from being a
     * free pass is that a *missing* token is still refused before this is
     * ever consulted, and that the rate limits every one of these forms
     * already carries are untouched by any of this.
     */
    public function allowsRequest(): bool
    {
        return $this !== self::Failed;
    }
}
