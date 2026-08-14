<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
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
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/clients', [
            'clients_can_register' => $this->settings->get(Setting::ClientsCanRegister),
            'clients_auto_approve' => $this->settings->get(Setting::ClientsAutoApprove),
            'clients_auto_group' => $this->settings->get(Setting::ClientsAutoGroup),
            'clients_can_select_group' => $this->settings->get(Setting::ClientsCanSelectGroup),
            'clients_membership_deny_cooldown_days' => $this->settings->get(Setting::ClientsMembershipDenyCooldownDays),
            'default_client_storage_quota_mb' => (int) $this->settings->get(Setting::DefaultClientStorageQuotaMb),
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
            'default_client_storage_quota_mb' => ['required', 'integer', 'min:0'],
        ]);

        $this->settings->set(Setting::ClientsCanRegister, $validated['clients_can_register']);
        $this->settings->set(Setting::ClientsAutoApprove, $validated['clients_auto_approve']);
        $this->settings->set(Setting::ClientsAutoGroup, (int) $validated['clients_auto_group']);
        $this->settings->set(Setting::ClientsCanSelectGroup, $validated['clients_can_select_group']);
        $this->settings->set(Setting::ClientsMembershipDenyCooldownDays, (int) $validated['clients_membership_deny_cooldown_days']);
        $this->settings->set(Setting::DefaultClientStorageQuotaMb, (int) $validated['default_client_storage_quota_mb']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'clients']);

        return back();
    }
}
