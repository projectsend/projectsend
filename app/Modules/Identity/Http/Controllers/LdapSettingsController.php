<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapEncryption;
use App\Modules\Identity\Ldap\LdapSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuring the directory.
 *
 * Available in **both** editions and behind no capability — LDAP is an
 * administrator's setting, not an edition difference, so this route sits
 * with the other `can:edit_settings` screens rather than in a
 * `capability:` group.
 *
 * The bind password follows the pattern MailProviderSettings established:
 * it is never sent to the browser (only `has_bind_password`), and a blank
 * submission means "keep the stored one". v1 rendered this credential into
 * the form's HTML `value=` attribute.
 */
class LdapSettingsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
    ) {}

    public function edit(Request $request): Response
    {
        $ldap = LdapSettings::current();

        return Inertia::render('system/settings/ldap', [
            'ldap' => [
                'active' => $ldap->active,
                'host' => $ldap->host,
                'port' => $ldap->port,
                'encryption' => $ldap->encryption->value,
                'ca_cert_path' => $ldap->ca_cert_path,
                'bind_dn' => $ldap->bind_dn,
                // The secret itself never leaves the server.
                'has_bind_password' => $ldap->bind_password !== null && $ldap->bind_password !== '',
                'base_dn' => $ldap->base_dn,
                'user_filter' => $ldap->user_filter,
                'email_attribute' => $ldap->email_attribute,
                'name_attribute' => $ldap->name_attribute,
                'auto_provision' => $ldap->auto_provision,
                'auto_approve' => $ldap->auto_approve,
            ],
            'encryptions' => array_map(
                fn (LdapEncryption $e): array => [
                    'value' => $e->value,
                    'label' => $e->label(),
                    'default_port' => $e->defaultPort(),
                ],
                LdapEncryption::cases(),
            ),
            // Without this an administrator flips a switch that silently
            // never works — the same class of failure as v1's use_tls.
            'extension_available' => extension_loaded('ldap'),
            // Shown only as context beside the directory's own
            // auto_approve: an administrator who sets the two differently
            // should be able to see that they have, rather than wonder why
            // directory accounts behave unlike registrations.
            'clients_auto_approve' => $this->settings->get(Setting::ClientsAutoApprove) === true,
            'test_result' => $request->session()->get('ldap_test_result'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::enum(LdapEncryption::class)],
            'ca_cert_path' => ['nullable', 'string', 'max:255'],
            'bind_dn' => ['nullable', 'string', 'max:255'],
            'bind_password' => ['nullable', 'string', 'max:255'],
            'base_dn' => ['nullable', 'string', 'max:255'],
            'user_filter' => ['nullable', 'string', 'max:255'],
            'email_attribute' => ['required', 'string', 'max:64'],
            'name_attribute' => ['required', 'string', 'max:64'],
            'auto_provision' => ['required', 'boolean'],
            'auto_approve' => ['required', 'boolean'],
        ]);

        $ldap = LdapSettings::current();

        $ldap->fill([
            'active' => (bool) $validated['active'],
            'host' => $validated['host'] ?? null,
            'port' => (int) $validated['port'],
            'encryption' => $validated['encryption'],
            'ca_cert_path' => $validated['ca_cert_path'] ?? null,
            'bind_dn' => $validated['bind_dn'] ?? null,
            'base_dn' => $validated['base_dn'] ?? null,
            'user_filter' => $validated['user_filter'] ?? null,
            'email_attribute' => $validated['email_attribute'],
            'name_attribute' => $validated['name_attribute'],
            'auto_provision' => (bool) $validated['auto_provision'],
            'auto_approve' => (bool) $validated['auto_approve'],
        ]);

        // Blank means "leave it alone", so editing the host does not wipe
        // the credential.
        if (is_string($validated['bind_password'] ?? null) && $validated['bind_password'] !== '') {
            $ldap->bind_password = $validated['bind_password'];
        }

        $ldap->save();

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'ldap']);

        return back()->with('success', __('LDAP settings saved.'));
    }

    /**
     * Bind against the directory and report which stage failed.
     *
     * The one place allowed to be specific about a directory failure: it
     * is behind `edit_settings`, and it is the difference between a
     * working configuration and a checkbox that quietly does nothing. The
     * login form stays deliberately vague.
     */
    public function test(Request $request, LdapDirectory $directory): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $directory->probe($validated['email'] ?? null, $validated['password'] ?? null);

        return back()->with('ldap_test_result', [
            'ok' => $result->ok,
            'stage' => $result->stage,
            'message' => $result->message,
            'dn' => $result->dn,
        ]);
    }
}
