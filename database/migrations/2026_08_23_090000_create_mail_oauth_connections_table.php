<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per OAuth mail provider (unique on `provider`), separate
        // from mail_provider_settings for the same reason that table is
        // separate from the generic settings blob: client_secret and both
        // tokens need real Eloquent-encrypted columns. Keeping the SMTP row
        // untouched also means switching to an OAuth provider and back
        // loses nothing.
        Schema::create('mail_oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('account_email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            // The last refresh/send failure that means "reconnect me", kept
            // until a successful refresh or reconnect clears it — what the
            // settings page's warning and the admin notification read.
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_oauth_connections');
    }
};
