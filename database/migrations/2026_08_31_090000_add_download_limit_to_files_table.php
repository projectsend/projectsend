<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Null means "no limit" — same convention as expires_at above
            // it. ProjectSend v1 spent two columns on this, an `enabled`
            // flag beside a count, which let a file carry a limit of 5
            // that was switched off; one nullable column cannot disagree
            // with itself.
            $table->unsignedInteger('download_limit')->nullable()->after('expires_at');

            // Meaningless while download_limit is null, which is why it
            // has a default rather than being nullable too: a file that
            // has never had a limit still answers "which kind would it
            // be" without a null check at every read.
            $table->string('download_limit_scope', 16)->default('total')->after('download_limit');

            // Not for finding limited files — for answering "does this
            // installation use limits at all?" in one indexed query, so
            // listings on the overwhelming majority of installs that
            // never touch the feature can skip counting downloads per row
            // entirely. See DownloadAllowance::isUsedAnywhere().
            $table->index('download_limit');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['download_limit']);
            $table->dropColumn(['download_limit', 'download_limit_scope']);
        });
    }
};
