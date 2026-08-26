<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving an account between the two populations: a staff member becomes a
 * client, or a client becomes a staff member.
 *
 * The two populations share one `users` table and are told apart by
 * `type`, so a conversion is a small write — which is exactly why it needs
 * a service. The authority questions it has to answer ("may this actor
 * touch this account", "may they grant this role", "does this leave the
 * installation without an administrator") are the same ones creating and
 * deleting a staff account ask, and they already have one answer in
 * StaffAccounts. This calls that rather than restating it.
 *
 * What it deletes is deliberately minimal: only what would be unsafe or
 * wrong for the new type. Group memberships, file assignments and custom
 * field values survive untouched and inert — `Group::members()` and the
 * client scopes filter on `type`, so they stop being read while the
 * account is staff and start again if it is converted back. That is what
 * makes a round trip restore the account rather than approximate it.
 */
class AccountConversion
{
    public function __construct(
        private readonly StaffAccounts $accounts,
        private readonly ActivityLogger $activity,
        private readonly StaffLibraryScope $library,
    ) {}

    /**
     * @throws ValidationException
     */
    public function guardToClient(User $actor, User $target): void
    {
        $this->guardSelf($actor, $target);

        // Only on this direction. "Could the actor have granted the
        // target's current role" is a real question when the target is
        // staff, because their role *is* authority over this installation.
        // Asking it of a client is meaningless: the Client system role
        // grants `upload` and `create_own_folders`, so a User Manager who
        // happens not to hold `upload` would be refused permission to
        // promote anybody — a refusal about nothing.
        $this->accounts->guardTarget($actor, $target);

        // Belt and braces: through this screen it cannot fire, because
        // demoting the sole active administrator requires an actor who is
        // themselves an active administrator (guardTarget above), which
        // would make two. It is kept because AccountConversion is a service
        // and the next caller may not have that property.
        //
        // StaffAccounts phrases this refusal on the `role_id` key, because
        // its callers are forms with a role picker. This one is not — a
        // demotion has no role field — so the message would otherwise land
        // on a key nothing renders and the refusal would look like a
        // silent no-op.
        try {
            $this->accounts->guardLastAdministrator(
                $target,
                removesAdmin: $this->accounts->isAdministratorRole($target->role_id),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'user' => $e->validator->errors()->first('role_id'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function guardToStaff(User $actor, User $target): void
    {
        $this->guardSelf($actor, $target);

        // No guardTarget here — see guardToClient(). It asks "could the
        // actor have granted the target's role", which is meaningless of
        // a client; what limits a promotion is the role being *granted*,
        // and the caller enforces that by validating role_id against
        // StaffAccounts::assignableRoleIds().
        //
        // That answers the question about the role. It does not answer
        // the one about the target, and the target here is a client
        // account: the same object every other route that binds one
        // holds to the actor's own roster. A promotion is the most
        // far-reaching thing that can be done to a client — it takes
        // their portal access away, makes their assignments inert, and
        // leaves them holding staff permissions the actor chose — so
        // reaching one outside that roster through this door and no
        // other is not a rule, it is a gap. 404 rather than 403, like
        // the clients routes and like the isClient() check the caller
        // makes on the way in: a client this staff member may not manage
        // should not be distinguishable from one that is not there.
        abort_unless($this->library->canAssignClient($actor, $target), 404);

        // An account request is not an account yet. Approving one is a
        // deliberate decision with its own screen and its own audit entry;
        // letting a conversion imply it would hide that decision inside an
        // unrelated tool.
        if ($target->account_requested) {
            throw ValidationException::withMessages([
                'user' => __('Approve or deny this account request before converting it.'),
            ]);
        }
    }

    /**
     * Whether promoting this account has to set a local password.
     *
     * A client this installation did not choose a password for has a
     * 64-character random one nobody has ever seen and nobody can recover
     * — see LdapProvisioner and SocialProvisioner. Something else holds
     * the credential that actually signs them in.
     *
     * For a directory account that something is unreachable to staff:
     * LoginRequest's LDAP fallback is guarded by isClient(), so promoting
     * one without a password produces an account whose two ways in are a
     * password nobody knows and a directory nobody asks — and the screen
     * would have reported success.
     *
     * For an account created by an identity provider the door does still
     * open, since staff may sign in through a connected provider. The rule
     * holds anyway, and on purpose: a staff account must carry a
     * credential *this application* can check, or an administrator is one
     * provider outage — or one disabled checkbox — away from being locked
     * out of their own installation.
     */
    public function requiresNewPassword(User $target): bool
    {
        return $target->auth_source !== AuthSource::Local;
    }

    /**
     * Converting yourself strips your own access halfway through the
     * request that does it.
     *
     * @throws ValidationException
     */
    private function guardSelf(User $actor, User $target): void
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot convert your own account.'),
            ]);
        }
    }

    /**
     * @return array{api_tokens_revoked: int, assigned_clients_cleared: int}
     */
    public function toClient(User $target): array
    {
        $oldRoleName = $target->role?->name;

        return DB::transaction(function () use ($target, $oldRoleName): array {
            // The API is staff-only (EnsureStaffToken), so a demoted
            // account holding a live bearer token would keep staff-level
            // API access that no screen in the app would ever show. This is
            // the one thing per-request type checks cannot catch.
            $tokensRevoked = $target->tokens()->count();
            $target->tokens()->delete();

            // A client does not manage clients.
            $assignmentsCleared = $target->assignedClients()->count();
            $target->assignedClients()->detach();

            // The role has to move with the type. PermissionChecker reads
            // only the role and never the type, and several routes are
            // gated on `can:` without the `staff` middleware — so a
            // type=client row still carrying an administrator role would
            // hold every permission.
            $target->forceFill([
                'type' => UserType::Client,
                'role_id' => $this->clientRoleId(),
                'account_requested' => false,
                // A staff dashboard preference means nothing to a client.
                'dashboard_columns' => null,
            ])->save();

            $this->activity->log(Action::AccountConvertedToClient, subject: $target, context: [
                'from_role' => $oldRoleName,
                'api_tokens_revoked' => $tokensRevoked,
                'assigned_clients_cleared' => $assignmentsCleared,
            ]);

            return [
                'api_tokens_revoked' => $tokensRevoked,
                'assigned_clients_cleared' => $assignmentsCleared,
            ];
        });
    }

    /**
     * @param  list<int>  $assignedClients
     * @return array{managed_by_cleared: int}
     */
    public function toStaff(User $target, int $roleId, array $assignedClients = [], ?string $password = null): array
    {
        $newPassword = is_string($password) && $password !== '' ? $password : null;

        // Enforced here and not only in the controller's rules, because
        // this is the invariant ("a staff account must have a credential
        // this application can actually check"), not a form's opinion.
        // Outside the transaction so a refusal writes nothing.
        if ($newPassword === null && $this->requiresNewPassword($target)) {
            throw ValidationException::withMessages([
                'password' => $target->auth_source === AuthSource::Ldap
                    ? __('Set a password for this account. It signs in through your directory today, and staff accounts never do.')
                    : __('Set a password for this account. It has never had one — it signs in through a connected provider, and a staff account needs a credential this application can check on its own.'),
            ]);
        }

        return DB::transaction(function () use ($target, $roleId, $assignedClients, $newPassword): array {
            // Normally a no-op — the token screens are staff-gated — but
            // the rule worth holding is "a conversion invalidates every
            // credential scoped to the old identity", which stays true the
            // day client tokens exist.
            $target->tokens()->delete();

            // They are no longer somebody's assigned client; leaving the
            // rows would place a staff account inside another staff
            // member's client scope.
            $managedByCleared = DB::table('staff_client_assignments')
                ->where('client_id', $target->id)
                ->delete();

            $target->forceFill([
                'type' => UserType::Staff,
                'role_id' => $roleId,
                'account_requested' => false,
                // They authenticate against this application now — that is
                // what the password above is for, and it is what being
                // staff means here. Leaving the stamp would claim their
                // credential lives somewhere nothing will ever ask.
                // `ldap_dn` is deliberately kept: it is the record of where
                // the account came from, and a demotion makes it live again.
                'auth_source' => AuthSource::Local,
            ]);

            if ($newPassword !== null) {
                $target->password = $newPassword;
            }

            $target->save();

            // Reused rather than re-derived: this is what clears the roster
            // for a role that is not client-scoped.
            $this->accounts->syncAssignedClients($target, $roleId, $assignedClients);

            $target->load('role');

            $this->activity->log(Action::AccountConvertedToStaff, subject: $target, context: [
                'role' => $target->role?->name,
                'managed_by_cleared' => $managedByCleared,
            ]);

            return ['managed_by_cleared' => $managedByCleared];
        });
    }

    private function clientRoleId(): int
    {
        $id = Role::query()->where('name', SystemRole::Client->value)->value('id');

        // EnsureSystemRoles guarantees this exists; a null here means the
        // installation is broken in a way this screen cannot repair.
        abort_if($id === null, 500, 'The Client system role is missing.');

        return (int) $id;
    }
}
