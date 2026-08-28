<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `last_error` was answering two questions at once — the table's
        // own comment says so: "what the settings page's warning and the
        // admin notification read". The warning wants "is this connection
        // broken", and any writer may answer it; the notification wants
        // "have the admins been told", which only the notifier can.
        //
        // They came apart because the send path writes last_error too
        // (OAuthCodeFlowBroker::refresh, reached from freshAccessToken).
        // On an installation that actually sends mail, that write lands
        // first, and the daily command then read it as "already notified"
        // and stayed silent forever.
        //
        // Cleared wherever last_error is cleared, and only there:
        // a successful refresh, a disconnect, and a changed client id.
        Schema::table('mail_oauth_connections', function (Blueprint $table) {
            $table->timestamp('broken_notified_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('mail_oauth_connections', function (Blueprint $table) {
            $table->dropColumn('broken_notified_at');
        });
    }
};
