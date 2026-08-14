<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $role_id
 * @property string $permission
 */
class RolePermission extends Model
{
    protected $table = 'role_permission';

    public $timestamps = false;

    protected $guarded = [];
}
