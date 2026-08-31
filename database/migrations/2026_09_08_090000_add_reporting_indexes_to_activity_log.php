<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two indexes every windowed count over `activity_log` needs.
 *
 * **They ship as a pair, and adding only the second one is a
 * regression.** That is the whole reason this comment is long.
 *
 * Measured on 2.1M rows (MySQL 8.4, two years of history, ~800k of them
 * downloads), which is a mid-size installation and not a stress test:
 *
 * | query                                   | before | after   |
 * |-----------------------------------------|--------|---------|
 * | last staff/client sign-in               | 0.63s  | 0.0004s |
 * | downloads in the last 30 days           | 1.07s  | 0.022s  |
 * | uploads in the last 30 days             | 0.42s  | 0.005s  |
 *
 * ### Why a date window was *slower* than no date window
 *
 * Counting every download ever took 0.46s; counting the last 30 days of
 * them took 1.07s. Not a mistake: with only the single-column indexes,
 * the planner picks `created_at`, reaches the window, and then has to do
 * a primary-key lookup on each row to read `action`. The unbounded count
 * stays inside the `action` index and never touches a row. So the naive
 * "just add a date filter" made the query cost more, which is the
 * opposite of what anybody writing it expects.
 *
 * ### Why (action, created_at) alone is not the fix
 *
 * It fixes the windowed counts and breaks the query
 * `projectsend:status` already runs on every tenant, every hour:
 * `last_staff_login_at` goes from **0.63s to 7.7s**, reproduced. The
 * planner switches to the new index, still needs `actor_type` — which is
 * not in it — and does a primary-key lookup per row; and because the
 * scan is now ordered by `created_at` rather than by id, those lookups
 * are scattered instead of sequential.
 *
 * That is why (action, actor_type, created_at) is here too, and why
 * dropping it as "redundant, the two-column one covers it" is exactly
 * the change that would put the regression back. It is not redundant:
 * it is the only one of the two that answers a question filtered by
 * actor without reading rows.
 *
 * ### Cost
 *
 * About 170 MB of index at 2.1M rows, against a 228 MB primary key.
 * Real, and bought with a 1500x improvement on a query that was already
 * running hourly before any of the new reporting existed.
 *
 * On MySQL both are added in place, so an existing installation stays
 * readable and writable while it happens — but on a large `activity_log`
 * it is minutes, not seconds, and it is the slowest part of the upgrade
 * that carries it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // "What did this kind of account do, and when did they last
            // do it" — both sign-in timestamps, and the download split.
            $table->index(['action', 'actor_type', 'created_at'], 'activity_log_action_actor_created_index');

            // "How many of these happened in the window", for actions
            // nobody is narrowing by actor.
            $table->index(['action', 'created_at'], 'activity_log_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_action_actor_created_index');
            $table->dropIndex('activity_log_action_created_index');
        });
    }
};
