<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // How many times the invited person has asked for a fresh link
            // without anybody deciding to give them one. Carried forward
            // each time, so it counts the chain rather than the row — see
            // Invitation::issue() and InvitationRedemptionController's
            // SELF_RESEND_LIMIT.
            $table->unsignedInteger('resends')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('resends');
        });
    }
};
