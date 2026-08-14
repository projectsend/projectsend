<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comments attached to a file, each carrying its author's own choice of
 * who it reaches (App\Modules\Comments\CommentVisibility).
 *
 * Two columns carry the whole privacy model and deserve their reasoning
 * next to the schema rather than only in a doc:
 *
 *  - `client_context_id` is which client's conversation a comment belongs
 *    to, not who wrote it. A client's own comment sets it to themselves; a
 *    staff reply sets it to the client being replied to; a staff comment
 *    with it left NULL is an internal note no client ever sees. This is
 *    what keeps "people with access" from meaning "every client this file
 *    happens to be shared with" — a comment scoped to client A is never
 *    returned to client B, so B never learns A exists. Every read goes
 *    through Access\VisibleCommentScope; nothing else may query this table
 *    by file_id alone, which is precisely the shape of the cross-client
 *    leak this design exists to prevent.
 *
 *  - `approved_at` NULL means "awaiting moderation", which only ever
 *    happens to anonymous comments (see Setting::CommentsGuestModeration).
 *    Authenticated comments are stamped at insert. It is nullable rather
 *    than a boolean so the moderation queue can order by when a decision
 *    was made.
 *
 * `author_id` is nullable because anonymous visitors may comment on a
 * publicly-visible file; `guest_name` and `ip_address` are only ever
 * populated for those rows, the IP being what makes spam actionable.
 *
 * Both foreign keys cascade on delete rather than nulling: a comment with
 * no file is meaningless, and a comment whose thread-owning client is gone
 * has no thread left to belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_context_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('visibility', 20);
            $table->text('body');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Loading a thread: always by file, usually narrowed by one of
            // the two columns the visibility rules turn on.
            $table->index(['file_id', 'visibility']);
            $table->index(['file_id', 'client_context_id']);
            // The moderation queue's own listing.
            $table->index('approved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_comments');
    }
};
