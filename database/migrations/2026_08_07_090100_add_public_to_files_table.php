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
            // Independent of the file's group(s): a file is only ever
            // publicly reachable when this is true, regardless of
            // whether a group it's assigned to is public. See
            // PublicGroupsController.
            $table->boolean('public')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('public');
        });
    }
};
