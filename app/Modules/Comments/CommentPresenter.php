<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Gate;

/**
 * One shape for a comment thread, whoever is asking.
 *
 * The staff panel, the client portal, all four public themes and the API
 * render the same payload, so a field added here reaches every surface at
 * once — the same reason the theme pages consume controller props rather
 * than fetching their own (see docs/theming-files-checklist.md).
 *
 * A client's payload never says that a comment belongs to one client's
 * conversation rather than to all of them — `conversation` below is
 * staff-only. A UI flag can be got around by reading the network tab;
 * missing data cannot.
 */
class CommentPresenter
{
    public function __construct(
        private readonly VisibleCommentScope $scope,
        private readonly CommentingRules $rules,
    ) {}

    /**
     * The whole payload for one file's thread.
     *
     * $viewerMaySeeFile is the precondition VisibleCommentScope states at
     * the top of its class: the caller must already have established that
     * this viewer may see the file. A caller that has not says so, and the
     * thread is narrowed to the public reading instead of the
     * authenticated one.
     *
     * @return array{comments: list<array<string, mixed>>, can_comment: bool, cannot_comment_reason: string|null, is_guest: bool, guest_moderated: bool, captcha_required: bool, visibilities: list<array<string, mixed>>, default_visibility: string|null, edit_window_minutes: int}
     */
    public function thread(?User $viewer, File $file, bool $viewerMaySeeFile = true): array
    {
        $forStaff = $viewer?->isStaff() === true;

        $comments = ($viewerMaySeeFile
            ? $this->scope->for($viewer, $file)
            : $this->scope->forPublicReader($viewer, $file))
            ->with(['author', 'clientContext'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        /** @var list<array<string, mixed>> $presented */
        $presented = $comments->map(fn (FileComment $comment): array => $this->present($viewer, $comment))->values()->all();

        return [
            'comments' => $presented,
            'can_comment' => $this->rules->canPost($viewer, $file),
            // …and why not, when not. Without it the composer disappears
            // silently and the empty thread says "No comments yet", which
            // reads as nobody having written one rather than as the box
            // being closed — the two are indistinguishable to the person
            // looking for somewhere to type.
            'cannot_comment_reason' => $this->rules->postingBlockedReason($viewer, $file),
            // The composer asks an anonymous author for a name and warns
            // that their comment is held; a signed-in one gets neither.
            // Decided here because the public page serves both, and the
            // page cannot tell them apart — it has no viewer.
            'is_guest' => $viewer === null,
            // Whether an anonymous comment actually waits for a moderator.
            // The composer promises that it does, and the promise has to be
            // true: with moderation off the comment appears immediately,
            // and saying otherwise is simply a lie to the person writing it.
            'guest_moderated' => $this->rules->moderatesGuests(),
            // Whether this author will be asked for a security check. Sent
            // per thread rather than read from the shared props, because
            // only the server knows whether this viewer counts as a
            // visitor — the composer is the same component either way.
            'captcha_required' => $this->rules->captchaRequiredFor($viewer),
            // Labels depend on which end of the conversation is reading:
            // `clients` is one channel, and staff call it "Clients" while a
            // client calls it "Staff". Unavailable audiences are sent too,
            // so the composer can show them disabled with the reason rather
            // than leave a hole where an option used to be.
            'visibilities' => array_map(
                fn (array $option): array => [
                    'value' => $option['visibility']->value,
                    'label' => $option['visibility']->label($forStaff),
                    'description' => $option['visibility']->description($forStaff),
                    'available' => $option['available'],
                    'reason' => $option['reason'],
                ],
                $this->rules->visibilityOptions($viewer, $file),
            ),
            'default_visibility' => $this->rules->defaultVisibility($viewer, $file)?->value,
            'edit_window_minutes' => $this->rules->editWindowMinutes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(?User $viewer, FileComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author_name' => $comment->authorName(),
            'author_type' => $this->authorType($comment),
            'is_mine' => $viewer !== null && $comment->author_id === $viewer->id,
            'visibility' => $comment->visibility->value,
            'visibility_label' => $comment->visibility->label($viewer?->isStaff() === true),
            // Whose conversation this is, when it is one client's rather
            // than every client's. Staff only: a client must not be able to
            // tell that their comment sits among others.
            'conversation' => $viewer?->isStaff() === true && $comment->client_context_id !== null
                ? $comment->clientContext?->name
                : null,
            // Replying is how staff address one client — the audience is
            // inherited from here, never chosen. Only worth offering on a
            // comment that has a client behind it to answer.
            'can_reply' => $viewer?->isStaff() === true
                && $comment->visibility === CommentVisibility::Clients
                && $comment->client_context_id !== null
                && $comment->author_id !== $viewer->id,
            'pending' => $comment->isPending(),
            'created_at' => $comment->created_at?->toIso8601String(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'can_update' => $viewer !== null && Gate::forUser($viewer)->allows('update', $comment),
            'can_delete' => $viewer !== null && Gate::forUser($viewer)->allows('delete', $comment),
            // A held comment is only actionable where it is seen. The
            // moderation queue is for working through a backlog; somebody
            // who followed the alert on a file row is already looking at
            // the one comment they came to decide about.
            'can_approve' => $comment->isPending()
                && $viewer !== null
                && Gate::forUser($viewer)->allows('moderate', FileComment::class),
        ];
    }

    private function authorType(FileComment $comment): string
    {
        $author = $comment->author;

        if ($author === null) {
            return 'guest';
        }

        return $author->isStaff() ? 'staff' : 'client';
    }
}
