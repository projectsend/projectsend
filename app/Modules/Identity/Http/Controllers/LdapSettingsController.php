<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Jobs\SyncLdapUsersJob;
use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapEncryption;
use App\Modules\Identity\Ldap\LdapSettings;
use App\Modules\Identity\Ldap\LdapSync;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        private readonly LdapSync $sync,
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
                'username_attribute' => $ldap->username_attribute,
                'auto_provision' => $ldap->auto_provision,
                'auto_approve' => $ldap->auto_approve,
                'sync_daily' => $ldap->sync_daily,
                'sync_deactivates_missing' => $ldap->sync_deactivates_missing,
                'sync_restores_deleted' => $ldap->sync_restores_deleted,
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
            'sync' => $this->sync->last(),
            'sync_preview' => $request->session()->get('ldap_sync_preview'),
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
            'username_attribute' => ['nullable', 'string', 'max:64'],
            'auto_provision' => ['required', 'boolean'],
            'auto_approve' => ['required', 'boolean'],
            'sync_daily' => ['sometimes', 'boolean'],
            'sync_deactivates_missing' => ['sometimes', 'boolean'],
            'sync_restores_deleted' => ['sometimes', 'boolean'],
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
            'username_attribute' => $validated['username_attribute'] ?? null,
            'auto_provision' => (bool) $validated['auto_provision'],
            'auto_approve' => (bool) $validated['auto_approve'],
            'sync_daily' => (bool) ($validated['sync_daily'] ?? $ldap->sync_daily),
            'sync_deactivates_missing' => (bool) ($validated['sync_deactivates_missing'] ?? $ldap->sync_deactivates_missing),
            'sync_restores_deleted' => (bool) ($validated['sync_restores_deleted'] ?? $ldap->sync_restores_deleted),
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

    /**
     * How long a preview vouches for a sync. Long enough to read the list,
     * short enough that it still describes the directory being synced.
     */
    private const PREVIEW_VALID_SECONDS = 600;

    /**
     * Preview a sync in the request, or start a real one in the background.
     *
     * A preview only reads, so it is quick enough to answer here. A real
     * sync may create and switch off accounts, so it is only accepted on
     * the heels of a preview of the settings as they are now saved: the
     * administrator has seen who it touches. It is then SyncLdapUsersJob's
     * to work through, and the screen follows it from LdapSync::last().
     */
    public function sync(Request $request): RedirectResponse
    {
        if ($request->boolean('dry_run')) {
            $preview = $this->sync->run(dryRun: true);

            // Stamped when the preview finished, the moment the screen
            // counts its ten minutes from, so the two never disagree.
            $request->session()->put('ldap_sync_previewed_at', $preview['finished_at']);

            return back()->with('ldap_sync_preview', $preview);
        }

        $previewedAt = $request->session()->get('ldap_sync_previewed_at');
        $savedAt = LdapSettings::current()->updated_at?->getTimestamp() ?? 0;

        if (! is_int($previewedAt) || now()->getTimestamp() - $previewedAt > self::PREVIEW_VALID_SECONDS || $savedAt > $previewedAt) {
            throw ValidationException::withMessages(['sync' => __('Preview the sync again before running it: the last preview is missing, too old, or older than the saved settings.')]);
        }

        $request->session()->forget('ldap_sync_previewed_at');
        $this->sync->markQueued();
        SyncLdapUsersJob::dispatch();

        return back()->with('success', __('Directory sync started.'));
    }
}
