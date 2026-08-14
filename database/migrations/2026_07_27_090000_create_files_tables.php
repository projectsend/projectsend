<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('original_name');
            // Disk-relative path on the "files" disk; listings never
            // touch storage (size/mime/checksum persisted at upload).
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('file_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            // A client (User) or a Group.
            $table->morphs('assignable');
            $table->timestamps();
            $table->unique(['file_id', 'assignable_type', 'assignable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_assignments');
        Schema::dropIfExists('files');
    }
};
