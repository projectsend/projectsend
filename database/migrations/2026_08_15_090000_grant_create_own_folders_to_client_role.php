<?php

declare(strict_types=1);

use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clients can now create their own folders (App\Modules\Files\FolderPolicy,
 * MyFoldersController), gated by create_own_folders — the same permission
 * staff already use for folder creation. Upload travels with it (creating a
 * folder you can never put anything in makes no sense on its own — see
 * MyFoldersController::store()). EnsureSystemRoles never touches an
 * already-existing role's permissions (see its docblock), so updating
 * SystemRole::Client's defaultPermissions() only affects fresh installs;
 * this grants both to the shared Client role on installs that already
 * migrated, matching how "on by default for all clients" was decided.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['create_own_folders', 'upload'];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', SystemRole::Client->value)->value('id');

        if ($roleId === null) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            $exists = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission', $permission)
                ->exists();

            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission' => $permission,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', SystemRole::Client->value)->value('id');

        if ($roleId !== null) {
            DB::table('role_permission')->where('role_id', $roleId)->whereIn('permission', self::PERMISSIONS)->delete();
        }
    }
};
