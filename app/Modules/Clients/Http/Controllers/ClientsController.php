<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientCustomFieldType;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Clients\Models\ClientCustomField;
use App\Modules\Clients\Models\ClientCustomFieldValue;
use App\Modules\Clients\Notifications\ClientAccountEditedNotification;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Identity\AccountContentDeletion;
use App\Modules\Identity\Erasure\AvailableEmailRule;
use App\Modules\Identity\Erasure\ErasureSchedule;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\TwoFactor\TwoFactorAdministration;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client management — the recipients files are shared with. Available
 * in BOTH editions (clients are never portal-provisioned seats).
 * Strictly clients: staff accounts 404 here.
 */
class ClientsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
        private readonly ClientStorageUsage $storageUsage,
        private readonly DeletedAccountContent $accountContent,
        private readonly AccountContentDeletion $accountDeletion,
        private readonly StaffLibraryScope $scope,
        private readonly SeatAllowance $seats,
        private readonly ErasureSchedule $erasure,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        // Narrowed by the same rule the buttons on each row are guarded
        // with. A client-scoped staff member is not shown the name and
        // email of somebody they can reach nothing of — the same thing
        // MembershipRequest::approvableBy does for its queue.
        $viewer = $request->user();
        assert($viewer !== null);

        $clients = $this->scope->clients($viewer)
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('active', $status === 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $content = $this->accountContent->summarizeMany($clients->pluck('id'));

        $clients->through(fn (User $client): array => [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'active' => $client->active,
            'account_requested' => $client->account_requested,
            'created_at' => $client->created_at?->toIso8601String(),
            'content' => $content[$client->id] ?? ['files' => 0, 'folders' => 0],
        ]);

        return Inertia::render('clients/index', [
            'clients' => $clients->items(),
            'pagination' => Pagination::meta($clients),
            'filters' => $filters,
            // Only for somebody who may actually reassign: the picker is
            // part of the delete dialog, and React filtering it out of the
            // page is not the same as it never being on the page.
            'reassign_candidates' => $viewer->can('delete_clients')
                ? $this->accountDeletion->candidates($viewer)
                : [],
            // Null on a self-hosted install: no limit, nothing to say.
            'seats' => $this->seats->clientState(),
        ]);
    }

    public function create(): RedirectResponse|Response
    {
        // The same courtesy UsersController::create() does: a full
        // installation is an ordinary state on a managed plan, so say so
        // before somebody fills in a form that cannot be submitted. The
        // guard in store() is still the rule; this is only the door.
        $seats = $this->seats->clientState();

        if ($seats !== null && $seats['full']) {
            return redirect()->route('clients.index')->with('error', $seats['message']);
        }

        return Inertia::render('clients/create', [
            'custom_fields' => $this->customFieldDefinitions(),
            'default_storage_quota_mb' => (int) $this->settings->get(Setting::DefaultClientStorageQuotaMb),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // A client created here is approved by construction, so it counts
        // immediately — unlike a self-registration awaiting a decision.
        $this->seats->guardClient();

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new AvailableEmailRule],
            'password' => ['required', 'confirmed', Password::defaults()],
            'storage_quota_mb' => ['nullable', 'integer', 'min:0'],
        ], $this->customFieldRules()));

        $client = User::create([
            'type' => UserType::Client,
            'active' => true,
            'account_requested' => false,
            'role_id' => Role::query()->where('name', SystemRole::Client->value)->value('id'),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            // 0 (including an omitted field) means "no custom quota" —
            // it inherits Setting::DefaultClientStorageQuotaMb at
            // enforcement time (see ClientStorageUsage::quotaMb()), not
            // baked in here, so a later change to the site default
            // keeps applying to this client automatically.
            'storage_quota_mb' => $validated['storage_quota_mb'] ?? 0,
            'email_verified_at' => now(),
        ]);

        $this->activity->log(Action::UserCreated, subject: $client);

        $this->saveCustomFieldValues($client, $validated['custom_field_values'] ?? []);

        if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $client->notify(new ClientWelcomeNotification);
        }

        // A role can hold create_clients without edit_clients, and the edit
        // page this used to land on unconditionally answers such a role
        // with a 403 — after the client was created, logged and welcomed.
        // Fall back to the create form: it shares this route's own gate, so
        // it is reachable by exactly whoever just created the record, and
        // the success toast shows there.
        $target = $request->user()?->can('edit_clients')
            ? redirect()->route('clients.edit', $client)
            : redirect()->route('clients.create');

        return $target->with('success', __('Client created.'));
    }

    /**
     * The one question every route binding a client has to ask.
     *
     * A permission is not a boundary: `edit_clients` says this staff
     * member manages clients, not that they manage *this* one — the same
     * rule ClientFilesController::index applies one route over. 404
     * rather than 403, so a client outside the roster is not
     * distinguishable from one that is not there.
     */
    private function guardTarget(Request $request, User $client): void
    {
        abort_unless($client->isClient(), 404);

        $viewer = $request->user();
        assert($viewer !== null);

        abort_unless($this->scope->canAssignClient($viewer, $client), 404);
    }

    public function edit(Request $request, User $client): Response
    {
        $this->guardTarget($request, $client);


        return Inertia::render('clients/edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'active' => $client->active,
                'account_requested' => $client->account_requested,
                'storage_quota_mb' => $client->storage_quota_mb,
                'two_factor_enabled' => $client->hasTwoFactorEnabled(),
            ],
            'default_storage_quota_mb' => (int) $this->settings->get(Setting::DefaultClientStorageQuotaMb),
            'storage_used_mb' => (int) ceil($this->storageUsage->usedBytes($client) / 1024 / 1024),
            'custom_fields' => $this->customFieldDefinitions(),
            'custom_field_values' => ClientCustomFieldValue::query()
                ->where('user_id', $client->id)
                ->pluck('value', 'client_custom_field_id'),
            'content' => $this->accountContent->summarize($client),
            'reassign_candidates' => $request->user()?->can('delete_clients') === true
                ? $this->accountDeletion->candidates($request->user(), $client->id)
                : [],
        ]);
    }

    public function update(Request $request, User $client): RedirectResponse
    {
        $this->guardTarget($request, $client);


        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client->id)],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'storage_quota_mb' => ['nullable', 'integer', 'min:0'],
        ], $this->customFieldRules()));

        $wasActive = $client->active;
        $passwordChanged = is_string($validated['password'] ?? null) && $validated['password'] !== '';

        $client->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'active' => $validated['active'],
            // The edit form always submits this field — an empty value
            // means the admin explicitly cleared it (ConvertEmptyStringsToNull
            // turns it into null before validation), not "leave unchanged".
            // 0 = inherit the site default, same as a brand-new client.
            'storage_quota_mb' => $validated['storage_quota_mb'] ?? 0,
        ]);

        // Activating a pending account through the edit screen counts as
        // approval and clears the request flag — which is the moment a
        // seat is spent, so the cap is asked here for the same reason
        // AccountRequestsController::approve() asks it one screen over.
        // Inside the branch, not above it: an installation at its cap must
        // still be able to rename a client it already has.
        if ($client->account_requested && $validated['active']) {
            $this->seats->guardClient('active');
            $client->account_requested = false;
        }

        if ($passwordChanged) {
            $client->password = $validated['password'];
        }

        $client->save();

        $this->saveCustomFieldValues($client, $validated['custom_field_values'] ?? []);

        $this->activity->log(Action::UserUpdated, subject: $client);

        if ($wasActive && ! $client->active) {
            $this->activity->log(Action::UserDeactivated, subject: $client);
        } elseif (! $wasActive && $client->active) {
            $this->activity->log(Action::UserActivated, subject: $client);
        }

        // Skip a no-op resubmit (same name/email/active, no new password).
        if (($client->wasChanged(['name', 'email', 'active']) || $passwordChanged)
            && $this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $client->notify(new ClientAccountEditedNotification);
        }

        return back()->with('success', __('Client updated.'));
    }

    /**
     * Remove this account's second factor, for the client who has lost
     * their authenticator and their recovery codes.
     */
    public function destroyTwoFactor(Request $request, User $client, TwoFactorAdministration $twoFactor): RedirectResponse
    {
        $this->guardTarget($request, $client);


        $twoFactor->reset($client);

        return back()->with('success', __('Two-factor authentication removed.'));
    }

    public function destroy(Request $request, User $client): RedirectResponse
    {
        $this->guardTarget($request, $client);


        $validated = $this->accountDeletion->validate($request, $client);

        // Soft-deleting the account and disposing of its files are two
        // separate writes; keep them in one transaction so a failure in the
        // second (e.g. the reassignment target deleted between validation
        // and apply()'s findOrFail) cannot leave the account deleted with
        // its content still pointing at it.
        //
        // The erasure stamp goes inside for the same reason: a deletion
        // that rolls back must not leave a live account carrying a date
        // on which it would be erased.
        DB::transaction(function () use ($validated, $client): void {
            $name = $client->name;
            $this->erasure->apply($client);
            $client->delete();

            $this->activity->log(Action::UserDeleted, context: ['name' => $name]);

            $this->accountDeletion->apply($validated, $client, $name);
        });

        return redirect()->route('clients.index')->with('success', __('Client deleted.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customFieldDefinitions(): array
    {
        return array_values(ClientCustomField::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ClientCustomField $field): array => [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type->value,
                'options' => $field->options,
                'required' => $field->required,
            ])
            ->all());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function customFieldRules(): array
    {
        $rules = [];

        foreach (ClientCustomField::query()->get() as $field) {
            $key = "custom_field_values.{$field->id}";

            // Checkboxes are never hard-required here — "required" only
            // drives the asterisk shown on the form, not a forced check.
            if ($field->type === ClientCustomFieldType::Checkbox) {
                $rules[$key] = ['nullable', 'boolean'];

                continue;
            }

            $rules[$key] = [$field->required ? 'required' : 'nullable', 'string', 'max:2000'];

            if ($field->type === ClientCustomFieldType::Select && is_array($field->options)) {
                $rules[$key][] = Rule::in($field->options);
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, mixed>  $values  field id => submitted value
     */
    private function saveCustomFieldValues(User $client, array $values): void
    {
        foreach (ClientCustomField::query()->get() as $field) {
            $submitted = $values[$field->id] ?? null;
            $value = $field->type === ClientCustomFieldType::Checkbox
                ? ($submitted ? '1' : '0')
                : (is_string($submitted) ? $submitted : null);

            ClientCustomFieldValue::query()->updateOrCreate(
                ['client_custom_field_id' => $field->id, 'user_id' => $client->id],
                ['value' => $value === '' ? null : $value],
            );
        }
    }
}
