<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The directory sync's two choices. Both off by default: nothing is
        // created or switched off on an existing installation until an
        // administrator asks for it.
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->boolean('sync_daily')->default(false);
            $table->boolean('sync_deactivates_missing')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->dropColumn(['sync_daily', 'sync_deactivates_missing']);
        });
    }
};
