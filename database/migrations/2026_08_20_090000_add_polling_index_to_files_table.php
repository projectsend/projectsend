<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The API's polling contract walks files ordered by (updated_at, id) —
 * see App\Modules\Api\Support\PollingQuery. Without a composite index in
 * that exact order, every poll from every integration is a filesort over
 * the whole table, and the cost grows with the library rather than with
 * the number of changes the caller is actually collecting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'files_updated_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_updated_at_id_index');
        });
    }
};
