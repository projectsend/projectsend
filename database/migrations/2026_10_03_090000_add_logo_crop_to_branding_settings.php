<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cropping the logo keeps the uploaded file, so the crop can be redone or
 * undone. Both columns are null for a logo that was never cropped, which is
 * every logo that exists when this runs: `logo_path` keeps meaning "the
 * image to show", and nothing about an existing installation changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            // The uploaded file, while `logo_path` points at a crop of it.
            $table->string('logo_original_path')->nullable()->after('logo_path');
            // The box that crop was cut with, in the original's pixels:
            // {x, y, width, height}. So the cropper reopens where it was.
            $table->json('logo_crop')->nullable()->after('logo_original_path');
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_original_path', 'logo_crop']);
        });
    }
};
