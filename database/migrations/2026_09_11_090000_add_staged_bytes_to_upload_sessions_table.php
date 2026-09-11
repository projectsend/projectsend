<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            // Bytes this session currently holds on the temporary volume,
            // including any part still being received. The filesystem is
            // still the part ledger; this is the running total, kept here
            // because a limit has to be claimed before the bytes arrive and
            // a directory listing cannot be read and written atomically.
            $table->unsignedBigInteger('staged_bytes')->default(0)->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn('staged_bytes');
        });
    }
};
