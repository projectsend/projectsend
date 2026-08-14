<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carries "this upload is a new version of file X" from the moment the
 * upload starts to the moment the File row exists.
 *
 * Declared up front rather than patched on afterwards so the choice can be
 * validated before the bytes are sent — telling someone their pick was not
 * allowed after a long upload is a poor trade for one integer.
 *
 * nullOnDelete: if the original is deleted mid-upload the upload still
 * completes, just unlinked. Losing the file over a lost link would be the
 * wrong way round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->foreignId('previous_file_id')->nullable()->after('folder_id')
                ->constrained('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_file_id');
        });
    }
};
