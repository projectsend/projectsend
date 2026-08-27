<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Identity\Erasure\ErasureSchedule;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Creating, changing and deleting staff accounts — the rules and the side
 * effects, shared by the web screens and the API.
 *
 * This exists because the rules here are about *authority*, not
 * convenience. "Nobody hands out authority they do not hold" and "never
 * leave the installation without an active administrator" are invariants,
 * and an invariant enforced in one controller and re-implemented in
 * another is an invariant that will eventually hold in only one of them.
 * The API added a second surface onto this domain; rather than mirror the
 * checks, both surfaces call these.
 *
 * What stays in the controllers is what genuinely differs: the shape of
 * the request (a form posting every field vs. a PATCH sending a subset),
 * validation rules, and the response.
 */
class StaffAccounts
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly PermissionChecker $permissions,
        private readonly ErasureSchedule $erasure,
        private readonly StaffLibraryScope $library,
    ) {}

    /**
     * Nobody hands out authority they do not hold. `manage_users` is a
     * permission like any other, so without this a non-administrator
     * holding it could mint an administrator — or build a custom role
     * carrying `edit_settings`, `delete_others_files`, anything — and
     * assign it, to a new account or to themselves. That turns one
     * permission into every permission and makes the rest of the matrix
     * decorative.
     *
     * An administrator holds every permission by construction, so this is
     * always true for them and the admin experience is unchanged.
     */
    public function mayGrant(User $actor, Role $role): bool
    {
        if ($actor->role?->is_administrator === true) {
            return true;
        }

        if ($role->is_administrator) {
            return false;
        }

        $held = $this->permissions->grantedKeys($actor);
        $granting = $role->permissions()->pluck('permission')->all();

        return array_diff($granting, $held) === [];
    }

    /**
     * Staff-assignable roles: everything except the Client system role,
     * narrowed to what this actor may actually grant.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        return Role::query()
            ->where('name', '!=', SystemRole::Client->value)
            ->orderByDesc('is_administrator')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => $this->mayGrant($actor, $role))
            ->values();
    }

    /**
     * @return list<int>
     */
    public function assignableRoleIds(User $actor): array
    {
        return array_values($this->assignableRoles($actor)->map(fn (Role $role): int => $role->id)->all());
    }

    /**
     * Client ids this actor may put on a staff account's roster — the same
     * rule as mayGrant(), applied to reach instead of to authority.
     *
     * An assigned client is not a label: it is everything that client can
     * see, handed to whoever holds it. So a client-scoped actor may hand
     * out the clients they hold and no others — including to themselves,
     * which is the case that matters, since guardTarget() lets anybody
     * edit their own account and `assigned_clients` was never checked
     * against the actor at all. Without this a scoped staff member with
     * `edit_users` could PATCH their own id with every client id on the
     * installation and read the whole library from then on.
     *
     * An unrestricted actor gets the full roster back rather than null, so
     * every caller can validate against one list instead of composing a
     * conditional rule. That list is already client-typed, which is why it
     * replaces the `exists:users,id where type = client` rule rather than
     * joining it.
     *
     * @return list<int>
     */
    public function assignableClientIds(User $actor): array
    {
        $ids = $this->library->assignableClientIds($actor);

        if ($ids !== null) {
            return $ids;
        }

        return array_values(User::query()->where('type', UserType::Client)
            ->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    /**
     * The same rule applied to an existing account: if the actor could not
     * grant the target's role, they have no business editing or deleting
     * that account either. Without this, a non-administrator holding
     * manage_users could still rename, deactivate or delete an
     * administrator — the role picker would refuse the role, but everything
     * around it would go through.
     */
    public function guardTarget(User $actor, User $target): void
    {
        if ($target->is($actor)) {
            return;
        }

        $role = $target->role;

        abort_unless($role === null || $this->mayGrant($actor, $role), 403);
    }

    /**
     * Refuse any change that would leave the installation without an
     * active administrator.
     */
    public function guardLastAdministrator(User $user, bool $removesAdmin): void
    {
        if (! $removesAdmin || ! $user->active) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->whereKeyNot($user->id)
            ->where('active', true)
            ->whereHas('role', fn ($query) => $query->where('is_administrator', true))
            ->exists();

        if (! $otherActiveAdmins) {
            throw ValidationException::withMessages([
                'role_id' => __('This is the last active administrator account.'),
            ]);
        }
    }

    public function isAdministratorRole(?int $roleId): bool
    {
        return $roleId !== null
            && Role::query()->whereKey($roleId)->where('is_administrator', true)->exists();
    }

    /**
     * The installation's principal administrator: the oldest active one.
     *
     * "Oldest" is the account created first, which on any installation
     * that went through setup is the person who set it up — and on one
     * imported from v1, the first administrator that import created.
     * There is no *flag* for this because the application deliberately has
     * no owner concept: administrators are equal in authority, and
     * inventing a superior one to hold this would be a real change to the
     * permission model in exchange for a greeting.
     *
     * Active, so that a founder who has since left the company does not
     * silently swallow anything addressed here; it moves to the next
     * administrator instead.
     */
    public function mainAdministrator(): ?User
    {
        return User::query()
            ->where('type', UserType::Staff)
            ->where('active', true)
            ->whereHas('role', fn ($query) => $query->where('is_administrator', true))
            ->orderBy('id')
            ->first();
    }

    /**
     * How many staff are active administrators right now — used to flag
     * the sole one in the UI so its delete/demote/deactivate controls
     * can be disabled before the server-side guard ever has to fire.
     */
    public function activeAdministratorCount(): int
    {
        return User::query()
            ->where('type', UserType::Staff)
            ->where('active', true)
            ->whereHas('role', fn ($query) => $query->where('is_administrator', true))
            ->count();
    }

    /**
     * @param  array{name: string, email: string, role_id: int, password: string}  $attributes
     * @param  list<int>  $assignedClients
     */
    public function create(array $attributes, array $assignedClients = []): User
    {
        $user = User::create([
            'type' => UserType::Staff,
            'active' => true,
            'role_id' => $attributes['role_id'],
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
        ]);

        // forceFill, not part of the create() array above: email_verified_at
        // is deliberately absent from User::$fillable — it is a security
        // decision, not an attribute — so mass assignment drops it silently.
        // The intent is real: an account an administrator created on
        // someone's behalf needs no verification step, because they vouched
        // for the address by typing it, and the alternative is a new hire
        // who cannot sign in. (Inert today, since MustVerifyEmail is not
        // enabled on the model; several other creation paths pass the same
        // key into a mass assignment and lose it the same way.)
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->syncAssignedClients($user, $attributes['role_id'], $assignedClients);

        $this->activity->log(Action::UserCreated, subject: $user);

        return $user;
    }

    /**
     * Apply a set of already-validated changes.
     *
     * Every key is optional, so a PATCH sending one field behaves the same
     * as a form sending all of them: whatever is absent keeps its current
     * value, including for the last-administrator guard, which has to
     * reason about the state the account would end up in rather than the
     * state the request happened to mention.
     *
     * @param  array{name?: string, email?: string, role_id?: int, active?: bool, password?: string}  $attributes
     * @param  list<int>|null  $assignedClients  null leaves the assignment alone
     */
    public function update(User $user, array $attributes, ?array $assignedClients = null): User
    {
        $roleId = $attributes['role_id'] ?? $user->role_id;
        $active = $attributes['active'] ?? $user->active;

        $this->guardLastAdministrator(
            $user,
            removesAdmin: $this->isAdministratorRole($user->role_id)
                && (! $this->isAdministratorRole($roleId) || ! $active),
        );

        $wasActive = $user->active;
        $oldRoleId = $user->role_id;
        $oldRoleName = $user->role?->name;

        $user->fill(array_intersect_key($attributes, array_flip(['name', 'email', 'role_id', 'active'])));

        if (is_string($attributes['password'] ?? null) && $attributes['password'] !== '') {
            $user->password = $attributes['password'];
        }

        $user->save();

        if ($assignedClients !== null) {
            $this->syncAssignedClients($user, (int) $user->role_id, $assignedClients);
        } elseif ($oldRoleId !== $user->role_id) {
            // The role moved but the caller said nothing about clients. A
            // role that is not client-scoped must not keep a stale roster,
            // so re-run the sync with what is already stored and let it
            // decide.
            $this->syncAssignedClients(
                $user,
                (int) $user->role_id,
                array_values($user->assignedClients()->pluck('users.id')->map(fn ($id): int => (int) $id)->all()),
            );
        }

        $context = [];
        if ($oldRoleId !== $user->role_id) {
            $user->load('role');
            $context['role'] = ['from' => $oldRoleName, 'to' => $user->role?->name];
        }

        $this->activity->log(Action::UserUpdated, subject: $user, context: $context);

        if ($wasActive && ! $user->active) {
            $this->activity->log(Action::UserDeactivated, subject: $user);
        } elseif (! $wasActive && $user->active) {
            $this->activity->log(Action::UserActivated, subject: $user);
        }

        return $user;
    }

    /**
     * Every refusal that applies to deleting a staff account, in the order
     * the screens ask them.
     */
    public function guardDeletable(User $actor, User $target): void
    {
        $this->guardTarget($actor, $target);

        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        $this->guardLastAdministrator($target, removesAdmin: $this->isAdministratorRole($target->role_id));
    }

    /**
     * Soft-delete the account, schedule its permanent erasure and record
     * it. Returns the name, which the caller needs afterwards for the
     * content-reassignment step — by then the model is trashed and reading
     * it back is needless ceremony.
     *
     * **This is only half of deleting somebody.** What happens to the
     * files and folders they own is the other half, and it lives in
     * AccountContentDeletion: validate() to make the caller choose
     * between cascading and reassigning, apply() to carry it out. A
     * caller that stops here leaves their content pointing at an account
     * that no longer exists.
     *
     * The trap is that it looks like it works. validate() returns an
     * empty array when the account owns nothing, so an account with no
     * files deletes perfectly through this method alone — and keeps
     * doing so until somebody deletes a colleague who had actually done
     * some work. Both existing callers pair the two; a new one must too.
     */
    public function delete(User $user): string
    {
        $name = $user->name;

        $this->erasure->apply($user);
        $user->delete();

        $this->activity->log(Action::UserDeleted, context: ['name' => $name]);

        return $name;
    }

    /**
     * Sync a staff member's assigned clients — but only for a client-scoped
     * role; any other role clears the list.
     *
     * @param  list<int>  $clientIds
     */
    public function syncAssignedClients(User $user, int $roleId, array $clientIds): void
    {
        $scoped = Role::query()->whereKey($roleId)->where('client_scoped', true)->exists();

        $user->assignedClients()->sync($scoped ? $clientIds : []);
    }
}
