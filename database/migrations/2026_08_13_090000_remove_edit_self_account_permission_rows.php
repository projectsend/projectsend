<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The edit_self_account permission was never actually enforced anywhere —
 * profile editing is gated by plain auth, not a role permission — so the
 * enum case was removed (App\Modules\Identity\Permissions\Permission).
 * PermissionChecker already ignores pivot rows for keys the enum no longer
 * has, so this is pure hygiene, not a correctness fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permission')->where('permission', 'edit_self_account')->delete();
    }

    public function down(): void
    {
        // Deliberately not reversible: the permission it referenced no
        // longer exists in the Permission enum.
    }
};
