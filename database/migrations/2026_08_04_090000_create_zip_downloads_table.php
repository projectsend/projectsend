<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disposable, regenerable artifacts — purged on a schedule (see
        // PurgeZipDownloadsCommand), not something a user manages directly.
        Schema::create('zip_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('path')->nullable();
            // The resolved (already access-filtered) selection, stored so
            // the job can build from it and the download action can log a
            // FileDownloaded entry per contained file — otherwise a file's
            // download history/count would silently miss zip downloads.
            $table->json('file_ids')->nullable();
            $table->json('folder_ids')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_size')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_downloads');
    }
};
