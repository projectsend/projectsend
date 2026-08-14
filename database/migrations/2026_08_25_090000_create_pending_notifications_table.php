<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The debounce buffer behind digest emails, generalised beyond file
 * shares.
 *
 * It replaces `pending_file_shares` rather than extending it: a row here
 * lives at most a couple of minutes (see NotificationDigester's debounce
 * window) and is deleted the moment its digest sends, so dropping the old
 * table loses nothing but a share email that was seconds from being sent
 * anyway. A rename plus a backfill would have been ceremony for rows that
 * do not survive the deploy.
 *
 * `type` is a notification type key from NotificationTypeRegistry, which
 * is what lets one job serve every kind: it looks the key up and asks the
 * definition which mail class to use for one item and which for several.
 * `context` carries whatever that mail class needs — the file name for a
 * comment, `is_folder` for a share.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pending_file_shares');

        Schema::create('pending_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('subject_name');
            $table->json('context')->nullable();
            $table->timestamps();

            // The job's only query: everything pending for one recipient.
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_notifications');

        Schema::create('pending_file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('item_name');
            $table->boolean('is_folder')->default(false);
            $table->timestamps();
        });
    }
};
