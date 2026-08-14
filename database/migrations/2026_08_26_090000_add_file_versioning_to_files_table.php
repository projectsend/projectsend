<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File versioning: a file may be marked as a revision of an earlier upload.
 *
 * Two columns, doing two different jobs:
 *
 * `previous_file_id` is the link the user actually creates — "this file
 * replaces that one". It is UNIQUE, which is the whole integrity model: a
 * file can be revised by at most one other file, so with a single pointer
 * per row both in-degree and out-degree are at most one and every component
 * of the graph is a path or a cycle. Cycles are the only thing left for code
 * to exclude (FileVersions::link). Every engine we target allows unlimited
 * NULLs in a unique index, which is exactly the semantic wanted.
 *
 * `version_root_id` is derived, not user-supplied: it names the oldest file
 * in the chain, and it exists so the visibility queries can resolve a
 * revision's recipients without walking the chain in SQL. A revision owns no
 * file_assignments rows of its own — its audience IS the root's — so every
 * query that reads assignments matches on COALESCE(version_root_id, id).
 * See App\Modules\Files\Access\SharingIdentity.
 *
 * Invariant, asserted by `versioning:check`:
 * previous_file_id IS NULL  <=>  version_root_id IS NULL.
 *
 * nullOnDelete() rather than cascade on both: cascading would mean
 * force-deleting an original destroys the revision's *row* — its comments,
 * its download history, its bytes — which is the opposite of the point.
 * Files are soft-deleted in practice (File::booted), so these FKs are a
 * safety net; FileVersions::detachOnDelete handles the live path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->foreignId('previous_file_id')->nullable()->after('folder_id')
                ->constrained('files')->nullOnDelete();
            $table->unique('previous_file_id', 'files_previous_file_id_unique');

            $table->foreignId('version_root_id')->nullable()->after('previous_file_id')
                ->constrained('files')->nullOnDelete();
            $table->index('version_root_id', 'files_version_root_id_index');
        });
    }

    /**
     * Both indexes are left to go with their columns rather than dropped
     * first: MySQL refuses to drop an index a foreign key still needs
     * ("Cannot drop index … needed in a foreign key constraint"), and
     * dropConstrainedForeignId drops the constraint before the column, at
     * which point the index goes with it.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_root_id');
            $table->dropConstrainedForeignId('previous_file_id');
        });
    }
};
