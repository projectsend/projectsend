<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // Plain FK, not polymorphic: there's exactly one Notifiable
            // model in this app (User, staff+client via UserType) — see
            // ActivityLog's actor_id for the same reasoning.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // A NotificationTypeRegistry key (e.g. 'file_shared'), not an
            // enum-backed column — the registry is open, not closed, since
            // the private cloud-modules package registers its own types.
            $table->string('type')->index();
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            // useCurrent() for the servers that still invent a default for a
            // TIMESTAMP (explicit_defaults_for_timestamp=OFF: MySQL 5.7,
            // MariaDB before 10.10). There, the second one in a table gets
            // '0000-00-00', which strict mode refuses, and the install
            // stops here (#1816). The application always sets the value.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
