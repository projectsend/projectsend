<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Auth\ApiTokens;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Identity\AccountConversion;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\StaffAccounts;
use App\Modules\Identity\UserType;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moving an account between staff and clients.
 *
 * Community edition only, by the same route group as every other
 * staff-account screen — managed installations create staff accounts
 * outside the application, so a converter there would be a second,
 * unmanaged way to create one.
 *
 * The rules live in AccountConversion, which calls StaffAccounts for the
 * authority questions. This controller is the request shape and the
 * response, plus the counts the confirmation dialog needs in order to say
 * what it is about to do — which is the whole safety story of the feature,
 * so the numbers are computed in bulk for the page rather than per row.
 */
class AccountConversionController extends Controller
{
    public function __construct(
        private readonly AccountConversion $conversion,
        private readonly StaffAccounts $accounts,
        private readonly DeletedAccountContent $accountContent,
        private readonly ApiTokens $apiTokens,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'direction' => ['nullable', Rule::in(['to_client', 'to_staff'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $direction = $validated['direction'] ?? 'to_client';
        $search = $validated['search'] ?? null;
        $actor = $this->actor($request);

        $accounts = User::query()
            ->where('type', $direction === 'to_client' ? UserType::Staff : UserType::Client)
            ->with('role')
            ->when($search, fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // One query each for the page, never one per row — this screen
        // paginates 25 at a time.
        $ids = $accounts->pluck('id');
        $content = $this->accountContent->summarizeMany($ids);
        $tokens = $this->apiTokens->summarizeMany($ids);
        $memberships = $this->groupMembershipCounts($ids);
        $shared = $this->fileAssignmentCounts($ids);
        $managedBy = $this->managedByCounts($ids);
        $manages = $this->managesCounts($ids);

        $activeAdminCount = $this->accounts->activeAdministratorCount();

        $accounts->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'active' => $user->active,
            'account_requested' => $user->account_requested,
            'is_self' => $user->is($actor),
            'is_last_administrator' => $user->active
                && $this->accounts->isAdministratorRole($user->role_id)
                && $activeAdminCount === 1,
            // Drives the password field from optional to required, so the
            // form says why before it refuses rather than after.
            'requires_new_password' => $this->conversion->requiresNewPassword($user),
            // Which of the two reasons it is required, so the form can say
            // the true one.
            'auth_source' => $user->auth_source->value,
            // Everything the confirmation dialog enumerates, so it can name
            // real numbers rather than say "some data may be affected".
            'consequences' => [
                'api_tokens' => $tokens[$user->id]['total'] ?? 0,
                'assigned_clients' => $manages[$user->id] ?? 0,
                'managed_by' => $managedBy[$user->id] ?? 0,
                'group_memberships' => $memberships[$user->id] ?? 0,
                'files_shared_with_them' => $shared[$user->id] ?? 0,
                'content' => $content[$user->id] ?? ['files' => 0, 'folders' => 0],
            ],
        ]);

        return Inertia::render('users/convert', [
            'direction' => $direction,
            'filters' => ['search' => $search],
            'accounts' => $accounts->items(),
            'pagination' => Pagination::meta($accounts),
            'roles' => $this->accounts->assignableRoles($actor)
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_system' => $role->is_system,
                    'client_scoped' => $role->client_scoped,
                ])->all(),
            // Narrowed like `roles` beside it: the picker offers what this
            // actor may hand out, which is what store() will accept.
            'clients' => User::query()->whereIn('id', $this->accounts->assignableClientIds($actor))->orderBy('name')->get()
                ->map(fn (User $client): array => ['id' => $client->id, 'name' => $client->name])
                ->values()->all(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $actor = $this->actor($request);

        $direction = $request->validate([
            'direction' => ['required', Rule::in(['to_client', 'to_staff'])],
        ])['direction'];

        if ($direction === 'to_client') {
            abort_unless($user->isStaff(), 404);

            $this->conversion->guardToClient($actor, $user);
            $this->conversion->toClient($user);

            return back()->with('success', __('Account converted to a client.'));
        }

        abort_unless($user->isClient(), 404);

        $this->conversion->guardToStaff($actor, $user);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::in($this->accounts->assignableRoleIds($actor))],
            'assigned_clients' => ['array'],
            'assigned_clients.*' => [
                'integer',
                // Reach, not a label: see StaffAccounts::assignableClientIds.
                Rule::in($this->accounts->assignableClientIds($actor)),
                Rule::notIn([$user->id]),
            ],
            // Required only for an account whose credential lives in the
            // directory; see AccountConversion::requiresNewPassword(). The
            // service refuses it too, so a future caller cannot skip it.
            'password' => $this->conversion->requiresNewPassword($user)
                ? ['required', 'string', Password::defaults()]
                : ['nullable', Password::defaults()],
        ]);

        $this->conversion->toStaff(
            $user,
            (int) $validated['role_id'],
            array_values($validated['assigned_clients'] ?? []),
            $validated['password'] ?? null,
        );

        return back()->with('success', __('Account converted to staff.'));
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function groupMembershipCounts(Collection $ids): array
    {
        return $this->countBy('group_members', 'user_id', $ids);
    }

    /**
     * Files shared directly with this account. `file_assignments` is
     * polymorphic — a row points at either a client or a group — so the
     * type has to be pinned or group assignments would be counted as if
     * they belonged to a user with the same id.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function fileAssignmentCounts(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('file_assignments')
            ->where('assignable_type', User::class)
            ->whereIn('assignable_id', $ids)
            ->selectRaw('assignable_id as owner_id, count(*) as total')
            ->groupBy('assignable_id')
            ->pluck('total', 'owner_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * How many staff members have this client in their assigned roster.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function managedByCounts(Collection $ids): array
    {
        return $this->countBy('staff_client_assignments', 'client_id', $ids);
    }

    /**
     * How many clients this staff member manages.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function managesCounts(Collection $ids): array
    {
        return $this->countBy('staff_client_assignments', 'staff_id', $ids);
    }

    /**
     * One grouped query per concern, for the whole page. A per-row count
     * here would be an N+1 on a screen that paginates 25 at a time.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function countBy(string $table, string $column, Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->selectRaw("{$column} as owner_id, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'owner_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        assert($actor instanceof User);

        return $actor;
    }
}
