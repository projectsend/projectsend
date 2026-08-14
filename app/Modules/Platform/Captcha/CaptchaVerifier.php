<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the provider whether one token is good.
 *
 * v1 did this with file_get_contents() and no timeout, decided reCAPTCHA
 * v2's answer by searching the raw body for the substring `"success":
 * true`, and treated every non-success — including a network error — as a
 * bot. All three are fixed here, and the third is the one that changes
 * behaviour rather than merely style: see CaptchaResult.
 */
class CaptchaVerifier
{
    /**
     * Long enough that an outage does not cost every visitor a timeout,
     * short enough that recovery is not noticeable. Same shape, and the
     * same reasoning, as LdapAuthenticator's breaker.
     */
    private const BREAKER_KEY = 'platform.captcha.unreachable';

    private const BREAKER_SECONDS = 60;

    /**
     * What the settings screen shows an administrator when verification is
     * not working. Kept for a week because the useful version of this
     * message is "since Tuesday", not "just now".
     */
    private const LAST_ERROR_KEY = 'platform.captcha.last_error';

    private const LAST_ERROR_DAYS = 7;

    public function __construct(
        private readonly Captcha $captcha,
    ) {}

    public function verify(string $token, CaptchaForm $form, ?string $ip = null): CaptchaResult
    {
        $active = $this->captcha->active();

        if ($active === null) {
            return CaptchaResult::Passed;
        }

        // While the breaker is open every verification is already known to
        // be failing. Without this, an outage adds the full timeout to
        // every login on the site rather than to the first one.
        if (Cache::get(self::BREAKER_KEY) === true) {
            return CaptchaResult::Unavailable;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(5)
                ->acceptJson()
                ->post($active->provider->verifyUrl(), array_filter([
                    'secret' => $active->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ], fn (?string $value): bool => $value !== null && $value !== ''));
        } catch (ConnectionException $e) {
            return $this->unavailable($active, $form, ['connection'], $e->getMessage());
        }

        $body = $response->json();
        $body = is_array($body) ? $body : null;

        // A non-2xx is never a verdict about the visitor, so it can only be
        // Unavailable — but the body usually still says *why*, and the
        // difference between "we could not reach them" and "they rejected
        // your secret key" is the whole value of the message an
        // administrator eventually reads. Cloudflare answers a malformed
        // secret with 400 and `invalid-input-secret` together, so reading
        // the status alone would throw away the useful half.
        if (! $response->successful()) {
            $codes = $body !== null ? $this->errorCodes($body) : [];

            return $this->unavailable($active, $form, $codes !== [] ? $codes : ['http_'.$response->status()]);
        }

        if ($body === null) {
            return $this->unavailable($active, $form, ['unparseable_body']);
        }

        return $this->interpret($active, $form, $body);
    }

    /**
     * Prove a secret key without solving a challenge.
     *
     * A deliberately meaningless response token is sent: a provider that
     * gets far enough to complain about the *token* has accepted the
     * secret, which is exactly what an administrator wants to know after
     * pasting one in. Anything else is reported verbatim.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(CaptchaProvider $provider, string $siteKeyOwnerSecret): array
    {
        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(5)
                ->acceptJson()
                ->post($provider->verifyUrl(), [
                    'secret' => $siteKeyOwnerSecret,
                    'response' => 'projectsend-connectivity-probe',
                ]);
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'message' => __('Could not reach :provider: :error', [
                    'provider' => $provider->label(),
                    'error' => $e->getMessage(),
                ]),
            ];
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $codes = $this->errorCodes($body);

        // The plainest possible answer, and easy to forget to handle
        // because a made-up token should never verify: Cloudflare's
        // always-pass *testing* secret accepts anything, including this
        // probe. Without this branch the clearest "your key works" there
        // is falls through to "unexpected reply".
        if (($body['success'] ?? false) === true) {
            return [
                'ok' => true,
                'message' => __(':provider accepted your secret key.', ['provider' => $provider->label()]),
            ];
        }

        if (in_array('invalid-input-response', $codes, true) || in_array('missing-input-response', $codes, true)) {
            return [
                'ok' => true,
                'message' => __(':provider accepted your secret key.', ['provider' => $provider->label()]),
            ];
        }

        if ($this->blamesOurCredentials($codes)) {
            return [
                'ok' => false,
                'message' => __(':provider rejected your secret key.', ['provider' => $provider->label()]),
            ];
        }

        return [
            'ok' => false,
            'message' => __('Unexpected reply from :provider: :codes', [
                'provider' => $provider->label(),
                'codes' => $codes === [] ? (string) $response->status() : implode(', ', $codes),
            ]),
        ];
    }

    /**
     * What the settings screen shows about the last failure, if any.
     *
     * @return array{at: string, codes: list<string>, our_credentials: bool}|null
     */
    public static function lastError(): ?array
    {
        /** @var array{at: string, codes: list<string>, our_credentials: bool}|null */
        return Cache::get(self::LAST_ERROR_KEY);
    }

    /**
     * Forget that anything was ever wrong: both the operator-facing
     * complaint and the breaker holding calls back.
     *
     * Called when the configuration changes, because an administrator who
     * has just replaced a rejected key should get a fresh verdict on the
     * next visitor rather than a minute of silence and a week-old warning
     * about the key they already fixed.
     */
    public static function forgetOutage(): void
    {
        Cache::forget(self::LAST_ERROR_KEY);
        Cache::forget(self::BREAKER_KEY);
    }

    /**
     * @param  array<mixed>  $body
     */
    private function interpret(ResolvedCaptcha $active, CaptchaForm $form, array $body): CaptchaResult
    {
        $codes = $this->errorCodes($body);

        if (($body['success'] ?? false) !== true) {
            // A wrong or missing secret is our mistake, not the visitor's,
            // and refusing them for it is how an installation locks its own
            // administrator out over a trailing space in a pasted key.
            if ($this->blamesOurCredentials($codes)) {
                return $this->unavailable($active, $form, $codes);
            }

            Cache::forget(self::BREAKER_KEY);

            return CaptchaResult::Failed;
        }

        // The provider answered, so whatever was wrong before is over.
        Cache::forget(self::BREAKER_KEY);

        // The hostname the widget ran on comes back too. It is logged with
        // a failure and never enforced: the provider already restricts the
        // site key to its own domain list, and enforcing it here breaks
        // behind a reverse proxy and on cloud, where one managed key
        // serves every tenant subdomain.
        // Bound when the provider says what the token was minted for, and
        // only then. A response carrying a *different* action is a token
        // from another form and is refused — the replay v1 allowed by
        // hardcoding the action to "submit" and never reading it back.
        //
        // An absent action is accepted, because it means the widget that
        // minted this was rendered without one: Cloudflare's own testing
        // keys answer that way, and so does any provider we have not
        // taught to send it. Refusing those would break the keys people
        // develop against while buying nothing — a token minted without an
        // action was never bound to a form in the first place, and
        // stopping somebody who can render their own widget is what the
        // provider's domain allow-list is for.
        $action = $body['action'] ?? null;

        if ($active->provider->bindsAction()
            && is_string($action) && $action !== ''
            && $action !== $form->action()) {
            return CaptchaResult::Failed;
        }

        if ($active->provider->usesScore()) {
            $score = $body['score'] ?? null;

            if (! is_numeric($score) || (float) $score < $active->threshold) {
                return CaptchaResult::Failed;
            }
        }

        return CaptchaResult::Passed;
    }

    /**
     * @param  array<mixed>  $body
     * @return list<string>
     */
    private function errorCodes(array $body): array
    {
        $codes = $body['error-codes'] ?? [];

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_map(strval(...), $codes));
    }

    /**
     * @param  list<string>  $codes
     */
    private function blamesOurCredentials(array $codes): bool
    {
        return array_intersect($codes, [
            'invalid-input-secret',
            'missing-input-secret',
            'bad-request',
            'internal-error',
        ]) !== [];
    }

    /**
     * @param  list<string>  $codes
     */
    private function unavailable(ResolvedCaptcha $active, CaptchaForm $form, array $codes, ?string $detail = null): CaptchaResult
    {
        Cache::put(self::BREAKER_KEY, true, self::BREAKER_SECONDS);

        Cache::put(self::LAST_ERROR_KEY, [
            'at' => now()->toIso8601String(),
            'codes' => $codes,
            'our_credentials' => $this->blamesOurCredentials($codes),
        ], now()->addDays(self::LAST_ERROR_DAYS));

        // Not the activity log: this is a transient infrastructure
        // condition, not something an administrator did.
        Log::warning('Captcha verification unavailable', array_filter([
            'provider' => $active->provider->value,
            'managed' => $active->managed,
            'form' => $form->value,
            'error_codes' => $codes,
            'detail' => $detail,
        ]));

        return CaptchaResult::Unavailable;
    }
}
