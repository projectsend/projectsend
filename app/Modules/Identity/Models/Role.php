<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_system
 * @property bool $is_administrator
 * @property bool $client_scoped
 * @property-read int $users_count
 * @property-read int $permissions_count
 */
class Role extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        // The built-in roles must always exist: they can never be
        // deleted, renamed, or demoted from system status.
        static::deleting(function (Role $role): void {
            if ($role->is_system) {
                throw new RuntimeException('System roles cannot be deleted.');
            }
        });

        static::updating(function (Role $role): void {
            if ((bool) $role->getOriginal('is_system') && ($role->isDirty('name') || $role->isDirty('is_system'))) {
                throw new RuntimeException('System roles cannot be renamed or demoted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_administrator' => 'boolean',
            'client_scoped' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
