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
            // Null means "use the system default" (3) — only set once a
            // user explicitly picks a column count from the dashboard's
            // Widgets modal. Same nullable-until-chosen shape as `locale`.
            $table->unsignedTinyInteger('dashboard_columns')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_columns');
        });
    }
};
