<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set on self-deletion: the trashed account is permanently
            // erased (GDPR Art. 17) once this moment passes. Admin
            // deletions leave it null — those are business records the
            // controller may keep.
            $table->timestamp('erase_after')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('erase_after');
        });
    }
};
