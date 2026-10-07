<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether the directory sync may undo an administrator's deletion of
        // a client account that is still waiting out its erasure grace
        // period. Off by default: a deleted account stays deleted until an
        // administrator asks otherwise.
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->boolean('sync_restores_deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->dropColumn('sync_restores_deleted');
        });
    }
};
