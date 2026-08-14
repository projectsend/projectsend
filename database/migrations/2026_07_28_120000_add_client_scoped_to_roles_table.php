<?php

declare(strict_types=1);

use App\Modules\Identity\Permissions\EnsureSystemRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'client_scoped')) {
            Schema::table('roles', function (Blueprint $table) {
                // A role whose members only see files & folders belonging to
                // the clients assigned to them (plus their own uploads).
                $table->boolean('client_scoped')->default(false)->after('is_administrator');
            });
        }

        // Now that the column exists, (re-)ensure the built-in roles so the
        // Client Manager role gets client_scoped = true — on fresh installs
        // the roles were first seeded before this column existed.
        (new EnsureSystemRoles)->ensure();
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('client_scoped');
        });
    }
};
