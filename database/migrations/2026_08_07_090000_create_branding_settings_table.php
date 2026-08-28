<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single-row table: one logo per install. Under the planned
        // DB-per-tenant model each cloud tenant has its own database, so
        // "one row" already means "one per tenant" — no tenant/owner
        // column needed.
        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
