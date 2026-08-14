<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Snapshot of the actor's type (staff/client) at the time of
            // the action; null = the system itself. Snapshotted so
            // entries stay filterable after the account is deleted.
            $table->string('actor_type')->nullable()->after('actor_name')->index();
        });

        DB::table('activity_log')
            ->whereNotNull('actor_id')
            ->update(['actor_type' => DB::raw('(select type from users where users.id = activity_log.actor_id)')]);
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('actor_type');
        });
    }
};
