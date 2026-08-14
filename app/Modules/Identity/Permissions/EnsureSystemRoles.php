<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantees the built-in roles exist with correct flags. Runs in the
 * roles migration and on every container boot, and is idempotent: a
 * missing role is recreated with its v1-default permission set, but an
 * existing role's permissions are never touched — administrators may
 * customize them, and defaults must not undo that.
 *
 * Legacy roles (SystemRole::isLegacy) are never seeded here — only the v1
 * migration tool creates them, for imported installations.
 */
class EnsureSystemRoles
{
    public function ensure(): void
    {
        // Tolerated so this can run during the roles migration (before the
        // client_scoped column is added) and again once it exists.
        $hasClientScoped = Schema::hasColumn('roles', 'client_scoped');

        foreach (SystemRole::cases() as $systemRole) {
            if ($systemRole->isLegacy()) {
                continue;
            }

            $role = Role::query()->where('name', $systemRole->value)->first();

            if ($role === null) {
                $attributes = [
                    'name' => $systemRole->value,
                    'is_system' => true,
                    'is_administrator' => $systemRole->isAdministrator(),
                ];

                if ($hasClientScoped) {
                    $attributes['client_scoped'] = $systemRole->isClientScoped();
                }

                $role = Role::query()->create($attributes);

                // The administrator role holds every permission by
                // construction and never needs pivot rows.
                if (! $systemRole->isAdministrator()) {
                    RolePermission::query()->insert(array_map(
                        fn (Permission $permission): array => [
                            'role_id' => $role->id,
                            'permission' => $permission->value,
                        ],
                        $systemRole->defaultPermissions(),
                    ));
                }

                continue;
            }

            // Repair tampered flags; permissions stay as customized.
            $scopeDrifted = $hasClientScoped && (bool) $role->client_scoped !== $systemRole->isClientScoped();

            if (! $role->is_system || $role->is_administrator !== $systemRole->isAdministrator() || $scopeDrifted) {
                $attributes = [
                    'is_system' => true,
                    'is_administrator' => $systemRole->isAdministrator(),
                ];

                if ($hasClientScoped) {
                    $attributes['client_scoped'] = $systemRole->isClientScoped();
                }

                $role->forceFill($attributes)->save();
            }
        }
    }

    /**
     * Find or create a single role by its SystemRole definition, including
     * its default permissions. Unlike ensure() this materializes legacy
     * roles too — used by the test factory (and, later, the migration
     * tool). Assumes the schema is fully migrated (client_scoped exists).
     */
    public function materialize(SystemRole $systemRole): Role
    {
        $role = Role::query()->where('name', $systemRole->value)->first();

        if ($role !== null) {
            return $role;
        }

        $role = Role::query()->create([
            'name' => $systemRole->value,
            'is_system' => true,
            'is_administrator' => $systemRole->isAdministrator(),
            'client_scoped' => $systemRole->isClientScoped(),
        ]);

        if (! $systemRole->isAdministrator()) {
            RolePermission::query()->insert(array_map(
                fn (Permission $permission): array => [
                    'role_id' => $role->id,
                    'permission' => $permission->value,
                ],
                $systemRole->defaultPermissions(),
            ));
        }

        return $role;
    }
}
