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
            // What the job actually put in the archive, recorded as it
            // adds each file. `file_ids` and `folder_ids` are the request,
            // not the result: a folder's contents were resolved again at
            // download time, against a scope that may have moved since the
            // archive was written. That made the download log describe a
            // selection rather than a delivery — a file added to the folder
            // afterwards was counted without ever being in the zip, and one
            // moved out of it was handed over without being counted.
            $table->json('contained_file_ids')->nullable()->after('folder_ids');
        });
    }

    public function down(): void
    {
        Schema::table('zip_downloads', function (Blueprint $table) {
            $table->dropColumn('contained_file_ids');
        });
    }
};
