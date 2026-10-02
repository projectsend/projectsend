<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GET /api/v1/folders walks folders ordered by (updated_at, id), like every
 * list endpoint — see App\Modules\Api\Support\PollingQuery. Same reasoning
 * as the files index: without it, every poll is a filesort over the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'folders_updated_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropIndex('folders_updated_at_id_index');
        });
    }
};
