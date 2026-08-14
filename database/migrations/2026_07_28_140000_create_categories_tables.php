<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flat, cross-cutting labels for files (folders provide hierarchy).
        // Categories are configuration, so they hard-delete — no soft deletes.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('category_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_file');
        Schema::dropIfExists('categories');
    }
};

