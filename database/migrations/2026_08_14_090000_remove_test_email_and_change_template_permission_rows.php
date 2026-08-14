<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Neither test_email nor change_template was ever enforced anywhere: the
 * "send test email" feature is gated by edit_settings, not a dedicated
 * permission, and the client-portal theme is now one global setting (also
 * edit_settings), not a per-permission toggle. Both enum cases were removed
 * (App\Modules\Identity\Permissions\Permission). PermissionChecker already
 * ignores pivot rows for keys the enum no longer has, so this is pure
 * hygiene, not a correctness fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permission')->whereIn('permission', ['test_email', 'change_template'])->delete();
    }

    public function down(): void
    {
        // Deliberately not reversible: the permissions they referenced no
        // longer exist in the Permission enum.
    }
};
