<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether a sign-in through this provider skips ProjectSend's own
        // two-factor step, for the provider that asks for a second factor
        // itself. Off by default: every existing installation keeps
        // challenging provider sign-ins until an administrator says that
        // this particular provider has already done so.
        Schema::table('social_login_providers', function (Blueprint $table) {
            $table->boolean('skip_two_factor')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('social_login_providers', function (Blueprint $table) {
            $table->dropColumn('skip_two_factor');
        });
    }
};
