<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The connection a code started, so a code presented twice ends
        // that connection. The code used to point at the first access
        // token, which the first refresh replaces, so after one refresh a
        // reused code found nothing to end.
        Schema::table('mcp_authorization_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('mcp_connection_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mcp_authorization_codes', function (Blueprint $table) {
            $table->dropColumn('mcp_connection_id');
        });
    }
};
