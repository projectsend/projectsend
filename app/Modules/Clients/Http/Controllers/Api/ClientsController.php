<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Support\PollingQuery;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientCustomFieldType;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Clients\Http\Resources\Api\ClientResource;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Client accounts over the API.
 *
 * Clients are `users` rows with type = client, so every response here goes
 * through ClientResource's allowlist rather than the model. `abort_unless
 * ($client->isClient(), 404)` on each single-client route mirrors the web
 * controller: a staff account is not addressable through this surface even
 * by id.
 *
 * Validation rules, custom-field handling and the deletion flow are the
 * web controller's, reused or mirrored field for field — a client created
 * through the API must be indistinguishable from one created in the UI.
 */
class ClientsController extends Controller
{
    public function __construct(
        private readonly PollingQuery $polling,
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
        private readonly ClientStorageUsage $storageUsage,
        private readonly DeletedAccountContent $accountContent,
        private readonly AccountContentDeletion $accountDeletion,
        private readonly StaffLibraryScope $scope,
        private readonly SeatAllowance $seats,
        private readonly ErasureSchedule $erasure,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate($this->polling->rules() + [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        // Narrowed the same way the web listing is, and by the same
        // rule the object routes below are guarded with.
        $viewer = $request->user();
        assert($viewer !== null);

        $query = $this->scope->clients($viewer);

        if (($filters['search'] ?? null) !== null) {
            $search = $filters['search'];
            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('active', $filters['status'] === 'active');
        }

        return ClientResource::collection($this->polling->paginate($request, $query, 'users'));
    }

    /**
     * Mirrors the web controller's guard, as every API twin here does:
     * the token's `edit_clients` says its owner manages clients, not
     * that they manage *this* one.
     */
    private function guardTarget(Request $request, User $client): void
    {
        abort_unless($client->isClient(), 404);

        $viewer = $request->user();
        assert($viewer !== null);

        abort_unless($this->scope->canAssignClient($viewer, $client), 404);
    }

    public function show(Request $request, User $client): ClientResource
    {
        $this->guardTarget($request, $client);


        return $this->resourceFor($client);
    }

    public function store(Request $request): JsonResponse
    {
        $this->seats->guardClient();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new AvailableEmailRule],
            // No `confirmed`: repeating a password is a defence against a
            // human mistyping into a form, and an API caller has no second
            // field to mistype. This installation's password policy still
            // applies — a minimum length, and optionally a check against
            // known breaches. Both are configured under Settings →
            // Security, so read them from there rather than assuming the
            // defaults; a password this endpoint accepts on one
            // installation may be refused on another.
            'password' => ['required', Password::defaults()],
            'storage_quota_mb' => ['nullable', 'integer', 'min:0'],
            'custom_field_values' => ['array'],
        ]);

        $validated['custom_field_values'] = $this->validateCustomFieldValues($request);

        $client = User::create([
            'type' => UserType::Client,
            'active' => true,
            'account_requested' => false,
            'role_id' => Role::query()->where('name', SystemRole::Client->value)->value('id'),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            // 0 means "no custom quota" and inherits the site default at
            // enforcement time — see ClientStorageUsage::quotaMb().
            'storage_quota_mb' => $validated['storage_quota_mb'] ?? 0,
            'email_verified_at' => now(),
        ]);

        $this->activity->log(Action::UserCreated, subject: $client);

        $this->saveCustomFieldValues($client, $validated['custom_field_values'] ?? []);

        if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $client->notify(new ClientWelcomeNotification);
        }

        return $this->resourceFor($client->refresh())->response()->setStatusCode(201);
    }

    public function update(Request $request, User $client): ClientResource
    {
        $this->guardTarget($request, $client);


        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client->id)],
            'active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'storage_quota_mb' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'custom_field_values' => ['sometimes', 'array'],
        ]);

        if ($request->has('custom_field_values')) {
            $validated['custom_field_values'] = $this->validateCustomFieldValues($request, required: false);
        }

        $wasActive = $client->active;
        $passwordChanged = is_string($validated['password'] ?? null) && $validated['password'] !== '';

        // PATCH semantics, unlike the web form which always submits every
        // field: an absent key means "leave alone", not "clear".
        $client->fill(array_intersect_key($validated, array_flip(['name', 'email', 'active'])));

        if (array_key_exists('storage_quota_mb', $validated)) {
            $client->storage_quota_mb = $validated['storage_quota_mb'] ?? 0;
        }

        // Approval, and so the moment the seat is spent — same rule the
        // web edit screen and approve() answer to. Inside the branch, so a
        // capped installation can still edit a client it already holds.
        if (($validated['active'] ?? false) && $client->account_requested) {
            $this->seats->guardClient('active');
            $client->account_requested = false;
        }

        if ($passwordChanged) {
            $client->password = $validated['password'];
        }

        $client->save();

        if (array_key_exists('custom_field_values', $validated)) {
            $this->saveCustomFieldValues($client, $validated['custom_field_values']);
        }

        $this->activity->log(Action::UserUpdated, subject: $client);

        if ($wasActive && ! $client->active) {
            $this->activity->log(Action::UserDeactivated, subject: $client);
        } elseif (! $wasActive && $client->active) {
            $this->activity->log(Action::UserActivated, subject: $client);
        }

        if (($client->wasChanged(['name', 'email', 'active']) || $passwordChanged)
            && $this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $client->notify(new ClientAccountEditedNotification);
        }

        return $this->resourceFor($client->refresh());
    }

    /**
     * Remove a client's two-factor authentication.
     *
     * The remedy for a locked-out account: a client whose authenticator
     * app and recovery codes are both gone cannot sign in, and nobody else
     * can open the account for them either. Afterwards they sign in with
     * their password alone, and — if this installation enforces two-factor
     * authentication for clients — are asked to enrol again on their next
     * request.
     *
     * The client is emailed that this happened, and the action is recorded
     * in the activity log against the caller. Answers 204 whether or not a
     * second factor was actually in force.
     */
    public function destroyTwoFactor(Request $request, User $client, TwoFactorAdministration $twoFactor): JsonResponse
    {
        $this->guardTarget($request, $client);


        $twoFactor->reset($client);

        return response()->json(status: 204);
    }

    /**
     * Delete a client.
     *
     * If the client owns no files or folders, no body is needed.
     *
     * If they do, you must say what happens to that content: send
     * `content_action` as either `cascade_delete` (delete it along with the
     * account) or `reassign`, and in the latter case a `reassign_to_id`
     * naming the active account that inherits it. Omitting the choice is a
     * 422 — there is no default, because one would silently destroy a
     * client's files and the other would silently hand them to somebody
     * else.
     *
     * `GET /clients/{client}` reports the counts so you can decide before
     * calling this.
     */
    public function destroy(Request $request, User $client): JsonResponse
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

        return response()->json(status: 204);
    }

    private function resourceFor(User $client): ClientResource
    {
        return ClientResource::detailed(
            $client,
            customFieldValues: ClientCustomFieldValue::query()
                ->where('user_id', $client->id)
                ->pluck('value', 'client_custom_field_id')
                ->all(),
            storage: $this->storageUsage,
            content: $this->accountContent->summarize($client),
        );
    }

    /**
     * Validated separately from the main rule set, and deliberately so.
     *
     * The per-field rules are built by querying `client_custom_fields`, so
     * they name this installation's actual field ids. Passing them to
     * `$request->validate()` put those ids into the generated OpenAPI
     * document — a document that is committed, served unauthenticated, and
     * supposed to be identical on every install. It described one
     * database's configuration and leaked which custom fields exist.
     *
     * The endpoint still validates exactly as before; only the shape the
     * documentation generator can see has changed, to a plain object.
     *
     * @return array<int, mixed>
     */
    private function validateCustomFieldValues(Request $request, bool $required = true): array
    {
        $rules = $this->customFieldRules($required);

        if ($rules === []) {
            return $request->input('custom_field_values', []);
        }

        return Validator::make($request->all(), $rules)->validate()['custom_field_values'] ?? [];
    }

    /**
     * Mirrors ClientsController::customFieldRules(). On update the required
     * flag is dropped, since PATCH may legitimately omit a field it is not
     * changing — the value already stored satisfies the requirement.
     *
     * @return array<string, array<int, mixed>>
     */
    private function customFieldRules(bool $required = true): array
    {
        $rules = [];

        foreach (ClientCustomField::query()->get() as $field) {
            $key = "custom_field_values.{$field->id}";

            if ($field->type === ClientCustomFieldType::Checkbox) {
                $rules[$key] = ['nullable', 'boolean'];

                continue;
            }

            $rules[$key] = [$required && $field->required ? 'required' : 'nullable', 'string', 'max:2000'];

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
