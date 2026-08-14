<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientFieldContext;
use App\Modules\Clients\ClientPortalCustomFields;
use App\Modules\Clients\ClientProvisioning;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Rules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client self-registration (v1's register.php) — client-only by
 * construction, gated by the clients_can_register setting. When
 * clients_auto_approve is off, the account is created inactive and
 * lands in the account-requests queue.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly ClientPortalCustomFields $customFields,
        private readonly ClientProvisioning $provisioning,
    ) {}

    public function create(): Response
    {
        abort_unless($this->settings->get(Setting::ClientsCanRegister) === true, 404);

        return Inertia::render('auth/register', [
            'auto_approve' => $this->settings->get(Setting::ClientsAutoApprove) === true,
            'selectable_groups' => $this->selectableGroups()
                ->map(fn (Group $group): array => ['id' => $group->id, 'name' => $group->name])
                ->values()
                ->all(),
            'custom_fields' => $this->customFields->rows(ClientFieldContext::Registration, null),
        ]);
    }

    /**
     * Groups a registrant may request membership to, per the
     * clients_can_select_group setting.
     *
     * @return Collection<int, Group>
     */
    private function selectableGroups(): Collection
    {
        return match ($this->settings->get(Setting::ClientsCanSelectGroup)) {
            'public' => Group::query()->where('public', true)->orderBy('name')->get(),
            default => Group::query()->whereRaw('1 = 0')->get(),
        };
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->settings->get(Setting::ClientsCanRegister) === true, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'groups' => ['array'],
            'groups.*' => ['integer', Rule::in($this->selectableGroups()->pluck('id')->all())],
            ...$this->customFields->rules(ClientFieldContext::Registration, null),
            // Exactly one verification per request. v1's registration path
            // verified twice for reCAPTCHA v2 — the second time on a token
            // its own first call had already consumed — which made
            // self-registration impossible to complete.
            ...Rules::captcha(CaptchaForm::Register),
        ]);

        $autoApprove = $this->provisioning->autoApproves();

        // Creation, approval state, the auto-join group and the
        // administrator notification are shared with LDAP provisioning —
        // see ClientProvisioning. What stays here is what only a
        // registration form has: custom fields and requested groups.
        $client = $this->provisioning->provision(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            Action::ClientSelfRegistered,
        );

        $this->customFields->save($client, ClientFieldContext::Registration, $validated['custom_field_values'] ?? []);

        $autoGroupId = (int) $this->settings->get(Setting::ClientsAutoGroup);

        // Requested groups wait for staff approval.
        foreach (array_unique($validated['groups'] ?? []) as $groupId) {
            if ((int) $groupId === $autoGroupId) {
                continue;
            }

            $membershipRequest = MembershipRequest::query()->create([
                'group_id' => $groupId,
                'user_id' => $client->id,
            ]);

            $this->activity->log(Action::GroupMembershipRequested, $client, $membershipRequest->group);
        }

        return redirect()->route('login')->with(
            'status',
            $autoApprove
                ? __('Your account has been created. You can log in now.')
                : __('Your account request has been received. You will be able to log in once it is approved.'),
        );
    }
}
