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
            // useCurrent() so the column has a default of its own. Without one,
            // a server with explicit_defaults_for_timestamp=OFF also gives the
            // first TIMESTAMP in a table ON UPDATE CURRENT_TIMESTAMP, and every
            // update of the row would rewrite it (#1816).
            $table->timestamp('ran_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
