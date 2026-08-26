<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionCategory;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role management (community edition: capability users.manage). The
 * built-in roles keep their names; the administrator role is read-only —
 * it holds every permission by construction.
 */
class RolesController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['system', 'client', 'custom'])],
        ]);
        $filters = ['type' => $validated['type'] ?? null];

        $clientRole = SystemRole::Client->value;

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            // The client role is a system role but a population apart, so it
            // sorts (and filters) as its own "client" type between the staff
            // system roles and custom roles.
            ->when($filters['type'] === 'system', fn (Builder $q) => $q->where('is_system', true)->where('name', '!=', $clientRole))
            ->when($filters['type'] === 'client', fn (Builder $q) => $q->where('name', $clientRole))
            ->when($filters['type'] === 'custom', fn (Builder $q) => $q->where('is_system', false))
            ->orderByRaw('CASE WHEN name = ? THEN 1 WHEN is_system = 1 THEN 0 ELSE 2 END', [$clientRole])
            ->orderByDesc('is_administrator')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'type' => $role->name === $clientRole ? 'client' : ($role->is_system ? 'system' : 'custom'),
                'is_system' => $role->is_system,
                'is_administrator' => $role->is_administrator,
                'users_count' => $role->users_count,
                'permissions_count' => $role->is_administrator ? null : $role->permissions_count,
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles->all(),
            'total_permissions' => count(Permission::cases()),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'catalog' => $this->catalog(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'client_scoped' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        $this->guardGrantablePermissions($request, $validated['permissions'] ?? []);

        $clientScoped = $request->boolean('client_scoped');
        $this->guardScopeRemoval($request, removesScope: ! $clientScoped);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'client_scoped' => $clientScoped,
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);

        $this->activity->log(Action::RoleCreated, subject: $role);

        return redirect()->route('roles.edit', $role)->with('success', __('Role created.'));
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'is_administrator' => $role->is_administrator,
                'client_scoped' => $role->client_scoped,
                'users_count' => $role->users()->count(),
                'permissions' => $role->permissions()->pluck('permission')->all(),
            ],
            'catalog' => $this->catalog(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_administrator) {
            throw ValidationException::withMessages([
                'permissions' => __('The administrator role always has every permission and cannot be edited.'),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'client_scoped' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        // Built-in roles have fixed names and a fixed scope flag; only their
        // permission set is editable. Custom roles can change name + scope.
        if (! $role->is_system) {
            $clientScoped = $request->boolean('client_scoped');
            $this->guardScopeRemoval($request, removesScope: $role->client_scoped && ! $clientScoped);

            $role->update([
                'name' => $validated['name'],
                'client_scoped' => $clientScoped,
            ]);
        }

        $oldPermissions = $role->permissions()->pluck('permission')->all();
        $newPermissions = $validated['permissions'] ?? [];

        // Only what the actor is losing or gaining needs checking: a
        // permission already on the role and left untouched is not being
        // granted by this actor, so editing an unrelated field never
        // requires holding the whole existing set.
        $this->guardGrantablePermissions($request, array_values(array_diff($newPermissions, $oldPermissions)));

        $this->syncPermissions($role, $newPermissions);

        $this->activity->log(Action::RoleUpdated, subject: $role, context: [
            'permissions_added' => array_values(array_diff($newPermissions, $oldPermissions)),
            'permissions_removed' => array_values(array_diff($oldPermissions, $newPermissions)),
        ]);

        return back()->with('success', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => __('Built-in roles cannot be deleted.'),
            ]);
        }

        // Trashed accounts still reference their role; count them too.
        if ($role->users()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('This role is assigned to accounts and cannot be deleted.'),
            ]);
        }

        $name = $role->name;
        $role->delete();

        $this->activity->log(Action::RoleDeleted, context: ['name' => $name]);

        return redirect()->route('roles.index')->with('success', __('Role deleted.'));
    }

    /**
     * A role is a bundle of authority, so minting one is handing authority
     * out — the same rule as assigning a role (UsersController::mayGrant).
     * Without this, a non-administrator holding manage_users could create a
     * role carrying permissions they lack and then hold it themselves.
     * An administrator holds everything, so this never fires for them.
     *
     * @param  list<string>  $permissions  the ones being granted by this request
     */
    private function guardGrantablePermissions(Request $request, array $permissions): void
    {
        $actor = $request->user();
        assert($actor !== null);

        if ($actor->role?->is_administrator === true) {
            return;
        }

        $beyond = array_values(array_diff($permissions, $this->permissions->grantedKeys($actor)));

        if ($beyond !== []) {
            throw ValidationException::withMessages([
                'permissions' => __('You cannot grant permissions your own role does not have: :permissions', [
                    'permissions' => implode(', ', $beyond),
                ]),
            ]);
        }
    }

    /**
     * The same rule for the other half of what a role carries.
     *
     * `client_scoped` decides how much of the library the role reaches,
     * which makes it authority in exactly the sense the docblock above
     * describes -- and the larger part of it, since it is what stands
     * between a limited staff member and every file on the installation.
     * Both writers of the flag went through nothing at all, so
     * `manage_users` alone was enough to mint a role without the limit,
     * or to lift it off the actor's own, and then to hold it.
     *
     * Phrased as "removes the limit" rather than "is not limited", so
     * that only what this request actually changes is checked -- the same
     * reasoning that has guardGrantablePermissions look at the diff.
     * Editing an already-unlimited role's permissions is not this actor
     * lifting a limit, and StaffAccounts::mayGrant is what stops them
     * holding the result either way.
     *
     * Callers resolve the flag with Request::boolean() and hand the same
     * value to this guard and to the write, deliberately. The `boolean`
     * validation rule accepts "0" and 0 as well as false but does not
     * cast, so reading the validated array and comparing it strictly
     * would let a request through here that the model's `boolean` cast
     * then stores as false anyway -- the guard and the write disagreeing
     * about one value is exactly the shape this guard exists to prevent.
     */
    private function guardScopeRemoval(Request $request, bool $removesScope): void
    {
        $actor = $request->user();
        assert($actor !== null);

        if (! $removesScope || ! $actor->isClientScoped()) {
            return;
        }

        throw ValidationException::withMessages([
            'client_scoped' => __('Your own role is limited to the clients assigned to you, so a role you create or edit cannot drop that limit.'),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        RolePermission::query()->where('role_id', $role->id)->delete();

        if ($permissions !== []) {
            RolePermission::query()->insert(array_map(
                fn (string $permission): array => ['role_id' => $role->id, 'permission' => $permission],
                array_values(array_unique($permissions)),
            ));
        }
    }

    /**
     * The permission vocabulary grouped by category, for the matrix UI.
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return array_values(array_map(fn (PermissionCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'permissions' => array_values(array_map(
                fn (Permission $permission): array => [
                    'key' => $permission->value,
                    'label' => $permission->label(),
                ],
                array_filter(
                    Permission::cases(),
                    fn (Permission $permission): bool => $permission->category() === $category,
                ),
            )),
        ], PermissionCategory::cases()));
    }
}
