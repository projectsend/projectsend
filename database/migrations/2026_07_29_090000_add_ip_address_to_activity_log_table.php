<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Requester's IP at the time of the action (IPv4/IPv6); used
            // to show where a file's downloads came from.
            $table->string('ip_address', 45)->nullable()->after('actor_type');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
