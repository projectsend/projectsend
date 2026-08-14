<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether a directory account skips the approval queue.
        //
        // Deliberately its own answer rather than inheriting
        // Setting::ClientsAutoApprove, which is a decision about anonymous
        // self-registration — a very different question. Somebody who binds
        // against your directory has already been authenticated by it, so
        // "everyone in the directory is a client here" is a reasonable
        // policy even on an installation that makes strangers from the web
        // wait for approval.
        //
        // Defaults to false: the safe direction, and the one an
        // administrator can relax deliberately rather than discover.
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->boolean('auto_approve')->default(false)->after('auto_provision');
        });
    }

    public function down(): void
    {
        Schema::table('ldap_settings', function (Blueprint $table) {
            $table->dropColumn('auto_approve');
        });
    }
};
