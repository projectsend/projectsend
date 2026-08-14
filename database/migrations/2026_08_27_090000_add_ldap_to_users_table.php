<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Provenance, not authorisation — see App\Modules\Identity\AuthSource.
            // Existing rows default to 'local', which is what they are, so no
            // backfill is needed.
            $table->string('auth_source')->default('local')->after('type');

            // The directory entry this account was matched to, kept so an
            // administrator can see which object in their tree an account
            // corresponds to. Never used to bind — the DN is always the one
            // a fresh search returned.
            $table->string('ldap_dn')->nullable()->after('auth_source');
            $table->timestamp('ldap_synced_at')->nullable()->after('ldap_dn');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_source', 'ldap_dn', 'ldap_synced_at']);
        });
    }
};
