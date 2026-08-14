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
            // v1 parity: self-registered clients awaiting approval carry
            // account_requested until an admin approves (activates) or
            // denies (deletes) them.
            $table->boolean('account_requested')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_requested');
        });
    }
};
