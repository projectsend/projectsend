<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Watermarking rides on the same single row as the sidebar logo:
        // it is part of the same Cloud-exclusive Branding capability, and
        // an install either brands itself or does not. Its image is stored
        // separately from `logo_path` on purpose — a logo drawn on a light
        // sidebar and a mark stamped over arbitrary photographs are
        // different artwork, and installs that want both want two files.
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->boolean('watermark_enabled')->default(false);
            $table->string('watermark_path')->nullable();
            $table->string('watermark_position', 20)->default('bottom-right');

            // Both percentages, not pixels: a thumbnail is bounded to 300px
            // on its longest side but its actual size depends on the
            // original's aspect ratio, so an absolute width would land
            // differently on a portrait than on a landscape.
            $table->unsignedTinyInteger('watermark_size')->default(30);
            $table->unsignedTinyInteger('watermark_opacity')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->dropColumn([
                'watermark_enabled',
                'watermark_path',
                'watermark_position',
                'watermark_size',
                'watermark_opacity',
            ]);
        });
    }
};
