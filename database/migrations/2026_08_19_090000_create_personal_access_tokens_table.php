<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum's own table, kept at the package's default name rather than
 * renamed: every helper Sanctum ships (findToken, the HasApiTokens
 * relation, the prune-expired command) resolves it through the package's
 * model, so a cosmetic rename buys nothing and costs a permanent
 * divergence from upstream.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            // Indexed because expiry is per token here (config/sanctum.php's
            // global 'expiration' is null on purpose), so both the auth path
            // and the pruning command filter on this column.
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
