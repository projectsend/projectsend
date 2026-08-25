<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\StaffLibraryScope;
use Illuminate\Support\Facades\Gate;

/**
 * Per-comment authorization. `view` defers wholly to VisibleCommentScope
 * rather than restating its rules — a policy and a query scope that both
 * describe the same privacy boundary will eventually disagree, and the
 * one that disagrees quietly is the query.
 */
class FileCommentPolicy
{
    public function __construct(
        private readonly VisibleCommentScope $scope,
        private readonly CommentingRules $rules,
        private readonly StaffLibraryScope $library,
    ) {}

    public function view(User $user, FileComment $comment): bool
    {
        if (! Gate::forUser($user)->allows('view', $comment->file)) {
            return false;
        }

        return $this->scope->for($user, $comment->file)->whereKey($comment->getKey())->exists();
    }

    /**
     * Editing is the author's alone, inside a short window. Moderators are
     * deliberately excluded: deleting somebody's comment is moderation,
     * rewriting their words is not, and no permission in this app should
     * imply the latter.
     */
    public function update(User $user, FileComment $comment): bool
    {
        return $comment->author_id === $user->id && $this->withinEditWindow($comment);
    }

    public function delete(User $user, FileComment $comment): bool
    {
        if ($this->moderate($user, $comment)) {
            return true;
        }

        return $comment->author_id === $user->id && $this->withinEditWindow($comment);
    }

    /**
     * Called both ways: with a comment, to decide about that one, and
     * against the class, to ask whether this user moderates at all (the
     * queue's own gate, and the affordances that offer it).
     *
     * The library boundary belongs here rather than in each caller. Named
     * against the class it cannot be applied — there is no file to weigh —
     * so that form answers the coarser question and every caller holding a
     * comment should pass it.
     */
    public function moderate(User $user, ?FileComment $comment = null): bool
    {
        if (! $user->isStaff() || ! $user->can('moderate_comments')) {
            return false;
        }

        if ($comment === null || ! $user->isClientScoped()) {
            return true;
        }

        // By file id rather than through the relation: a file soft-deleted
        // out from under its comments resolves to null there, and the
        // answer for a scoped moderator is the same either way — it is not
        // in their library. Unscoped staff never reach this line.
        return $this->library->files($user)->whereKey($comment->file_id)->exists();
    }

    private function withinEditWindow(FileComment $comment): bool
    {
        $minutes = $this->rules->editWindowMinutes();

        if ($minutes <= 0) {
            return false;
        }

        return $comment->created_at !== null
            && $comment->created_at->diffInMinutes(now()) < $minutes;
    }
}
