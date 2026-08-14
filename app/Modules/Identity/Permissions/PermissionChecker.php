<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;

class PermissionChecker
{
    /**
     * Granted permission keys per role id, loaded once per request.
     *
     * @var array<int, array<string, bool>>
     */
    private array $granted = [];

    public function allows(User $user, Permission $permission): bool
    {
        $role = $user->role;

        if ($role === null) {
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

        if ($role->is_administrator) {
            return array_map(fn (Permission $permission): string => $permission->value, Permission::cases());
        }

        return array_keys($this->grantedFor($role));
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
