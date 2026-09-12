<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A staff-sent link inviting a specific address to register, ahead
        // of the public form self-registration uses. The token is the
        // whole authorization and is queried directly, the same as a
        // file's share link — see Invitation's docblock.
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending');
            // 0 means no per-account quota and inherits the site default
            // at enforcement time, the same as ClientAccounts::create()'s
            // storageQuotaMb — see Invitation::issue().
            $table->unsignedInteger('storage_quota_mb')->default(0);
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
