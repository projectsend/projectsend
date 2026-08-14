<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * unblock_ip was never enforced anywhere: v1's persisted per-IP lockout
 * with an admin unblock action was never ported, only Laravel's standard
 * self-expiring login-attempt rate limiting (App\Http\Requests\Auth\
 * LoginRequest), which needs no admin override. The enum case was removed
 * (App\Modules\Identity\Permissions\Permission). PermissionChecker already
 * ignores pivot rows for keys the enum no longer has, so this is pure
 * hygiene, not a correctness fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permission')->where('permission', 'unblock_ip')->delete();
    }

    public function down(): void
    {
        // Deliberately not reversible: the permission no longer exists in the Permission enum.
    }
};
