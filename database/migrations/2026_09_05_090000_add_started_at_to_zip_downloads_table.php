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
            // When a worker actually picked this build up, as opposed to
            // when it was asked for. The gap between the two is the only
            // thing that tells "nobody is consuming the zips queue" apart
            // from "the archive is big" — a row that has waited a while
            // and never started means no worker is listening, which is
            // exactly what happens on a manual install whose worker
            // command predates that queue. See StalledZipBuilds.
            $table->timestamp('started_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('zip_downloads', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
