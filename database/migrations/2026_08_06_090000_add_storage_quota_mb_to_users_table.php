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
            // A per-client cumulative upload quota in MB (0 = unlimited),
            // enforced only against that client's own portal uploads. Only
            // meaningful for clients — staff rows just carry the default.
            $table->unsignedInteger('storage_quota_mb')->default(0)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('storage_quota_mb');
        });
    }
};
