<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Laravel\Sanctum\Contracts\HasAbilities;

class PermissionChecker
{
    /**
     * Granted permission keys per role id, loaded once per request.
     *
     * @var array<int, array<string, bool>>
     */
    private array $granted = [];

    /**
     * The one user whose permissions are capped, the API token they are
     * calling with, and the keys a token can be granted at all. See
     * LimitPermissionsToToken.
     *
     * @var array{int, HasAbilities, list<string>}|null
     */
    private ?array $ceiling = null;

    /**
     * Cap $user at $token's abilities for the rest of this request. Applies
     * to an administrator too: a token minted for one thing is that one
     * thing, whoever minted it.
     *
     * Only the keys in $grantable are capped: the ones a token can be
     * given, which TokenAbilities::inUse() lists. That includes the file
     * settings such as upload_public (GHSA-q84h-845v-9p26). Keys outside it
     * belong to screens the API does not reach, and follow the role.
     *
     * @param  list<string>  $grantable
     */
    public function limitTo(User $user, HasAbilities $token, array $grantable): void
    {
        $this->ceiling = [(int) $user->id, $token, $grantable];
    }

    /**
     * Run $callback with the token cap lifted, so permission checks inside
     * it answer from the role alone.
     *
     * Only for a check a token ability already implies. There is one:
     * moderating comments means reading them, which FileCommentPolicy
     * guards with the file-reading keys, and a token granted
     * `moderate_comments` was granted that much. Every other check stays
     * capped.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function asRole(callable $callback): mixed
    {
        $ceiling = $this->ceiling;
        $this->ceiling = null;

        try {
            return $callback();
        } finally {
            $this->ceiling = $ceiling;
        }
    }

    public function allows(User $user, Permission $permission): bool
    {
        if (! $this->withinCeiling($user, $permission->value)) {
            return false;
        }

        $role = $user->role;

        if ($role === null) {
            return false;
        }

        if ($permission->spansAllClients() && $role->client_scoped) {
            return false;
        }

        if ($role->is_administrator) {
            return true;
        }

        return isset($this->grantedFor($role)[$permission->value]);
    }

    /**
     * Every permission key granted to the user, for UI hiding.
     *
     * @return list<string>
     */
    public function grantedKeys(User $user): array
    {
        $role = $user->role;

        if ($role === null) {
            return [];
        }

        $keys = $role->is_administrator
            ? array_map(fn (Permission $permission): string => $permission->value, Permission::cases())
            : array_keys($this->grantedFor($role));

        return array_values(array_filter(
            $keys,
            fn (string $key): bool => $this->withinCeiling($user, $key)
                && ! ($role->client_scoped && Permission::tryFrom($key)?->spansAllClients() === true),
        ));
    }

    private function withinCeiling(User $user, string $key): bool
    {
        if ($this->ceiling === null || $this->ceiling[0] !== (int) $user->id
            || ! in_array($key, $this->ceiling[2], true)) {
            return true;
        }

        return $this->ceiling[1]->can($key);
    }

    /**
     * @return array<string, bool>
     */
    private function grantedFor(Role $role): array
    {
        return $this->granted[$role->id] ??= RolePermission::query()
            ->where('role_id', $role->id)
            ->pluck('permission')
            // Ignore keys that no longer exist in the vocabulary.
            ->filter(fn (string $key): bool => Permission::tryFrom($key) !== null)
            ->mapWithKeys(fn (string $key): array => [$key => true])
            ->all();
    }
}
