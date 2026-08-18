<?php

declare(strict_types=1);

use App\Support\VacatedSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hands back the slugs held by rows deleted before slugs were vacated on
 * delete.
 *
 * Without this the fix only helps installations that have never deleted
 * anything: every folder, file and group already in the trash goes on
 * occupying its name, which is exactly the state being reported.
 *
 * Rewriting a deleted row's slug changes nothing anybody can see. A trashed
 * row is excluded from every public lookup by the soft-delete scope, so its
 * slug addresses no page — it was only ever visible as the reason a new row
 * could not have the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['files', 'folders', 'groups'] as $table) {
            DB::table($table)
                ->whereNotNull('deleted_at')
                ->whereNotNull('slug')
                ->where('slug', 'not like', '%'.VacatedSlug::MARKER.'%')
                // Chunked because an installation that has been running a
                // while can have a lot of these, and each row's new slug
                // depends on its own id rather than on anything set-wide.
                ->orderBy('id')
                ->chunkById(500, function (Collection $rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['slug' => VacatedSlug::for((string) $row->slug, (int) $row->id)]);
                    }
                });
        }
    }

    /**
     * Deliberately does not put the old slugs back. By the time this is rolled
     * back the names may well have been taken by live rows, and restoring them
     * would break the unique index this migration exists to stop tripping over.
     * A deleted row keeping a vacated slug harms nothing on older code — it
     * reads as an unusual name on a row nobody can see.
     */
    public function down(): void {}
};
