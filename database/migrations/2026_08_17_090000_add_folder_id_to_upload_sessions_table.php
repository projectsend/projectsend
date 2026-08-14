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
            // Null = loose upload at the client's root, matching every
            // upload before this column existed. Set at session-create
            // time (ChunkedUploadsController::store(), after
            // Folder::uploadableBy() authorizes it) and threaded through
            // to the resulting File at complete().
            $table->foreignId('folder_id')->nullable()->after('user_id')->constrained('folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
    }
};
