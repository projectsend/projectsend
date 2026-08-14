<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-file opt-in for comments, consulted only when
 * Setting::CommentsScope is `selected` (see CommentScope::allows).
 * Under every other scope value this column is ignored entirely, which is
 * why the file editor hides the toggle unless that scope is active —
 * a control with no current effect is worse than no control.
 *
 * Defaults to false so switching the scope to `selected` starts from
 * "nothing is commentable" and an admin opts files in, rather than
 * silently opening every existing file at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->boolean('commentable')->default(false)->after('public');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('commentable');
        });
    }
};
