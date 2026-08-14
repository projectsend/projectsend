<?php

declare(strict_types=1);

use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_administrator')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->unique(['role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('type')->constrained()->restrictOnDelete();
        });

        // Reference data ships with the schema: the same idempotent
        // ensure step also runs on every container boot, so the built-in
        // roles exist even if this seed is somehow bypassed.
        (new EnsureSystemRoles)->ensure();

        // Pre-roles installs: staff accounts had full access, so they map
        // to System Administrator; clients map to the Client role.
        $adminId = DB::table('roles')->where('name', SystemRole::SystemAdministrator->value)->value('id');
        $clientId = DB::table('roles')->where('name', SystemRole::Client->value)->value('id');

        DB::table('users')->where('type', 'staff')->update(['role_id' => $adminId]);
        DB::table('users')->where('type', 'client')->update(['role_id' => $clientId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
    }
};
