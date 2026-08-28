<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The last piece of white-labelling: replacing the logo but
        // leaving "Powered by ProjectSend" under every client's file
        // list only half-does the job this capability sells. Rides on
        // the same single row as the logo and the watermark for the
        // same reason they ride together — an install either brands
        // itself or does not.
        //
        // Phrased as "hide" rather than "show" so the default is false
        // and every existing row keeps naming ProjectSend without a
        // backfill.
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->boolean('hide_attribution')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->dropColumn('hide_attribution');
        });
    }
};
