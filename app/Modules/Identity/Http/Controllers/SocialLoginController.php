<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\SignIn;
use App\Modules\Identity\Social\SocialAuthenticator;
use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Signing in through an identity provider, and connecting one to an
 * account that already exists.
 *
 * Both start the same OAuth exchange and come back to the same callback,
 * which is why the intent is written into the session before the redirect
 * rather than inferred afterwards. The callback can be neither `guest`
 * (connecting requires a session) nor `auth` (signing in must not have
 * one), so the session marker is also what refuses a stray or replayed
 * callback that nobody asked for.
 */
class SocialLoginController extends Controller
{
    private const INTENT = 'social.intent';

    private const PROVIDER = 'social.provider';

    public function __construct(
        private readonly SocialAuthenticator $authenticator,
        private readonly SignIn $signIn,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Resolved per call rather than injected.
     *
     * Laravel memoises a controller instance on its Route object, so
     * anything taken through the constructor is built once for the life
     * of the process — and the gateway wraps a Socialite provider bound
     * to the *current* request's `code`, `state` and session. Under
     * PHP-FPM that is invisible; under Octane, or in a test that swaps
     * the gateway twice, it is a stale object serving a live request.
     */
    private function gateway(): SocialGateway
    {
        return app(SocialGateway::class);
    }

    /** Begin a sign-in. */
    public function redirect(Request $request, string $provider): SymfonyRedirectResponse|RedirectResponse
    {
        return $this->begin($request, $provider, 'login');
    }

    /** Begin connecting a provider to the signed-in account. */
    public function connect(Request $request, string $provider): SymfonyRedirectResponse|RedirectResponse
    {
        return $this->begin($request, $provider, 'link');
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $case = $this->provider($provider);

        $intent = $request->session()->pull(self::INTENT);
        $expected = $request->session()->pull(self::PROVIDER);

        // Nobody started this exchange from here.
        if (! is_string($intent) || $expected !== $case->value) {
            return redirect()->route('login')->with('error', __('That sign-in could not be completed. Please try again.'));
        }

        $settings = SocialSettings::for($case);

        if (! $settings->usable()) {
            return redirect()->route('login')->with('error', __('That sign-in method is not available.'));
        }

        $identity = $this->gateway()->identity($settings);

        if ($identity === null) {
            return $intent === 'link'
                ? redirect()->route('connected-accounts.edit')->with('error', __('That sign-in could not be completed. Please try again.'))
                : redirect()->route('login')->with('error', __('That sign-in could not be completed. Please try again.'));
        }

        if ($intent === 'link') {
            return $this->completeLink($request, $settings, $identity);
        }

        $resolution = $this->authenticator->resolve($settings, $identity);

        if ($resolution->user === null) {
            return redirect()->route('login')->with('error', $resolution->refusal);
        }

        if ($resolution->linked && ! $resolution->provisioned) {
            $this->activity->log(Action::SocialAccountLinked, $resolution->user, $resolution->user, [
                'provider' => $case->label(),
            ]);
        }

        // The same account-state check a password login gets, with the
        // same wording. An account awaiting approval must not be let in
        // by a different door — including the one that just created it.
        $refusal = $this->signIn->refusalReason($resolution->user);

        if ($refusal !== null) {
            return redirect()->route('login')->with('error', $refusal);
        }

        // Two-factor still applies, identically: a second factor that a
        // provider could skip is not a second factor.
        if ($this->signIn->begin($resolution->user, remember: false)) {
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function begin(Request $request, string $provider, string $intent): SymfonyRedirectResponse|RedirectResponse
    {
        $case = $this->provider($provider);
        $settings = SocialSettings::for($case);

        if (! $settings->usable()) {
            return redirect()->route($intent === 'link' ? 'connected-accounts.edit' : 'login')
                ->with('error', __('That sign-in method is not available.'));
        }

        $request->session()->put([self::INTENT => $intent, self::PROVIDER => $case->value]);

        return $this->gateway()->redirect($settings);
    }

    private function completeLink(Request $request, SocialSettings $settings, SocialIdentity $identity): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $link = $this->authenticator->link($user, $identity);

        if ($link === null) {
            return redirect()->route('connected-accounts.edit')->with(
                'error',
                __('That :provider account is already connected to another account here.', [
                    'provider' => $settings->provider->label(),
                ])
            );
        }

        $this->activity->log(Action::SocialAccountLinked, $user, $user, [
            'provider' => $settings->provider->label(),
        ]);

        return redirect()->route('connected-accounts.edit')->with(
            'success',
            __(':provider connected.', ['provider' => $settings->provider->label()])
        );
    }

    private function provider(string $provider): SocialProvider
    {
        return SocialProvider::tryFrom($provider) ?? throw new NotFoundHttpException;
    }
}
