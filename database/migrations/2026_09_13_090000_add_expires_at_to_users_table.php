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
            // The instant a client account stops working. Null means it
            // never does. See User::hasExpired() for how it is enforced,
            // and ExpireClientAccountsCommand for why `active` is also
            // switched off once it passes.
            $table->timestamp('expires_at')->nullable()->after('erase_after')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
