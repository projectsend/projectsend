<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table, same reasoning as mail_provider_settings:
        // `secret` needs a real Eloquent-encrypted column, which the
        // generic settings table can't offer per-key.
        Schema::create('external_storage_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false);
            $table->string('key')->nullable();
            $table->text('secret')->nullable();
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->string('endpoint')->nullable();
            $table->boolean('use_path_style')->default(false);
            $table->string('root')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_storage_settings');
    }
};
