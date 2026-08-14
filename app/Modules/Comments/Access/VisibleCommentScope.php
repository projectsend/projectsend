<?php

declare(strict_types=1);

namespace App\Modules\Comments\Access;

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\GuestCommentIdentity;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\ShareTargets;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\InAppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The single place that decides which comments on a file a viewer may
 * read, and — its mirror image — which people a new comment may be
 * announced to.
 *
 * Follows the same convention as Files' FilePolicy/ViewableFileScope pair:
 * one question, expressed once, in SQL, so no caller has to re-derive it.
 * Unlike that pair there is no per-model policy twin doing the same work
 * in PHP; FileCommentPolicy::view() defers to this class rather than
 * restating it, because two statements of a rule this sharp will drift.
 *
 * **Callers must have already established that the viewer may see the
 * file itself** (Gate::authorize('view', $file), or for guests, that the
 * file is publicly reachable). This class narrows an accessible file's
 * comments; it is not a substitute for the file's own gate.
 *
 * The rule that everything else hangs off:
 *
 *   A Clients comment carrying client_context_id = C is never returned to
 *   any non-staff viewer other than C.
 *
 * That is what stops one customer learning that another exists — a worse
 * failure than leaking a comment's text. A Clients comment with a *null*
 * context is a staff message to everyone on the file, and every client
 * with access reads it: it is written by staff and names no client, so
 * seeing it tells a reader nothing about who else is there.
 *
 * Nothing outside this class may query file_comments by file_id alone.
 */
class VisibleCommentScope
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly ShareTargets $shareTargets,
        private readonly GuestCommentIdentity $guests,
    ) {}

    /**
     * @param  User|null  $viewer  Null means an anonymous visitor.
     * @return Builder<FileComment>
     */
    public function for(?User $viewer, File $file): Builder
    {
        return $this->applyVisibility(
            FileComment::query()->where('file_id', $file->id),
            $viewer,
            $file->isEffectivelyPublic(),
        );
    }

    /**
     * Every comment this staff member may read, across their whole library
     * — the management screen's query, rather than one file's thread.
     *
     * Same predicate as for(), so the screen that lists everything is still
     * governed by the rule at the top of this class: another person's "only
     * me" note is not in it, and neither is a conversation belonging to a
     * client this viewer is not assigned to. **A moderation screen is not a
     * way around the visibility model** — moderating means deciding about
     * comments you can already see.
     *
     * Staff only. A client has no cross-file view of comments and asking
     * for one is a mistake rather than an empty result, but returning
     * nothing is the safe way to be wrong.
     *
     * @return Builder<FileComment>
     */
    public function across(User $viewer): Builder
    {
        if (! $viewer->isStaff()) {
            return FileComment::query()->whereRaw('1 = 0');
        }

        return $this->applyVisibility(
            FileComment::query()->whereIn('file_id', $this->scope->files($viewer)->select('files.id')),
            $viewer,
            // Publicness is a property of each file, so it cannot be one
            // value for a query spanning many. It does not have to be: the
            // staff branch of applyVisibility never reads this argument
            // (staff keep seeing Everyone comments whatever the file's
            // current state), and this method is staff-only.
            isPublic: false,
        );
    }

    /**
     * How many comments are waiting for a decision anywhere in this
     * viewer's library — the sidebar badge.
     *
     * Deliberately not derived from across(): a held comment is invisible
     * to everyone but a moderator, so the number is about the queue rather
     * than about what this viewer may read, and a moderator who cannot see
     * a particular client's thread must still be told the file has
     * something waiting.
     */
    public function pendingTotal(User $viewer): int
    {
        if (! $viewer->isStaff() || ! $viewer->can('moderate_comments')) {
            return 0;
        }

        return FileComment::query()
            ->whereNull('approved_at')
            ->whereIn('file_id', $this->scope->files($viewer)->select('files.id'))
            ->count();
    }

    /**
     * How many comments each of these files has, from this viewer's point
     * of view — the number on a file row.
     *
     * Runs the same predicate as for(), because it calls the same private
     * method: this is the bulk shape of one rule, not a second statement
     * of it. The only thing it cannot batch is whether a file is public,
     * which is a property of each row rather than of the query, so the
     * files are split into two groups and the predicate applied to each.
     *
     * @param  iterable<File>  $files
     * @return array<int, int> file id => count
     */
    public function countsFor(?User $viewer, iterable $files): array
    {
        $public = [];
        $private = [];

        foreach ($files as $file) {
            if ($file->isEffectivelyPublic()) {
                $public[] = $file->id;
            } else {
                $private[] = $file->id;
            }
        }

        $counts = [];

        foreach ([[$public, true], [$private, false]] as [$ids, $isPublic]) {
            if ($ids === []) {
                continue;
            }

            $rows = $this->applyVisibility(FileComment::query()->whereIn('file_id', $ids), $viewer, $isPublic)
                ->selectRaw('file_id, count(*) as aggregate')
                ->groupBy('file_id')
                ->pluck('aggregate', 'file_id');

            foreach ($rows as $fileId => $count) {
                $counts[(int) $fileId] = (int) $count;
            }
        }

        return $counts;
    }

    /**
     * @param  Builder<FileComment>  $query
     * @return Builder<FileComment>
     */
    private function applyVisibility(Builder $query, ?User $viewer, bool $isPublic): Builder
    {
        // A comment awaiting moderation exists only for those who can act
        // on it — and for whoever wrote it, who would otherwise watch their
        // own comment vanish on posting and conclude it had failed. A
        // visitor is recognised by their session (see GuestCommentIdentity);
        // that is weak on purpose, and only ever widens what somebody sees
        // of their own writing.
        if ($viewer === null || ! $viewer->can('moderate_comments')) {
            $ownPending = $viewer === null ? $this->guests->ownCommentIds() : [];

            $query->where(fn (Builder $visible) => $visible
                ->whereNotNull('approved_at')
                ->when($ownPending !== [], fn (Builder $mine) => $mine->orWhereIn('id', $ownPending)));
        }

        if ($viewer === null) {
            // Publicness is re-derived here on every read rather than
            // frozen onto the comment at write time, so making a file
            // private later retracts its public comments too.
            return $isPublic
                ? $query->where('visibility', CommentVisibility::Everyone)
                : $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($viewer, $isPublic): void {
            // Your own comments, whatever audience you gave them. This is
            // the only branch that can return a CommentVisibility::OnlyMe
            // row, which is what makes "only me" mean it.
            $outer->where('author_id', $viewer->id);

            if ($viewer->isStaff()) {
                // Staff keep seeing Everyone comments after a file stops
                // being public — they can see the file, and a history with
                // holes in it is worse than one that is merely stale.
                $outer->orWhere('visibility', CommentVisibility::Everyone);
                $outer->orWhere('visibility', CommentVisibility::StaffOnly);
                $this->applyStaffThreads($outer, $viewer);

                return;
            }

            if ($isPublic) {
                $outer->orWhere('visibility', CommentVisibility::Everyone);
            }

            // A client reads two things: what staff addressed to every
            // client on this file, and their own conversation. Never a
            // staff-only note, and never another client's conversation —
            // they are not told the others are there.
            $outer->orWhere(fn (Builder $broadcast) => $broadcast
                ->where('visibility', CommentVisibility::Clients)
                ->whereNull('client_context_id'));

            $outer->orWhere(fn (Builder $thread) => $thread
                ->where('visibility', CommentVisibility::Clients)
                ->where('client_context_id', $viewer->id));
        });
    }

    /**
     * How many comments on each file are waiting for a decision.
     *
     * Distinct from unreadCountsFor: unread is "you have not looked at
     * this yet", pending is "nobody can see this until somebody acts".
     * Only a moderator gets a count at all, which is why the empty array
     * for everyone else is the whole answer rather than a filter applied
     * afterwards — a staff member who cannot approve should not be shown
     * a badge asking them to.
     *
     * @param  list<int>  $fileIds
     * @return array<int, int> file id => count
     */
    public function pendingCountsFor(User $viewer, array $fileIds): array
    {
        if ($fileIds === [] || ! $viewer->can('moderate_comments')) {
            return [];
        }

        $rows = FileComment::query()
            ->whereIn('file_id', $fileIds)
            ->whereNull('approved_at')
            ->selectRaw('file_id, count(*) as aggregate')
            ->groupBy('file_id')
            ->pluck('aggregate', 'file_id');

        $counts = [];

        foreach ($rows as $fileId => $count) {
            $counts[(int) $fileId] = (int) $count;
        }

        return $counts;
    }

    /**
     * How many comments on each file are new to this viewer.
     *
     * Read state rides on the notification system's own `read_at` rather
     * than a file_comment_reads table of its own: a viewer has already
     * been told about every comment they may see (that is what
     * recipientsFor guarantees), so "unread notification about this file"
     * and "unread comment on this file" are the same set. A second
     * mechanism would only be a second thing to keep in step.
     *
     * @param  list<int>  $fileIds
     * @return array<int, int> file id => count
     */
    public function unreadCountsFor(User $viewer, array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }

        $rows = InAppNotification::query()
            ->where('user_id', $viewer->id)
            ->where('type', 'file_comment.posted')
            ->where('subject_type', (new File)->getMorphClass())
            ->whereIn('subject_id', $fileIds)
            ->whereNull('read_at')
            ->selectRaw('subject_id, count(*) as aggregate')
            ->groupBy('subject_id')
            ->pluck('aggregate', 'subject_id');

        $counts = [];

        foreach ($rows as $fileId => $count) {
            $counts[(int) $fileId] = (int) $count;
        }

        return $counts;
    }

    /**
     * Who should be told about $comment, excluding its own author.
     *
     * This is the set-of-people mirror of for()'s set-of-rows, and exists
     * so Notifier's security contract can be honoured without the caller
     * re-deriving visibility: a notification must never reach somebody the
     * comment itself would not.
     *
     * Deliberately narrower than "everyone who could read it" in one case:
     * an Everyone comment notifies staff only. Every client on a public
     * file receiving a notification for every public remark would be noise,
     * and staff are the ones who would act on it.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(FileComment $comment): Collection
    {
        $file = $comment->file;

        /** @var Collection<int, User> $none */
        $none = new Collection;

        if ($comment->visibility === CommentVisibility::OnlyMe) {
            return $none;
        }

        $recipients = $this->staffWhoCanSee($file);

        $client = $comment->clientContext;

        if ($comment->visibility === CommentVisibility::Clients) {
            if ($client !== null) {
                // Staff whose library scope excludes this conversation's
                // client must not hear about it — the same boundary for()
                // applies to rows.
                $recipients = $recipients
                    ->filter(fn (User $staff): bool => $this->scope->canAssignClient($staff, $client))
                    ->values();

                $recipients->push($client);
            } else {
                // Addressed to every client on the file, so every client on
                // the file is told. This is the one case that fans out.
                foreach ($this->clientsWhoCanSee($comment->file) as $recipient) {
                    $recipients->push($recipient);
                }
            }
        }

        return $recipients
            ->reject(fn (User $user): bool => $user->id === $comment->author_id)
            ->values();
    }

    /**
     * Staff who hold moderation rights — the audience for a comment held
     * for approval, which is not the same audience as the comment itself
     * (it has none yet).
     *
     * @return Collection<int, User>
     */
    public function moderators(File $file): Collection
    {
        return $this->staffWhoCanSee($file)
            ->filter(fn (User $staff): bool => $staff->can('moderate_comments'))
            ->values();
    }

    /**
     * Clients this file is in front of: assigned to it, to a group it is
     * shared with, or to the folder it sits in or any folder above that.
     *
     * Expressed through ShareTargets rather than as an inverted
     * File::scopeVisibleToClient — that scope is the authority on "can this
     * client see this file", and writing its mirror image here would be a
     * third statement of a rule this feature depends on not drifting. The
     * cost is that a client who reaches the file only by having uploaded it
     * themselves is not notified of a message to all clients; they still
     * read it when they open the file.
     *
     * @return Collection<int, User>
     */
    private function clientsWhoCanSee(File $file): Collection
    {
        $shares = $this->shareTargets->assigned($file);

        $groupIds = array_column($shares['groups'], 'id');
        $clientIds = array_column($shares['clients'], 'id');

        $folder = $file->folder;

        if ($folder !== null) {
            foreach (Folder::query()->whereIn('id', [$folder->id, ...$folder->ancestorIds()])->get() as $ancestor) {
                $inherited = $this->shareTargets->assigned($ancestor);
                $groupIds = [...$groupIds, ...array_column($inherited['groups'], 'id')];
                $clientIds = [...$clientIds, ...array_column($inherited['clients'], 'id')];
            }
        }

        /** @var Collection<int, User> $clients */
        $clients = User::query()
            ->where('type', UserType::Client)
            ->where(fn (Builder $who) => $who
                ->whereIn('id', $clientIds)
                ->orWhereHas('memberOfGroups', fn (Builder $groups) => $groups->whereIn('groups.id', $groupIds)))
            ->get();

        return $clients;
    }

    /**
     * Resolved per user through the file's own policy rather than as one
     * query: FilePolicy::view mixes a permission check (a property of the
     * viewer) with a scope check (a property of the row), and staff counts
     * are small by design in this product — the v2 model puts limited
     * staff on client scoping, not on large teams.
     *
     * @return Collection<int, User>
     */
    private function staffWhoCanSee(File $file): Collection
    {
        /** @var Collection<int, User> $staff */
        $staff = User::query()->where('type', UserType::Staff)->get();

        return $staff->filter(fn (User $user): bool => Gate::forUser($user)->allows('view', $file))->values();
    }

    /**
     * @param  Builder<FileComment>  $outer
     */
    private function applyStaffThreads(Builder $outer, User $viewer): void
    {
        // Null means unrestricted: this staff member sees every client's
        // conversation on a file they can already open.
        $clientIds = $this->scope->assignableClientIds($viewer);

        $outer->orWhere(function (Builder $thread) use ($clientIds): void {
            $thread->where('visibility', CommentVisibility::Clients);

            if ($clientIds === null) {
                return;
            }

            // A client-scoped staff member sees their own clients'
            // conversations, plus the messages addressed to every client on
            // the file (no conversation of their own, so no client to be
            // out of scope for), and nothing of the clients they are not
            // assigned to.
            $thread->where(fn (Builder $context) => $context
                ->whereNull('client_context_id')
                ->orWhereIn('client_context_id', $clientIds));
        });
    }
}
