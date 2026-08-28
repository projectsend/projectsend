<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\Notifier;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Everything that happens when a comment is written, changed, removed or
 * approved — in one place, so the web UI, the public page and the API
 * cannot end up with three slightly different ideas of it: side effects
 * belong in a service, not in one surface's controller.
 *
 * Controllers here are thin on purpose: they resolve who is asking and
 * hand over. In particular they do not decide client_context_id, which is
 * the field the whole privacy model turns on — that decision lives in
 * resolveClientContext() below and nowhere else.
 */
class FileComments
{
    public function __construct(
        private readonly CommentingRules $rules,
        private readonly VisibleCommentScope $scope,
        private readonly StaffLibraryScope $library,
        private readonly ActivityLogger $log,
        private readonly Notifier $notifier,
        private readonly NotificationDigester $digester,
    ) {}

    /**
     * @param  User|null  $author  Null for an anonymous visitor.
     * @param  FileComment|null  $replyTo  The comment being answered. A reply
     *                                     inherits its audience, which is the
     *                                     only way a comment becomes addressed
     *                                     to one client rather than all of them.
     *
     * @throws AuthorizationException
     */
    public function post(
        File $file,
        ?User $author,
        CommentVisibility $visibility,
        string $body,
        ?FileComment $replyTo = null,
        ?string $guestName = null,
    ): FileComment {
        if ($replyTo !== null) {
            // A reply is not a fresh choice of audience — it joins one that
            // already exists. Letting the caller pass both would be two
            // sources of truth for who reads this, and the wrong one would
            // eventually win.
            $visibility = $replyTo->visibility;
        }

        // Two refusals, and they are not the same sentence. "That audience
        // is not available" is wrong when the real answer is that this
        // person may not comment here at all — and that is the answer an
        // API caller sees, so it has to name the actual obstacle.
        if (($blocked = $this->rules->postingBlockedReason($author, $file)) !== null) {
            throw new AuthorizationException($blocked);
        }

        if (! in_array($visibility, $this->rules->allowedVisibilities($author, $file), true)) {
            throw new AuthorizationException('This comment visibility is not available on this file.');
        }

        $pending = $author === null && $this->rules->moderatesGuests();

        $comment = FileComment::query()->create([
            'file_id' => $file->id,
            'author_id' => $author?->id,
            'client_context_id' => $this->resolveClientContext($author, $visibility, $replyTo),
            'guest_name' => $author === null ? $guestName : null,
            // Only ever recorded for anonymous authors, and only because
            // it is the one handle that makes spam actionable — an account
            // is already identified by its author_id.
            'ip_address' => $author === null ? request()->ip() : null,
            'visibility' => $visibility,
            'body' => $body,
            'approved_at' => $pending ? null : now(),
        ]);

        // An anonymous comment gets its own action rather than a null
        // actor: "System commented on this file" is not what happened, and
        // the name a visitor gave is the only handle the entry has.
        $author === null
            ? $this->log->log(Action::CommentPostedByVisitor, null, $file, [
                'visibility' => $visibility->value,
                'guest' => $comment->authorName(),
            ])
            : $this->log->log(Action::CommentPosted, $author, $file, ['visibility' => $visibility->value]);

        if ($pending) {
            // Nobody can see the comment yet, so the only people to tell
            // are the ones who can act on that.
            $this->notifier->send('file_comment.pending', $this->scope->moderators($file), $file, [
                'fileId' => $file->id,
                'fileName' => $file->name,
                'authorName' => $comment->authorName(),
            ]);

            return $comment;
        }

        $this->announce($comment);

        return $comment;
    }

    public function edit(FileComment $comment, string $body): FileComment
    {
        $comment->forceFill(['body' => $body, 'edited_at' => now()])->save();

        $this->log->log(Action::CommentEdited, null, $comment->file);

        return $comment;
    }

    /**
     * Soft delete, consistent with the rest of the app: a staff member
     * looking into a dispute later should not find a hole where the
     * conversation was.
     */
    public function remove(FileComment $comment): void
    {
        $this->log->log(Action::CommentDeleted, null, $comment->file);

        $comment->delete();
    }

    /**
     * Release a held anonymous comment. Only now does it get announced —
     * approval is the moment it becomes something anyone can see.
     */
    public function approve(FileComment $comment, User $moderator): FileComment
    {
        if (! $comment->isPending()) {
            return $comment;
        }

        $comment->forceFill(['approved_at' => now()])->save();

        $this->log->log(Action::CommentApproved, $moderator, $comment->file);

        $this->announce($comment);

        return $comment;
    }

    /**
     * Notifier performs no authorization of its own — its contract puts
     * that on the caller — so the recipient list comes from the same scope
     * that decides who may read the comment. Deriving it any other way is
     * how a notification becomes a second, unaudited way to leak one
     * client's existence to another.
     */
    private function announce(FileComment $comment): void
    {
        $recipients = $this->scope->recipientsFor($comment);

        $this->notifier->send('file_comment.posted', $recipients, $comment->file, [
            'fileId' => $comment->file_id,
            'fileName' => $comment->file->name,
            'authorName' => $comment->authorName(),
        ]);

        // The same already-permission-checked list, buffered so a burst of
        // replies becomes one email instead of one each. The digester
        // applies the email gates (master switch, per-recipient
        // preference) — this list is about who may *see* the comment,
        // which is a different question.
        $this->digester->queue('file_comment.posted', $recipients, $comment->file->name, [
            'file_id' => $comment->file_id,
            'author_name' => $comment->authorName(),
        ]);
    }

    /**
     * Which client's conversation this comment joins, or none.
     *
     * Only a Clients comment can belong to one: an OnlyMe note has an
     * audience of one, a StaffOnly note has no client in it, and an
     * Everyone comment is addressed to people with no client identity at
     * all. Giving those a context would create rows that look
     * conversation-scoped but are read by a different rule — the near-miss
     * that makes a privacy model unauditable.
     *
     * Nobody names a client here. A client's own comment is theirs by
     * construction; a staff comment is addressed to every client on the
     * file unless it is a reply, in which case it goes exactly where the
     * comment it answers went. There is no request field that can point a
     * comment at an arbitrary client.
     *
     * @throws AuthorizationException
     */
    private function resolveClientContext(?User $author, CommentVisibility $visibility, ?FileComment $replyTo): ?int
    {
        if ($visibility !== CommentVisibility::Clients || $author === null) {
            return null;
        }

        // A client always writes in their own conversation.
        if (! $author->isStaff()) {
            return $author->id;
        }

        if ($replyTo === null) {
            // Staff writing fresh address every client the file is shared
            // with. They read it; they cannot see each other's replies.
            return null;
        }

        // Asked of the column, not of the relation. client_context_id is
        // cascadeOnDelete, but a user is soft-deleted, so the cascade
        // never fires: the column goes on pointing at a row that is still
        // there while the relation resolves to null. Branching on the
        // relation therefore read "this is Alice's conversation" as "this
        // has no conversation" — and a null context on a Clients comment
        // is the branch every client on the file reads (see
        // VisibleCommentScope's opening rule). A private reply became a
        // circular, and canAssignClient below was skipped on the way.
        if ($replyTo->client_context_id === null) {
            return null;
        }

        $client = $replyTo->clientContext;

        if ($client === null) {
            // The column points at somebody, and that somebody is gone.
            // There is nobody to answer, and the one outcome that must
            // not follow from a filled column is the broadcast above, so
            // this refuses rather than falling through to it.
            throw new AuthorizationException('You cannot reply in this conversation.');
        }

        if (! $this->library->canAssignClient($author, $client)) {
            throw new AuthorizationException('You cannot reply in this conversation.');
        }

        return $client->id;
    }
}
