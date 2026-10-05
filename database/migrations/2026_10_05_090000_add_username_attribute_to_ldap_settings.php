<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The directory attribute that holds a username (uid, cn,
        // sAMAccountName...), so people can sign in with it as well as with
        // their address. Empty by default: sign-in stays email-only on every
        // existing installation until an administrator names one.
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->string('username_attribute')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->dropColumn('username_attribute');
        });
    }
};
