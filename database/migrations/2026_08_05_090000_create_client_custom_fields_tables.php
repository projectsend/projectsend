<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Field definitions are configuration, so they hard-delete — no
        // soft deletes, matching the categories table.
        Schema::create('client_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('client_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_custom_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['client_custom_field_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_custom_field_values');
        Schema::dropIfExists('client_custom_fields');
    }
};
