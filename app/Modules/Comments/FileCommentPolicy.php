<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\Models\FileComment;
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
        if ($this->moderate($user)) {
            return true;
        }

        return $comment->author_id === $user->id && $this->withinEditWindow($comment);
    }

    public function moderate(User $user): bool
    {
        return $user->isStaff() && $user->can('moderate_comments');
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
