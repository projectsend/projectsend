<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telemetry for the public API — deliberately separate from `activity_log`.
 *
 * The two answer different questions and want different lifetimes. The
 * activity log is an audit trail of domain events ("uploaded Quarterly
 * report"), kept indefinitely and exported for compliance. This records
 * that a request happened and how it went, which is what a dashboard needs
 * for volume, latency and error rates — none of which are domain events,
 * and none of which the activity log could ever answer. Keeping them apart
 * also means this table can be pruned aggressively without touching the
 * audit trail, which must not be.
 *
 * Two privacy decisions are baked into the columns:
 *
 *  - `route` stores the *pattern* (`api/v1/clients/{client}`), never the
 *    resolved URI. Concrete ids would turn every row into a record of which
 *    client or file was touched, which is the audit log's job and is
 *    already covered there for the actions that warrant it. The pattern is
 *    also what aggregates usefully.
 *  - No IP address. The token identifies the caller, which is the question
 *    this table exists to answer, and adding one would create a new
 *    personal-data surface with its own retention argument.
 *
 * No foreign key on api_token_id: a row must outlive the token it
 * describes, since reviewing what a revoked token did is the main reason
 * anyone opens this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_token_id')->nullable();
            $table->string('api_token_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('route');
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->index();

            // The dashboard's two questions: "what has this token been
            // doing" and "what has the API been doing lately".
            $table->index(['api_token_id', 'created_at']);
            $table->index(['route', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
