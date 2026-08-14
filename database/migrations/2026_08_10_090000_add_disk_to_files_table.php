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
            // The Laravel disk name this file's bytes live on ('files' =
            // local, or 'files_external' once the community-only external
            // storage module routes an upload there). Stamped once at
            // upload time and never changed after — there is no migration
            // tool between backends, so this is the only way a file
            // uploaded before external storage was enabled keeps resolving
            // to local disk after it's turned on.
            $table->string('disk')->default('files')->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('disk');
        });
    }
};
