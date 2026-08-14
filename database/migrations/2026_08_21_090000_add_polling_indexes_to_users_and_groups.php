<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same composite index the files table already carries, for the two
 * list endpoints that joined the API's polling contract in phase 4.
 *
 * Without it, every `?updated_since=` poll is a filesort over the whole
 * table — a cost that grows with how many clients or groups exist, rather
 * than with how many changed since the caller last asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'users_updated_at_id_index');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'groups_updated_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_updated_at_id_index');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex('groups_updated_at_id_index');
        });
    }
};
