<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
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
            ], MailProvider::cases()),
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

        $rules = [
            'email_notifications_enabled' => ['required', 'boolean'],
            'admin_notification_emails' => ['required', 'array', 'min:1'],
            'admin_notification_emails.*' => ['email', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ];

        if ($canConfigureTransport) {
            $rules += [
                'provider' => ['required', Rule::in(array_map(fn (MailProvider $p): string => $p->value, MailProvider::cases()))],
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:255'],
                'encryption' => ['required', Rule::in(['none', 'tls', 'ssl'])],
            ];
        }

        $validated = $request->validate($rules);

        $adminEmails = array_values(array_unique($validated['admin_notification_emails']));
        $this->settings->set(Setting::EmailNotificationsEnabled, $validated['email_notifications_enabled']);
        $this->settings->set(Setting::AdminNotificationEmails, $adminEmails);

        $mailProvider = MailProviderSettings::current();

        // Transport fields are simply never read from the request when the
        // capability is absent — a hand-crafted PATCH can't smuggle a
        // custom relay into a cloud install through this endpoint either.
        if ($canConfigureTransport) {
            $mailProvider->fill([
                'provider' => $validated['provider'],
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

        $mailProvider->from_address = $validated['from_address'];
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

        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $hostPort = (is_string($host) ? $host : '').':'.(is_scalar($port) ? (string) $port : '');

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
