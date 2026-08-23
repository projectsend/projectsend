<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Mail\MailOAuthBrokers;
use App\Modules\Platform\Mail\MailOAuthConnection;
use App\Modules\Platform\Mail\MailOAuthException;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\MailProviderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connecting the mailbox an OAuth mail provider sends as, and cutting it
 * loose again.
 *
 * Mirrors SocialLoginController's shape — a session marker written
 * before the redirect is what ties the callback to an exchange somebody
 * here actually started, and refuses a stray or replayed one — but with
 * its own state parameter instead of Socialite, because this flow wants
 * raw tokens with a send scope, not a user identity (see
 * MailOAuthBroker). Community-only like the rest of the transport
 * configuration: on cloud, outgoing mail is the platform's relay and
 * there is nothing to connect.
 */
class EmailOAuthController extends Controller
{
    private const STATE = 'mail_oauth.state';

    private const PROVIDER = 'mail_oauth.provider';

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly MailOAuthBrokers $brokers,
        private readonly MailConfigApplier $mailConfig,
        private readonly ActivityLogger $activity,
    ) {}

    /** Begin connecting: off to the provider's consent screen. */
    public function connect(Request $request): RedirectResponse|Response
    {
        abort_unless($this->capabilities->has(Capability::EmailTransportConfigure), 404);

        $provider = MailProviderSettings::current()->provider;

        if (! $provider->isOAuth()) {
            return back()->with('error', __('The selected mail provider does not use a connected mailbox.'));
        }

        $connection = MailOAuthConnection::for($provider);

        if (! $connection->configured()) {
            return back()->with('error', __('Enter and save the application (client) ID and secret first.'));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE, $state);
        $request->session()->put(self::PROVIDER, $provider->value);

        // Inertia::location(), not redirect()->away(): the button posts
        // through Inertia's XHR, and a plain 302 to another origin makes
        // the XHR follow it into a CORS wall — the consent screen never
        // appears and the page just reloads. The 409/X-Inertia-Location
        // handshake turns it into a real top-level navigation (and falls
        // back to an ordinary redirect for a non-Inertia request).
        return Inertia::location(
            $this->brokers->for($provider)->authorizeUrl($connection, $state, route('system-settings.email.oauth.callback')),
        );
    }

    /** The provider sent the admin's browser back with a code (or a refusal). */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->capabilities->has(Capability::EmailTransportConfigure), 404);

        $expectedState = $request->session()->pull(self::STATE);
        $startedProvider = $request->session()->pull(self::PROVIDER);

        $provider = MailProviderSettings::current()->provider;

        // Nobody started this exchange from here — or the provider was
        // switched mid-flight, in which case the code belongs to a
        // configuration that no longer exists.
        if (! is_string($expectedState) || $startedProvider !== $provider->value || ! $provider->isOAuth()) {
            return redirect()->route('system-settings.email.edit')->with('error', __('That connection attempt could not be completed. Please try again.'));
        }

        $state = $request->query('state');

        if (! is_string($state) || ! hash_equals($expectedState, $state)) {
            return redirect()->route('system-settings.email.edit')->with('error', __('That connection attempt could not be completed. Please try again.'));
        }

        // The admin clicked "Cancel" on the consent screen, or the
        // provider refused. Their description is safe to show — this is
        // an authenticated administrator on their own settings page.
        $error = $request->query('error');

        if (is_string($error) && $error !== '') {
            $description = $request->query('error_description');

            return redirect()->route('system-settings.email.edit')
                ->with('error', __('The mailbox was not connected: :reason', [
                    'reason' => is_string($description) && $description !== '' ? $description : $error,
                ]));
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()->route('system-settings.email.edit')->with('error', __('That connection attempt could not be completed. Please try again.'));
        }

        $connection = MailOAuthConnection::for($provider);

        try {
            $this->brokers->for($provider)->exchange($connection, $code, route('system-settings.email.oauth.callback'));
        } catch (MailOAuthException $e) {
            return redirect()->route('system-settings.email.edit')
                ->with('error', __('The mailbox was not connected: :reason', ['reason' => $e->getMessage()]));
        }

        $this->activateConnection();

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'email', 'action' => 'mailbox_connected']);

        return redirect()->route('system-settings.email.edit')
            ->with('success', __(':account connected. Outgoing email now sends as this mailbox.', [
                'account' => (string) $connection->account_email,
            ]));
    }

    /**
     * Drop the tokens; keep the app registration, so reconnecting is one
     * click through the consent screen rather than a form refill.
     */
    public function disconnect(): RedirectResponse
    {
        abort_unless($this->capabilities->has(Capability::EmailTransportConfigure), 404);

        $provider = MailProviderSettings::current()->provider;

        if (! $provider->isOAuth()) {
            return back()->with('error', __('The selected mail provider does not use a connected mailbox.'));
        }

        $connection = MailOAuthConnection::for($provider);

        $connection->fill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'account_email' => null,
            'last_error' => null,
        ])->save();

        $this->activateConnection();

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'email', 'action' => 'mailbox_disconnected']);

        return back()->with('success', __('Mailbox disconnected. Outgoing email is paused until one is connected again.'));
    }

    /**
     * The same three steps EmailSettingsController::update() ends with,
     * for the same reason: this request must already see the new
     * transport, and the long-running queue worker must not keep sending
     * (or failing) with the old one.
     */
    private function activateConnection(): void
    {
        $this->mailConfig->flush();
        $this->mailConfig->apply();

        Artisan::call('queue:restart');
    }
}
