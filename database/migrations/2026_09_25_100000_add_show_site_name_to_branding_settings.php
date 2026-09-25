<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether the sign-in and download pages print the site name under
        // the logo. A choice rather than always-on, because plenty of logos
        // already say the name and would then say it twice (#1798). Off by
        // default, so every existing installation looks as it did.
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->boolean('show_site_name')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->dropColumn('show_site_name');
        });
    }
};
