<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where somebody lands after signing in: a StartPage value, or null
        // for the dashboard. The role holds the default for everyone in it;
        // the user column is that person's own choice, and wins. Plain
        // strings rather than an enum column, and not cast to the enum
        // either — a value a later version stops offering must fall back to
        // the dashboard, not fail to load the account. See StartPages.
        Schema::table('roles', function (Blueprint $table) {
            $table->string('start_page', 32)->nullable()->after('client_scoped');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('start_page', 32)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('start_page');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('start_page');
        });
    }
};
