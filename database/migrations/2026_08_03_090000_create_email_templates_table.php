<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per CUSTOMIZED slot only — no row means "use the code
        // default" (see EmailTemplateSlot). Subject and body are saved
        // together as a unit; there's no independent toggle like v1 had.
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slot')->unique();
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
