<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Mail\MailOAuthConnection;
use App\Modules\Platform\Notifications\TestEmailNotification;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\MailProvider;
use App\Modules\Platform\Settings\MailProviderSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The master switch for transactional email, the SMTP transport itself
 * (a generic form with provider presets — see MailProvider), and the
 * admin notification recipient list.
 *
 * Transport (provider/host/port/username/password/encryption) is
 * community-edition only (Capability::EmailTransportConfigure) — cloud
 * operates its own relay. Sender identity (from_address/from_name) and
 * the notification toggle/recipients stay editable in both editions.
 */
class EmailSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly MailConfigApplier $mailConfig,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    public function edit(Request $request): Response
    {
        $adminEmails = $this->settings->get(Setting::AdminNotificationEmails);
        $mailProvider = MailProviderSettings::current();

        return Inertia::render('system/settings/email', [
            'email_notifications_enabled' => $this->settings->get(Setting::EmailNotificationsEnabled),
            'admin_notification_emails' => is_array($adminEmails) ? $adminEmails : [],
            'mail_provider' => [
                'provider' => $mailProvider->provider->value,
                'host' => $mailProvider->host ?? '',
                'port' => $mailProvider->port,
                'username' => $mailProvider->username ?? '',
                'has_password' => $mailProvider->password !== null && $mailProvider->password !== '',
                'encryption' => $mailProvider->encryption,
                'from_address' => $mailProvider->from_address ?? '',
                'from_name' => $mailProvider->from_name ?? '',
            ],
            'mail_provider_presets' => array_map(fn (MailProvider $provider): array => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'host' => $provider->defaultHost(),
                'port' => $provider->defaultPort(),
                'oauth' => $provider->isOAuth(),
                'needs_tenant' => $provider->needsTenant(),
            ], MailProvider::cases()),
            // Keyed by provider so the form can switch providers without
            // a round-trip; tokens and the secret never leave the server
            // — only "is one stored" and the connection's health.
            'mail_oauth_connections' => collect(MailProvider::cases())
                ->filter(fn (MailProvider $provider): bool => $provider->isOAuth())
                ->mapWithKeys(function (MailProvider $provider): array {
                    $connection = MailOAuthConnection::for($provider);

                    return [$provider->value => [
                        'client_id' => $connection->client_id ?? '',
                        'has_client_secret' => $connection->client_secret !== null && $connection->client_secret !== '',
                        'tenant_id' => $connection->tenant_id ?? '',
                        'connected' => $connection->usable(),
                        'account_email' => $connection->account_email,
                        'last_refreshed_at' => $connection->last_refreshed_at?->toIso8601String(),
                        'last_error' => $connection->last_error,
                    ]];
                }),
            'test_result' => $request->session()->get('mail_test_result'),
        ]);
    }

    /**
     * One form, one save: the notification toggle, admin recipients, and
     * SMTP transport all persist together.
     */
    public function update(Request $request): RedirectResponse
    {
        $canConfigureTransport = $this->capabilities->has(Capability::EmailTransportConfigure);

        // Peeked at before validation because it decides which rule set
        // the rest of the transport fields get; an unknown value falls
        // through to the SMTP rules, whose `provider` rule then rejects
        // it with the proper validation error.
        $requestedProvider = $canConfigureTransport
            ? MailProvider::tryFrom((string) $request->input('provider'))
            : null;
        $wantsOAuth = $requestedProvider?->isOAuth() ?? false;

        $rules = [
            'email_notifications_enabled' => ['required', 'boolean'],
            'admin_notification_emails' => ['required', 'array', 'min:1'],
            'admin_notification_emails.*' => ['email', 'max:255'],
            // With an OAuth provider the sender is the connected mailbox,
            // not a form field — the form doesn't submit one.
            'from_address' => [$wantsOAuth ? 'nullable' : 'required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ];

        if ($canConfigureTransport) {
            $rules['provider'] = ['required', Rule::in(array_map(fn (MailProvider $p): string => $p->value, MailProvider::cases()))];

            if ($wantsOAuth) {
                $rules += [
                    'client_id' => ['required', 'string', 'max:255'],
                    'client_secret' => ['nullable', 'string', 'max:255'],
                    'tenant_id' => ['nullable', 'string', 'max:255'],
                ];
            } else {
                $rules += [
                    'host' => ['required', 'string', 'max:255'],
                    'port' => ['required', 'integer', 'between:1,65535'],
                    'username' => ['nullable', 'string', 'max:255'],
                    'password' => ['nullable', 'string', 'max:255'],
                    'encryption' => ['required', Rule::in(['none', 'tls', 'ssl'])],
                ];
            }
        }

        $validated = $request->validate($rules);

        $adminEmails = array_values(array_unique($validated['admin_notification_emails']));
        $this->settings->set(Setting::EmailNotificationsEnabled, $validated['email_notifications_enabled']);
        $this->settings->set(Setting::AdminNotificationEmails, $adminEmails);

        $mailProvider = MailProviderSettings::current();

        // Transport fields are simply never read from the request when the
        // capability is absent — a hand-crafted PATCH can't smuggle a
        // custom relay into a cloud install through this endpoint either.
        if ($canConfigureTransport && $requestedProvider !== null) {
            $mailProvider->provider = $requestedProvider;

            if ($wantsOAuth) {
                // The SMTP columns keep their values — switching to an
                // OAuth provider and back must lose nothing.
                $connection = MailOAuthConnection::for($requestedProvider);

                // A different app registration invalidates tokens minted
                // by the old one (the next refresh would present the new
                // client_id against them and die) — drop them now so the
                // page honestly shows "not connected" instead of a
                // connection that fails on first send.
                $clientIdChanged = $connection->client_id !== null
                    && $connection->client_id !== ''
                    && $connection->client_id !== $validated['client_id'];

                $connection->client_id = $validated['client_id'];
                $connection->tenant_id = ($validated['tenant_id'] ?? null) !== null && trim((string) $validated['tenant_id']) !== ''
                    ? trim((string) $validated['tenant_id'])
                    : null;

                // A blank secret keeps whatever is already stored, like
                // the SMTP password below (only `has_client_secret` is
                // ever round-tripped to the browser).
                if (is_string($validated['client_secret'] ?? null) && $validated['client_secret'] !== '') {
                    $connection->client_secret = $validated['client_secret'];
                }

                if ($clientIdChanged) {
                    $connection->fill([
                        'access_token' => null,
                        'refresh_token' => null,
                        'token_expires_at' => null,
                        'account_email' => null,
                        'last_error' => null,
                    ]);
                }

                $connection->save();
            } else {
                $mailProvider->fill([
                    'host' => $validated['host'],
                    'port' => $validated['port'],
                    'username' => $validated['username'] ?? null,
                    'encryption' => $validated['encryption'],
                ]);

                // A blank password keeps whatever is already stored — the field
                // is never round-tripped to the browser (only `has_password` is).
                if (is_string($validated['password'] ?? null) && $validated['password'] !== '') {
                    $mailProvider->password = $validated['password'];
                }
            }
        }

        // Absent while an OAuth provider is selected (the connected
        // mailbox is the sender) — the stored value survives for a later
        // switch back to SMTP.
        if (is_string($validated['from_address'] ?? null) && $validated['from_address'] !== '') {
            $mailProvider->from_address = $validated['from_address'];
        }

        $mailProvider->from_name = $validated['from_name'];
        $mailProvider->save();

        $this->mailConfig->flush();
        $this->mailConfig->apply();

        // The long-running queue worker cached the old config at boot;
        // this signals it to restart so queued/future mail uses the new
        // settings without a manual container restart.
        Artisan::call('queue:restart');

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'email']);

        return back()->with('success', __('Email settings saved.'));
    }

    /**
     * Sends immediately (bypassing the queue, unlike every other
     * notification in this app) to whatever address the admin enters,
     * regardless of the toggle above — this exists specifically to
     * verify SMTP works before switching it on, and needs a real,
     * synchronous result to show, not a "queued" success message.
     */
    public function sendTest(Request $request): RedirectResponse
    {
        abort_unless($this->capabilities->has(Capability::EmailTransportConfigure), 404);

        $validated = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);

        // What "via" means depends on the active transport: host:port
        // only describes SMTP; an OAuth mailer is best named by its
        // mailer key (e.g. "microsoft-graph").
        $mailer = config('mail.default');

        if ($mailer === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $hostPort = (is_string($host) ? $host : '').':'.(is_scalar($port) ? (string) $port : '');
        } else {
            $hostPort = is_string($mailer) ? $mailer : '';
        }

        // Which of the two this is has to travel with the message rather
        // than be inferred from its text: the frontend colours the result,
        // and sniffing for the word "Success" would stop working the
        // moment somebody reads this screen in another language.
        try {
            Notification::route('mail', $validated['recipient'])->notifyNow(new TestEmailNotification);

            $result = [
                'ok' => true,
                'message' => __('Success: test email sent to :email via :hostport.', [
                    'email' => $validated['recipient'],
                    'hostport' => $hostPort,
                ]),
            ];
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => __('Failed to send: :error', ['error' => $e->getMessage()])];
        }

        return back()->with('mail_test_result', $result);
    }
}
