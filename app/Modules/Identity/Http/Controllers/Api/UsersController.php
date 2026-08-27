<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Support\PollingQuery;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Identity\AccountContentDeletion;
use App\Modules\Identity\Erasure\AvailableEmailRule;
use App\Modules\Identity\Http\Resources\Api\StaffUserResource;
use App\Modules\Identity\StaffAccounts;
use App\Modules\Identity\TwoFactor\TwoFactorAdministration;
use App\Modules\Identity\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Staff accounts over the API — the API twin of the /users screens.
 *
 * **Community only.** Every route is behind `capability:users.manage`, so
 * a cloud install answers 403 `capability_unavailable`: managed
 * installations create staff accounts outside the application, and an API
 * that could mint them there would be a second, unmanaged door into the
 * same thing.
 * The routes are still registered in every edition so the committed
 * OpenAPI document is identical everywhere — the middleware refuses, the
 * route table does not lie.
 *
 * The rules this enforces are not restated here. Who may be granted which
 * role, who may edit whom, and never leaving the installation without an
 * active administrator all live in StaffAccounts, which the web
 * controller calls too. That sharing is the point: these are invariants
 * about authority, and an invariant implemented twice is an invariant
 * that will eventually hold once.
 *
 * `abort_unless($user->isStaff(), 404)` on each single-account route
 * mirrors the web controller: a client is not addressable through this
 * surface even by id, and has its own endpoints under /clients.
 */
class UsersController extends Controller
{
    public function __construct(
        private readonly PollingQuery $polling,
        private readonly StaffAccounts $accounts,
        private readonly DeletedAccountContent $accountContent,
        private readonly AccountContentDeletion $accountDeletion,
    ) {}

    /**
     * List staff accounts.
     *
     * Clients never appear here — they are a different population with
     * their own endpoints under `/clients`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate($this->polling->rules() + [
            'search' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $query = User::query()->where('type', UserType::Staff)->with('role');

        if (($filters['search'] ?? null) !== null) {
            $search = $filters['search'];
            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (($filters['role_id'] ?? null) !== null) {
            $query->where('role_id', (int) $filters['role_id']);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('active', $filters['status'] === 'active');
        }

        return StaffUserResource::collection($this->polling->paginate($request, $query, 'users'));
    }

    /**
     * Get one staff account.
     *
     * Adds `assigned_client_ids` and the `content` counts that `DELETE`
     * needs, neither of which the listing carries.
     */
    public function show(Request $request, User $user): StaffUserResource
    {
        abort_unless($user->isStaff(), 404);
        $this->accounts->guardTarget($this->actor($request), $user);

        return $this->resourceFor($user);
    }

    /**
     * Create a staff account.
     *
     * `role_id` must name a role the calling token's owner could grant.
     * You cannot hand out authority you do not hold: a caller who is not
     * an administrator may not create one, nor assign any role carrying a
     * permission they lack. `GET /roles` lists what is available to you.
     *
     * The account is created active and with its email already verified —
     * an administrator vouched for the address by sending it.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new AvailableEmailRule],
            'role_id' => ['required', 'integer', Rule::in($this->accounts->assignableRoleIds($actor))],
            // No `confirmed`: repeating a password defends against a human
            // mistyping into a form, and an API caller has no second field
            // to mistype. Password::defaults() still applies.
            'password' => ['required', Password::defaults()],
            'assigned_clients' => ['array'],
            // Only clients you can reach yourself: an unrestricted account may
            // assign any client, a client-scoped one only the clients already
            // assigned to it. Assigning a client hands over everything that
            // client can see, so it follows the same rule as role_id above.
            'assigned_clients.*' => ['integer', Rule::in($this->accounts->assignableClientIds($actor))],
        ]);

        $user = $this->accounts->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => (int) $validated['role_id'],
            'password' => $validated['password'],
        ], array_values($validated['assigned_clients'] ?? []));

        return $this->resourceFor($user->refresh())->response()->setStatusCode(201);
    }

    /**
     * Update a staff account, including the role assigned to it.
     *
     * PATCH semantics: an absent key means "leave alone", not "clear".
     * Sending `assigned_clients` replaces the whole list; omitting it
     * leaves it, except that moving to a role which is not client-scoped
     * clears it either way.
     *
     * Refused with a 422 if the change would leave the installation with
     * no active administrator, or if you would be deactivating yourself.
     */
    public function update(Request $request, User $user): StaffUserResource
    {
        abort_unless($user->isStaff(), 404);

        $actor = $this->actor($request);
        $this->accounts->guardTarget($actor, $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['sometimes', 'integer', Rule::in($this->accounts->assignableRoleIds($actor))],
            'active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'assigned_clients' => ['sometimes', 'array'],
            // Only clients you can reach yourself: an unrestricted account may
            // assign any client, a client-scoped one only the clients already
            // assigned to it. Assigning a client hands over everything that
            // client can see, so it follows the same rule as role_id above.
            'assigned_clients.*' => ['integer', Rule::in($this->accounts->assignableClientIds($actor))],
        ]);

        // The same refusal the web screen makes, and for the same reason:
        // locking yourself out is never what was meant.
        if ($user->is($actor) && ($validated['active'] ?? true) === false) {
            throw ValidationException::withMessages([
                'active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $attributes = array_intersect_key($validated, array_flip(['name', 'email', 'active', 'password']));

        if (array_key_exists('role_id', $validated)) {
            $attributes['role_id'] = (int) $validated['role_id'];
        }

        $this->accounts->update(
            $user,
            $attributes,
            array_key_exists('assigned_clients', $validated)
                ? array_values($validated['assigned_clients'])
                : null,
        );

        return $this->resourceFor($user->refresh());
    }

    /**
     * Delete a staff account.
     *
     * If the account owns no files or folders, no body is needed.
     *
     * If it does, you must say what happens to that content: send
     * `content_action` as either `cascade_delete` (delete it along with
     * the account) or `reassign`, and in the latter case a
     * `reassign_to_id` naming the active account that inherits it.
     * Omitting the choice is a 422 — there is no default, because one
     * would silently destroy files and the other would silently hand them
     * to somebody else. `GET /users/{user}` reports the counts.
     *
     * You cannot delete your own account, and you cannot delete the last
     * active administrator.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);

        $this->accounts->guardDeletable($this->actor($request), $user);

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

        return response()->json(status: 204);
    }

    /**
     * Remove a staff account's two-factor authentication.
     *
     * The remedy for a locked-out account: somebody whose authenticator
     * app and recovery codes are both gone cannot sign in, and nobody else
     * can open the account for them either. Afterwards the account signs
     * in with its password alone, and — if this installation enforces
     * two-factor authentication for staff — is asked to enrol again on its
     * next request.
     *
     * The account holder is emailed that this happened, and the action is
     * recorded in the activity log against the caller. Answers 204 whether
     * or not a second factor was actually in force.
     */
    public function destroyTwoFactor(Request $request, User $user, TwoFactorAdministration $twoFactor): JsonResponse
    {
        abort_unless($user->isStaff(), 404);

        $this->accounts->guardTarget($this->actor($request), $user);

        $twoFactor->reset($user);

        return response()->json(status: 204);
    }

    private function resourceFor(User $user): StaffUserResource
    {
        return StaffUserResource::detailed(
            $user->load('role'),
            content: $this->accountContent->summarize($user),
            assignedClientIds: array_values(
                $user->assignedClients()->pluck('users.id')->map(fn ($id): int => (int) $id)->all()
            ),
        );
    }

    /**
     * The token's owner. Every guard in StaffAccounts is asked on their
     * behalf, never on the token's — a token cannot exceed its owner's
     * permissions (EnsureTokenCan re-checks that on every request), so the
     * owner is the right subject for "may they grant this role".
     */
    private function actor(Request $request): User
    {
        $actor = $request->user();
        assert($actor instanceof User);

        return $actor;
    }
}
