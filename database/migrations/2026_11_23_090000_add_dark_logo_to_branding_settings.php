<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second logo, shown instead of the first to anyone using the dark theme
 * (#1845). The same three columns the main logo has: the image shown, the
 * upload it was cropped from, and the crop box. All null on every existing
 * installation, which keeps showing its one logo in both themes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->string('logo_dark_path')->nullable()->after('logo_crop');
            $table->string('logo_dark_original_path')->nullable()->after('logo_dark_path');
            $table->json('logo_dark_crop')->nullable()->after('logo_dark_original_path');
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_dark_path', 'logo_dark_original_path', 'logo_dark_crop']);
        });
    }
};
