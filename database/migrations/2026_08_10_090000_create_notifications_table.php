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
            $table->timestamp('created_at');

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
