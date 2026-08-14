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
            // An IANA identifier ("America/Argentina/Buenos_Aires"), null
            // until this account has a zone of its own — same
            // nullable-until-chosen shape as `locale` above it, and read
            // through TimezoneRegistry, which falls back to the
            // installation's setting rather than trusting the column.
            //
            // Longest identifier currently shipped by tzdata is 32
            // characters, so the default string length is ample; it is a
            // display preference, not a key, and is never joined on.
            $table->string('timezone')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
