<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Auth\ApiTokens;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Identity\AccountContentDeletion;
use App\Modules\Identity\Erasure\AvailableEmailRule;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\StaffAccounts;
use App\Modules\Identity\TwoFactor\TwoFactorAdministration;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff ("system users") management. Clients are a different population
 * managed by the Clients module: they never appear here.
 *
 * Available on both editions since 2.2.0. A managed installation is sold a
 * number of seats and fills them itself — see Capability::UsersManage for
 * why capacity is the platform's and who fills it is the tenant's.
 *
 * That makes a full installation an ordinary state rather than an error,
 * so `index()` reports the seat position and `create()` refuses to open a
 * form nothing can be submitted through. SeatAllowance::guardStaff() still
 * runs in `store()`: this is the courtesy, that is the rule.
 */
class UsersController extends Controller
{
    public function __construct(
        private readonly DeletedAccountContent $accountContent,
        private readonly AccountContentDeletion $accountDeletion,
        private readonly ApiTokens $apiTokens,
        private readonly StaffAccounts $accounts,
        private readonly SeatAllowance $seats,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'role' => isset($validated['role']) ? (string) $validated['role'] : null,
            'status' => $validated['status'] ?? null,
        ];

        $activeAdminCount = $this->accounts->activeAdministratorCount();

        $users = User::query()
            ->where('type', UserType::Staff)
            ->with('role')
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'], fn (Builder $query, string $role) => $query->where('role_id', (int) $role))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('active', $status === 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $content = $this->accountContent->summarizeMany($users->pluck('id'));
        // One grouped query for the page, not one per row — see
        // ApiTokens::summarizeMany().
        $tokens = $this->apiTokens->summarizeMany($users->pluck('id'));

        $users->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'role_is_system' => $user->role !== null && $user->role->is_system,
            'active' => $user->active,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'created_at' => $user->created_at?->toIso8601String(),
            'is_self' => $user->is($request->user()),
            'is_last_administrator' => $user->active && $activeAdminCount === 1 && $user->role?->is_administrator === true,
            'content' => $content[$user->id] ?? ['files' => 0, 'folders' => 0],
            // Both counts: "does anyone hold a live credential for this
            // account" and "has this account ever used the API at all" are
            // different questions, and the list answers both. An expired
            // token is nobody's credential, but it does say the account has
            // an integration history worth knowing about.
            'api_tokens' => $tokens[$user->id] ?? ['total' => 0, 'active' => 0],
        ]);

        return Inertia::render('users/index', [
            'users' => $users->items(),
            'pagination' => Pagination::meta($users),
            'filters' => $filters,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])->all(),
            // Same rule as the clients list: the picker belongs to the
            // delete dialog, so it is sent to whoever may open one.
            'reassign_candidates' => $this->actor()->can('delete_users')
                ? $this->accountDeletion->candidates($this->actor())
                : [],
            // Null on a self-hosted install: no limit, nothing to say.
            'seats' => $this->seats->staffState(),
        ]);
    }

    public function create(): RedirectResponse|Response
    {
        // Turned away here rather than on submit. Somebody reaching this
        // by link or bookmark used to fill in a name, an email and a
        // password they had to invent, and learn the installation was full
        // from a validation error under the email field — which reads as a
        // fault with the address rather than a fact about the plan.
        $seats = $this->seats->staffState();

        if ($seats !== null && $seats['full']) {
            return redirect()->route('users.index')->with('error', $seats['message']);
        }

        return Inertia::render('users/create', [
            'roles' => $this->roleOptions(),
            'clients' => $this->clientOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new AvailableEmailRule],
            'role_id' => ['required', 'integer', Rule::in($this->accounts->assignableRoleIds($this->actor()))],
            'password' => ['required', 'confirmed', Password::defaults()],
            'assigned_clients' => ['array'],
            // Reach, not a label: see StaffAccounts::assignableClientIds.
            // The list is client-typed already, so this is one rule where
            // an exists() plus a type filter used to be two.
            'assigned_clients.*' => ['integer', Rule::in($this->accounts->assignableClientIds($this->actor()))],
        ]);

        $user = $this->accounts->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => (int) $validated['role_id'],
            'password' => $validated['password'],
        ], $validated['assigned_clients'] ?? []);

        // Same create-without-edit rule as ClientsController::store().
        $target = $this->actor()->can('edit_users')
            ? redirect()->route('users.edit', $user)
            : redirect()->route('users.create');

        return $target->with('success', __('User created.'));
    }

    public function edit(User $user): Response
    {
        abort_unless($user->isStaff(), 404);
        $this->accounts->guardTarget($this->actor(), $user);

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'active' => $user->active,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],
            'roles' => $this->roleOptions(),
            'clients' => $this->clientOptions(),
            'assigned_client_ids' => $user->assignedClients()->pluck('users.id')->map(fn ($id): int => (int) $id)->all(),
            'is_self' => $user->is(auth()->user()),
            'is_last_administrator' => $user->active
                && $this->accounts->isAdministratorRole($user->role_id)
                && $this->accounts->activeAdministratorCount() === 1,
            'content' => $this->accountContent->summarize($user),
            'reassign_candidates' => $this->actor()->can('delete_users')
                ? $this->accountDeletion->candidates($this->actor(), $user->id)
                : [],
            // Read-only, deliberately: an administrator may see that an
            // integration exists and what it is allowed to do, but only
            // the owner can rename, re-scope or revoke it. See ApiTokens.
            'api_tokens' => $this->apiTokens->detailFor($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $this->accounts->guardTarget($this->actor(), $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', 'integer', Rule::in($this->accounts->assignableRoleIds($this->actor()))],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'assigned_clients' => ['array'],
            // Reach, not a label: see StaffAccounts::assignableClientIds.
            // The list is client-typed already, so this is one rule where
            // an exists() plus a type filter used to be two.
            'assigned_clients.*' => ['integer', Rule::in($this->accounts->assignableClientIds($this->actor()))],
        ]);

        // Deactivating yourself is refused here rather than in StaffAccounts
        // because it is a rule about *this* request's actor, not about the
        // account: the API's own equivalent asks the same question of its
        // own caller.
        if ($user->is($this->actor()) && ! $validated['active']) {
            throw ValidationException::withMessages([
                'active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $this->accounts->update($user, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => (int) $validated['role_id'],
            'active' => (bool) $validated['active'],
            'password' => is_string($validated['password'] ?? null) ? $validated['password'] : '',
        ], $validated['assigned_clients'] ?? []);

        return back()->with('success', __('User updated.'));
    }

    /**
     * Remove this account's second factor, for the staff member who has
     * lost their authenticator and their recovery codes.
     */
    public function destroyTwoFactor(User $user, TwoFactorAdministration $twoFactor): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $this->accounts->guardTarget($this->actor(), $user);

        $twoFactor->reset($user);

        return back()->with('success', __('Two-factor authentication removed.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $this->accounts->guardDeletable($this->actor(), $user);

        $validated = $this->accountDeletion->validate($request, $user);

        // Soft-deleting the account and disposing of its files are two
        // separate writes; keep them in one transaction so a failure in the
        // second (e.g. the reassignment target deleted between validation
        // and apply()'s findOrFail) cannot leave the account deleted with
        // its content still pointing at it.
        DB::transaction(function () use ($validated, $user): void {
            $name = $this->accounts->delete($user);
            $this->accountDeletion->apply($validated, $user, $name);
        });

        return redirect()->route('users.index')->with('success', __('User deleted.'));
    }

    /**
     * The signed-in staff member, as every guard in StaffAccounts needs
     * them. Not nullable here: every route on this controller is behind
     * `auth`.
     */
    private function actor(): User
    {
        $actor = auth()->user();
        assert($actor instanceof User);

        return $actor;
    }

    /**
     * Staff-assignable roles, shaped for the form. `client_scoped` tells
     * it which roles need the assigned-clients picker.
     *
     * Which roles those are is StaffAccounts' answer, not this
     * controller's — it is an authority question, and the API asks it too.
     *
     * @return array<int, array{id: int, name: string, is_system: bool, client_scoped: bool}>
     */
    private function roleOptions(): array
    {
        return $this->accounts->assignableRoles($this->actor())
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'client_scoped' => $role->client_scoped,
            ])
            ->values()
            ->all();
    }

    /**
     * The client roster, for the assigned-clients picker — narrowed to
     * what this actor may actually hand out, the same way roleOptions()
     * is narrowed to the roles they may grant.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function clientOptions(): array
    {
        return User::query()->whereIn('id', $this->accounts->assignableClientIds($this->actor()))->orderBy('name')->get()
            ->map(fn (User $client): array => ['id' => $client->id, 'name' => $client->name])
            ->values()->all();
    }
}
