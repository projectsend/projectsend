<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Folders\ClientHomeFolders;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly ClientHomeFolders $homeFolders,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/clients', [
            'clients_can_register' => $this->settings->get(Setting::ClientsCanRegister),
            'clients_auto_approve' => $this->settings->get(Setting::ClientsAutoApprove),
            'clients_auto_group' => $this->settings->get(Setting::ClientsAutoGroup),
            'clients_can_select_group' => $this->settings->get(Setting::ClientsCanSelectGroup),
            'clients_membership_deny_cooldown_days' => $this->settings->get(Setting::ClientsMembershipDenyCooldownDays),
            'client_invitation_expiry_hours' => $this->settings->get(Setting::ClientInvitationExpiryHours),
            'default_client_storage_quota_mb' => (int) $this->settings->get(Setting::DefaultClientStorageQuotaMb),
            'clients_can_preview_files' => $this->settings->get(Setting::ClientsCanPreviewFiles),
            'clients_home_folders' => $this->settings->get(Setting::ClientsHomeFolders),
            // What the button beside the switch would actually do, so it can
            // say "3 clients have no folder yet" instead of asking somebody
            // to press it and find out.
            'clients_without_home' => $this->homeFolders->pendingCount(),
            'groups' => Group::query()->orderBy('name')->get()
                ->map(fn (Group $group): array => ['id' => $group->id, 'name' => $group->name])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clients_can_register' => ['required', 'boolean'],
            'clients_auto_approve' => ['required', 'boolean'],
            'clients_auto_group' => ['required', 'integer', Rule::in([0, ...Group::query()->pluck('id')->all()])],
            'clients_can_select_group' => ['required', Rule::in(['none', 'public'])],
            'clients_membership_deny_cooldown_days' => ['required', 'integer', 'min:0', 'max:365'],
            'client_invitation_expiry_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'default_client_storage_quota_mb' => ['required', 'integer', 'min:0'],
            'clients_can_preview_files' => ['required', 'boolean'],
            'clients_home_folders' => ['required', 'boolean'],
        ]);

        $this->settings->set(Setting::ClientsCanRegister, $validated['clients_can_register']);
        $this->settings->set(Setting::ClientsAutoApprove, $validated['clients_auto_approve']);
        $this->settings->set(Setting::ClientsAutoGroup, (int) $validated['clients_auto_group']);
        $this->settings->set(Setting::ClientsCanSelectGroup, $validated['clients_can_select_group']);
        $this->settings->set(Setting::ClientsMembershipDenyCooldownDays, (int) $validated['clients_membership_deny_cooldown_days']);
        $this->settings->set(Setting::ClientInvitationExpiryHours, (int) $validated['client_invitation_expiry_hours']);
        $this->settings->set(Setting::DefaultClientStorageQuotaMb, (int) $validated['default_client_storage_quota_mb']);
        $this->settings->set(Setting::ClientsCanPreviewFiles, $validated['clients_can_preview_files']);
        // Saving the switch deliberately creates nothing. Existing clients
        // get a folder when somebody presses the button, so that turning
        // this on, looking at it, and turning it off again leaves the
        // library exactly as it was.
        $this->settings->set(Setting::ClientsHomeFolders, $validated['clients_home_folders']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'clients']);

        return back();
    }

    /**
     * Create the missing home folders, on request.
     *
     * Its own endpoint rather than part of saving the form, because it is a
     * different kind of act: the form records a preference, this one writes
     * a folder for every client on the installation. Wrapping the second
     * inside the first would mean an administrator could not try the
     * setting without committing to it.
     */
    public function backfillHomeFolders(Request $request): RedirectResponse
    {
        abort_unless($this->homeFolders->enabled(), 403, 'Client folders are switched off.');

        $result = $this->homeFolders->backfill();

        $this->activity->log(Action::SettingsUpdated, context: [
            'section' => 'clients',
            'action' => 'client_home_folders_backfill',
            'created' => $result['created'],
            'total' => $result['total'],
        ]);

        // Counts rather than "Done": on an installation with hundreds of
        // clients the administrator wants to know how many there were and
        // how many are new, and the difference between "created 200" and
        // "created 0, they already had one" is the whole answer.
        return back()->with('success', trans_choice(
            '{0}Every client already had a folder.|[1,*]:created of :total clients got a folder. :existing already had one.',
            $result['created'],
            [
                'created' => (string) $result['created'],
                'total' => (string) $result['total'],
                'existing' => (string) $result['existing'],
            ],
        ));
    }
}
