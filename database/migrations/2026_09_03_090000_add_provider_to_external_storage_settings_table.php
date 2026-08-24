<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_storage_settings', function (Blueprint $table) {
            // Every row that exists predates the choice, and every one of
            // them is S3 — so the default is what keeps this migration
            // invisible to anyone already using external storage.
            $table->string('provider')->default('s3')->after('active');

            // A service account key is a ~2 KB JSON document, not a
            // password, so it gets its own encrypted column rather than
            // sharing `secret` with S3. The two are validated
            // differently, labelled differently and shown differently,
            // and one column meaning two things is how that gets
            // confusing later.
            $table->text('key_file')->nullable()->after('secret');
        });
    }

    public function down(): void
    {
        Schema::table('external_storage_settings', function (Blueprint $table) {
            $table->dropColumn(['provider', 'key_file']);
        });
    }
};
