<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            // The artisan command signature (e.g. 'projectsend:purge-expired-files')
            // — one row per known scheduled command, upserted on every run.
            $table->string('command')->unique();
            $table->string('status');
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('ran_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
