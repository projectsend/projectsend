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
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
