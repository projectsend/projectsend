<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records how each audited action arrived: the web UI, an API token, or
 * the system. Previously an API action was only distinguishable by a `via`
 * key inside the JSON context, which cannot be filtered or indexed and did
 * not exist at all before the API shipped.
 *
 * `api_token_name` sits beside `api_token_id` for the same reason
 * `actor_name` sits beside `actor_id`: this table is a snapshot that has to
 * outlive the rows it describes, and a revoked token would otherwise leave
 * entries pointing at nothing. No foreign key, deliberately — an audit row
 * must survive the token's deletion, and a cascade would erase exactly the
 * history someone investigating a leaked token needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('origin', 16)->default('ui')->index()->after('actor_type');
            $table->unsignedBigInteger('api_token_id')->nullable()->after('origin');
            $table->string('api_token_name')->nullable()->after('api_token_id');
        });

        // Existing rows: anything with no actor was the system, everything
        // else came from the UI — the API did not exist when they were
        // written. The handful of entries the API wrote between its first
        // release and this migration carry `via: api` in their context, so
        // promote those rather than mislabel them.
        DB::table('activity_log')->whereNull('actor_type')->update(['origin' => 'system']);

        // Matched through the JSON path rather than a LIKE over the raw
        // text: MySQL's `json` column type stores a normalised document,
        // so the value comes back as `{"via": "api"}` — with a space that
        // a naive `%"via":"api"%` pattern misses entirely, silently
        // relabelling every historical API action as a UI one. The JSON
        // operator is also the only form that works on both MySQL and the
        // SQLite the test suite runs on.
        DB::table('activity_log')
            ->where('context->via', 'api')
            ->update([
                'origin' => 'api',
                // Those entries recorded the token's name in the context
                // bag; lift it into the column so the log reads the same
                // for them as for everything written after this.
                'api_token_name' => DB::raw($this->jsonExtract('context', 'api_token')),
            ]);
    }

    /**
     * MySQL and SQLite spell "read this JSON key as text" differently, and
     * this migration has to run on both — MySQL in production, SQLite in
     * the test suite.
     */
    private function jsonExtract(string $column, string $key): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract({$column}, '$.{$key}')"
            : "json_unquote(json_extract({$column}, '$.{$key}'))";
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['origin']);
            $table->dropColumn(['origin', 'api_token_id', 'api_token_name']);
        });
    }
};
