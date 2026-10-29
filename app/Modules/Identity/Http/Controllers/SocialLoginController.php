<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Reauthentication;
use App\Modules\Identity\SignIn;
use App\Modules\Identity\StaffSignInPolicy;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\StartPages;
use App\Modules\Identity\Social\SocialAuthenticator;
use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
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

    private const RETURN_TO = 'social.return';

    public function __construct(
        private readonly SocialAuthenticator $authenticator,
        private readonly SignIn $signIn,
        private readonly ActivityLogger $activity,
        private readonly StartPages $startPages,
        private readonly Reauthentication $reauthentication,
        private readonly StaffSignInPolicy $staffSignIn,
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
    public function redirect(Request $request, string $provider): Response
    {
        return $this->begin($request, $provider, 'login');
    }

    /** Begin connecting a provider to the signed-in account. */
    public function connect(Request $request, string $provider): Response
    {
        return $this->begin($request, $provider, 'link');
    }

    /**
     * Prove it is still you, for an account with no password to type: sign
     * in again at the provider it is connected to. See Reauthentication,
     * which decides who is offered this and which provider.
     */
    public function confirm(Request $request, string $provider): Response
    {
        $user = $request->user();
        assert($user !== null);

        if ($this->reauthentication->provider($user) !== $this->provider($provider)) {
            return back()->with('error', __('That sign-in method is not available.'));
        }

        // Back to the page that asked, which is where the person was
        // working: the confirm-password screen's own intended URL when it
        // was that screen, otherwise the page the dialog opened over.
        $request->session()->put(self::RETURN_TO, url()->previous());

        return $this->begin($request, $provider, 'confirm');
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
            return (match ($intent) {
                'link' => redirect()->route('connected-accounts.edit'),
                'confirm' => $this->returnFromConfirm($request),
                default => redirect()->route('login'),
            })->with('error', __('That sign-in could not be completed. Please try again.'));
        }

        if ($intent === 'link') {
            return $this->completeLink($request, $settings, $identity);
        }

        if ($intent === 'confirm') {
            return $this->completeConfirm($request, $settings, $identity);
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

        // Staff who must sign in through one provider may not use another
        // they happen to have connected: switching them off in the
        // directory has to be enough to keep them out (#1828).
        $required = $this->staffSignIn->providerFor($resolution->user);

        if ($required !== null && $required !== $case) {
            return redirect()->route('login')->with('error', __('Staff sign in with :provider here.', [
                'provider' => $required->label(),
            ]));
        }

        // The same account-state check a password login gets, with the
        // same wording. An account awaiting approval must not be let in
        // by a different door — including the one that just created it.
        $refusal = $this->signIn->refusalReason($resolution->user);

        if ($refusal !== null) {
            return redirect()->route('login')->with('error', $refusal);
        }

        // Two-factor applies exactly as it does to a password sign-in, unless
        // the administrator has said that this provider asks for a second
        // factor of its own. SignIn decides, so that this is the only door
        // that can ask.
        //
        // Not on the sign-in that has just linked an existing account by its
        // address, though: there the provider vouched for an address, not
        // for this account, and the second factor is what stands behind a
        // match by address. Once linked, sign-ins match by subject.
        $linkedByAddress = $resolution->linked && ! $resolution->provisioned;

        if ($this->signIn->begin($resolution->user, remember: false, viaProvider: $linkedByAddress ? null : $settings)) {
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->startPages->pathFor($resolution->user));
    }

    private function begin(Request $request, string $provider, string $intent): Response
    {
        $case = $this->provider($provider);
        $settings = SocialSettings::for($case);

        if (! $settings->usable()) {
            return (match ($intent) {
                'link' => redirect()->route('connected-accounts.edit'),
                'confirm' => $this->returnFromConfirm($request),
                default => redirect()->route('login'),
            })->with('error', __('That sign-in method is not available.'));
        }

        $request->session()->put([self::INTENT => $intent, self::PROVIDER => $case->value]);

        // Inertia::location(), not the redirect itself. Connecting starts
        // as an Inertia XHR from the settings screen, and an XHR follows a
        // 302 to the provider cross-origin, where CORS kills it before the
        // person ever leaves the page. The 409 + X-Inertia-Location pair
        // makes the client navigate top-level instead; a plain browser
        // request — the login flow — passes through unchanged.
        // A confirmation stands in for typing a password, so the provider
        // is asked to make the person sign in again rather than accept the
        // session it already has.
        return Inertia::location($this->gateway()->redirect($settings, reauthenticate: $intent === 'confirm'));
    }

    /**
     * The confirmation's other half. The identity that came back must be
     * the one connected to *this* account — by subject, as signing in
     * matches it — or anybody signed in at the provider in this browser
     * could confirm for whoever holds the session here.
     */
    private function completeConfirm(Request $request, SocialSettings $settings, SocialIdentity $identity): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $connected = SocialAccount::resolve($identity);

        if ($connected === null || ! $connected->is($user) || $this->reauthentication->provider($user) !== $settings->provider) {
            return $this->returnFromConfirm($request)->with('error', __('That :provider account is not the one connected to yours. Sign in to :provider as yourself and try again.', [
                'provider' => $settings->provider->label(),
            ]));
        }

        $this->reauthentication->markConfirmed($request);

        return $this->returnFromConfirm($request)->with('success', __('Confirmed. You can carry on.'));
    }

    /**
     * Where a confirmation goes back to: the URL the framework kept for the
     * confirm-password screen, else the page the dialog was opened over.
     * Only ever this site — url()->previous() is read from the session.
     */
    private function returnFromConfirm(Request $request): RedirectResponse
    {
        $back = $request->session()->pull(self::RETURN_TO);
        $home = url('/');
        $user = $request->user();

        $fallback = is_string($back) && ($back === $home || str_starts_with($back, $home.'/'))
            ? $back
            : ($user === null ? route('login') : $this->startPages->pathFor($user));

        return redirect()->intended($fallback);
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
