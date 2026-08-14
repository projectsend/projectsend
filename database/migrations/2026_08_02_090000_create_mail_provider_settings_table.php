<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table: SMTP transport config, separate from the
        // generic settings table because `password` needs a real
        // Eloquent-encrypted column (see App\Models\User::two_factor_secret
        // for the same pattern) — the generic settings.value JSON blob has
        // no per-key encryption.
        Schema::create('mail_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('custom');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('encryption')->default('tls');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_provider_settings');
    }
};
