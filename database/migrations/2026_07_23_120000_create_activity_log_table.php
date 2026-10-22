<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Snapshot: survives actor deletion; null = the system itself.
            $table->string('actor_name')->nullable();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            // Snapshot of the subject's display name at the time.
            $table->string('subject_name')->nullable();
            $table->json('context')->nullable();
            // useCurrent() so the column has a default of its own. Without one,
            // a server with explicit_defaults_for_timestamp=OFF also gives the
            // first TIMESTAMP in a table ON UPDATE CURRENT_TIMESTAMP, and every
            // update of the row would rewrite it (#1816).
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
