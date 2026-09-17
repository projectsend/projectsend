<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which client, if any, a folder is the home of.
 *
 * A column rather than a convention, because every other way of asking
 * "is this somebody's home folder" is a guess. Matching on the name breaks
 * the moment two clients share one; matching on `created_by` plus
 * `parent_id IS NULL` catches every root folder a client ever made for
 * themselves. The question gets asked on each upload and on every portal
 * listing, and the answer has to be exact: a home folder is one a client
 * may not delete and one their uploads default into.
 *
 * Unique, so one client cannot end up with two — the backfill is a button
 * an administrator can press twice, and a partial run followed by a second
 * press has to converge rather than accumulate.
 *
 * nullOnDelete, not cascade. Erasing a client must not take the folder and
 * everything filed in it with it; the content outlives the account, and
 * what the column loses is only the claim that it was ever somebody's home.
 * Deleting the *folder* is separately refused while it is a home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->foreignId('home_for_user_id')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->unique('home_for_user_id', 'folders_home_for_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->dropUnique('folders_home_for_user_id_unique');
            $table->dropConstrainedForeignId('home_for_user_id');
        });
    }
};
