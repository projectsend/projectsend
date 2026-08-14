<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zip_downloads', function (Blueprint $table) {
            // When the archive was first handed over. Fetching a prepared
            // zip used to write one FileDownloaded entry per contained
            // file *every time* it was fetched, so re-downloading one
            // archive of fifty files counted as fifty more downloads —
            // inflating every count and, now that files can be capped,
            // spending allowances that were never really used. A zip is
            // one delivery; this records that it happened.
            $table->timestamp('delivered_at')->nullable()->after('total_size');

            // Files left out because their download limit was already
            // spent. Recorded so the person who asked for the archive is
            // told what is missing from it rather than quietly receiving
            // less than they selected.
            $table->json('skipped_files')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('zip_downloads', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'skipped_files']);
        });
    }
};
