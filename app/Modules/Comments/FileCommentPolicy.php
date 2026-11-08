<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Identity\Permissions\PermissionChecker;
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
        private readonly ViewableFileScope $viewable,
        private readonly PermissionChecker $permissions,
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

        // Moderating is deciding about comments you can already see, so the
        // permission half of file reading is part of the answer in both
        // forms. Without one of the three file keys this user gets a 403 on
        // every file these comments are about, and approving one hands back
        // its body — so this is a reading door, not only a writing one.
        //
        // From the role, not an API token's abilities: a token granted
        // moderate_comments was granted reading the comments it moderates,
        // and nothing more (see PermissionChecker::asRole()). The
        // moderate_comments check above stays capped.
        if (! $this->permissions->asRole(fn (): bool => $this->viewable->permitsAnyFile($user))) {
            return false;
        }

        if ($comment === null) {
            return true;
        }

        // The comment itself, not only its file: the moderation screen is
        // across(), and a comment it would not list is not this moderator's
        // to decide about. Asking only whether the file was in their
        // library let a client-scoped moderator delete, by id, a
        // conversation with a client off their roster on a shared file, and
        // any moderator delete somebody's "only me" note, which says
        // nobody else can read it, staff included. From the role, for the
        // reason given above.
        return $this->permissions->asRole(
            fn (): bool => $this->scope->across($user)->whereKey($comment->getKey())->exists(),
        );
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
