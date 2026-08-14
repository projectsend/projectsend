<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_requests', function (Blueprint $table) {
            // Denied requests persist (spam control): the client sees the
            // outcome and may re-request only after the configured
            // cooldown, reusing this same row.
            $table->string('status')->default('pending')->index()->after('user_id');
            $table->timestamp('denied_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('membership_requests', function (Blueprint $table) {
            $table->dropColumn(['status', 'denied_at']);
        });
    }
};
