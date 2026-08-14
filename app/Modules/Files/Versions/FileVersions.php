<?php

declare(strict_types=1);

namespace App\Modules\Files\Versions;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Sharing\FileSharing;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Marking one file as a revision of another, and everything that follows
 * from it.
 *
 * Its own class rather than part of FilesController for the reason
 * FileSharing is: linking is not one UPDATE. It is an UPDATE plus an
 * authorization pair, a cycle check, a move of assignment rows onto the
 * original, a re-stamp of the chain's roots, an activity entry and a
 * notification — and it has three callers (the file editor, the upload
 * flow, the API) that must not be able to answer any of it differently.
 *
 * Two invariants this class is responsible for, both asserted by
 * `versioning:check`:
 *
 *  1. previous_file_id IS NULL <=> version_root_id IS NULL.
 *  2. A file with a non-null version_root_id owns no file_assignments rows.
 *     Its audience is the root's (see File::sharingOwnerId and
 *     Access\SharingIdentity). This is what makes "a revision is always
 *     shared with the same people as the original" a property of the
 *     schema rather than a convention some future write path can forget.
 *
 * Authorization is NOT the caller's job here, unlike FileSharing: the
 * escalation this feature can produce is specific to versioning (inheriting
 * a stranger's recipients), so the check lives at the seam every caller
 * reaches. See FilePolicy::setVersion.
 */
class FileVersions
{
    /**
     * How deep a version history may go.
     *
     * Doubles as the termination guard for the chain walks below: if the
     * table were ever corrupted into a cycle, every walk here still ends.
     */
    public const MAX_CHAIN = 50;

    public function __construct(
        private readonly ViewableFileScope $viewable,
        private readonly FileSharing $sharing,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
        private readonly NotificationDigester $digester,
    ) {}

    /**
     * Mark $file as a revision of $previous. Idempotent: re-linking an
     * existing pair is a no-op rather than an error, which matters for an
     * API caller retrying a request.
     *
     * @throws ValidationException when a rule rejects the pair
     * @throws AuthorizationException when $actor may not version either file
     */
    public function link(File $file, File $previous, User $actor): void
    {
        $this->guard($file, $previous, $actor);

        if ($file->previous_file_id === $previous->id) {
            return;
        }

        $root = $previous->version_root_id ?? $previous->id;

        // RESOLVED BEFORE THE MERGE, and the ordering is the whole dedupe:
        // these are the people who could already see both files, so anyone
        // the merge below is about to reach for the first time is excluded
        // here and gets file_shared from FileSharing::assign() instead.
        // Resolve it afterwards and every newly-added client receives two
        // emails about one action.
        $audience = $this->sharedAudience($file, $previous);

        try {
            DB::transaction(function () use ($file, $previous, $root, $actor): void {
                // Move, never drop: a revision holds no recipients of its
                // own, but the people who already had this file must not
                // lose it. Through FileSharing so each target still gets
                // its activity entry, notification and digest.
                $this->moveAssignmentsToRoot($file, $root);

                $file->update([
                    'previous_file_id' => $previous->id,
                    'version_root_id' => $root,
                ]);

                // $file may itself have revisions; the whole subchain now
                // belongs to $root.
                $this->recomputeRoots($root);

                $this->activity->log(
                    Action::FileVersionLinked,
                    actor: $actor,
                    subject: $file,
                    context: ['previous' => $previous->name],
                );
            });
        } catch (UniqueConstraintViolationException) {
            // Two staff picked the same original at once. The pre-check in
            // guard() lost the race; the index is the authority. The
            // message deliberately omits the winner's name — it may be a
            // file this actor is not allowed to know about.
            throw ValidationException::withMessages([
                'previous_file_id' => __('That file has just been revised by another file. Reload and try again.'),
            ]);
        }

        if ($audience->isNotEmpty()) {
            $this->notifier->send('file_new_version', $audience, subject: $file, data: [
                'itemName' => $file->name,
                'previousName' => $previous->name,
            ]);

            $this->digester->queue('file_new_version', $audience, $file->name, [
                'previousName' => $previous->name,
            ]);
        }
    }

    /**
     * The clients who could see BOTH files before this link was made.
     *
     * Notifier performs no authorization of its own — see its SECURITY
     * CONTRACT docblock — so this is where the list has to be exact. It is
     * an INTERSECTION, never a difference: a client who holds only the old
     * file must be told nothing, because the new one was never shared with
     * them and naming it would leak a file they were not granted. That is
     * the same rule the badge follows, shaped as people.
     *
     * Candidates come from the previous file's own audience rather than a
     * broad user query, then each is re-checked against both files with the
     * authoritative visibility scope.
     *
     * @return Collection<int, User>
     */
    private function sharedAudience(File $file, File $previous): Collection
    {
        $candidateIds = FileAssignment::query()
            ->where('file_id', $previous->sharingOwnerId())
            ->get()
            ->flatMap(function (FileAssignment $assignment): array {
                $target = $assignment->assignable;

                if ($target instanceof Group) {
                    return $target->members->pluck('id')->all();
                }

                return $target instanceof User ? [$target->id] : [];
            })
            ->unique()
            ->values();

        if ($candidateIds->isEmpty()) {
            /** @var Collection<int, User> $empty */
            $empty = new Collection;

            return $empty;
        }

        return User::query()->whereIn('id', $candidateIds)->get()
            ->filter(fn (User $candidate): bool => File::query()->whereKey($file->id)->visibleToClient($candidate)->exists()
                && File::query()->whereKey($previous->id)->visibleToClient($candidate)->exists())
            ->values();
    }

    /**
     * Detach $file from its original, making it a root again.
     *
     * The root's current recipients are copied onto it first: it is about
     * to stop inheriting them, and silently revoking access people already
     * have is never the right reading of "unlink".
     *
     * @return bool whether a link was actually removed
     */
    public function unlink(File $file, User $actor): bool
    {
        if ($file->previous_file_id === null) {
            return false;
        }

        Gate::forUser($actor)->authorize('setVersion', $file);

        DB::transaction(function () use ($file): void {
            $this->copyAssignmentsFrom($file->sharingOwnerId(), $file);

            $file->update(['previous_file_id' => null, 'version_root_id' => null]);

            // $file is the head of whatever followed it; that subchain
            // roots at $file now.
            $this->recomputeRoots($file->id);
        });

        $this->activity->log(Action::FileVersionUnlinked, actor: $actor, subject: $file);

        return true;
    }

    /**
     * The full lineage $viewer may see, oldest first, including $file
     * itself. Files in the chain the viewer cannot see are omitted rather
     * than replaced with a placeholder — a gap is less informative than a
     * hint that something exists.
     *
     * @return Collection<int, File>
     */
    public function chain(File $file, ?User $viewer): Collection
    {
        $members = $this->walkBack($file)->reverse()->values();

        foreach ($this->walkForward($file) as $successor) {
            $members->push($successor);
        }

        if ($viewer === null) {
            return $members->filter(
                fn (File $member): bool => $member->isEffectivelyPublic() && ! $member->isExpired()
            )->values();
        }

        $visibleIds = $this->viewable->for($viewer)
            ->whereIn('files.id', $members->pluck('id')->all())
            ->pluck('files.id')
            ->all();

        return $members->filter(
            fn (File $member): bool => in_array($member->id, $visibleIds, true)
        )->values();
    }

    /**
     * Files $actor may pick as $file's original.
     *
     * Staff get their viewable library. A CLIENT GETS THEIR OWN UPLOADS
     * ONLY — deliberately not ViewableFileScope, which includes every file
     * staff shared with them. Since a revision inherits the original's
     * recipients, offering a shared file here would be offering a way to
     * publish an upload to a list the client does not own. The picker
     * agreeing with FilePolicy::setVersion is a courtesy; that policy is
     * the control.
     *
     * $file is null in the upload flow, where the original is chosen
     * before the row exists.
     *
     * @return Collection<int, File>
     */
    public function candidates(?File $file, User $actor, string $search = '', int $limit = 25): Collection
    {
        return $this->candidateQuery($file, $actor)
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('original_name', 'like', '%'.$search.'%')
            ))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * One candidate by id, or null — the same rule as candidates(), for a
     * caller that already has an id (the upload flow, the API).
     *
     * Shares candidateQuery() rather than filtering a candidates() result,
     * so resolving an id never depends on that id happening to fall inside
     * the list's limit.
     */
    public function resolveCandidate(?File $file, User $actor, int $previousFileId): ?File
    {
        return $this->candidateQuery($file, $actor)->whereKey($previousFileId)->first();
    }

    /**
     * @return Builder<File>
     */
    private function candidateQuery(?File $file, User $actor): Builder
    {
        $query = $actor->isStaff()
            ? $this->viewable->for($actor)
            : File::query()->where('uploaded_by', $actor->id)->notExpired();

        // Already revised by something: the unique index would refuse it.
        //
        // Except when that something is $file itself. Without this exemption
        // a file's *current* original is not resolvable, so re-submitting an
        // existing link 404s instead of being the no-op link() is careful to
        // be — which an API client retrying a request will do.
        $query->where(function (Builder $eligible) use ($file): void {
            $eligible->whereDoesntHave('nextVersion');

            if ($file !== null) {
                $eligible->orWhereHas('nextVersion', fn (Builder $successor) => $successor->whereKey($file->id));
            }
        });

        if ($file !== null) {
            // Neither itself nor anything downstream of it — both would be
            // rejected by guardAgainstCycle().
            $query->whereKeyNot($file->id)
                ->whereNotIn('files.id', $this->walkForward($file)->pluck('id')->all());
        }

        return $query;
    }

    /**
     * The recipients link() would move from $file onto $previous's root.
     */
    public function previewLink(File $file, File $previous): LinkPreview
    {
        $rootId = $previous->version_root_id ?? $previous->id;

        $moving = $file->assignments()->get();

        if ($moving->isEmpty()) {
            return new LinkPreview;
        }

        $existing = FileAssignment::query()->where('file_id', $rootId)->get()
            ->map(fn (FileAssignment $a): string => $a->assignable_type.':'.$a->assignable_id)
            ->all();

        $clients = [];
        $groups = [];

        foreach ($moving as $assignment) {
            if (in_array($assignment->assignable_type.':'.$assignment->assignable_id, $existing, true)) {
                continue;
            }

            $target = $assignment->assignable;

            if ($target instanceof User) {
                $clients[] = $target->name;
            } elseif ($target instanceof Group) {
                $groups[] = $target->name;
            }
        }

        return new LinkPreview($clients, $groups);
    }

    /**
     * Stamp version_root_id across the chain containing $anyFileIdInChain.
     *
     * Idempotent, and the repair path for `versioning:check --repair`:
     * it derives every root from previous_file_id alone, so a table whose
     * version_root_id column has drifted is fixed by running it.
     */
    public function recomputeRoots(?int $anyFileIdInChain): void
    {
        if ($anyFileIdInChain === null) {
            return;
        }

        $member = File::withTrashed()->find($anyFileIdInChain);

        if ($member === null) {
            return;
        }

        $head = $this->walkBack($member)->last();
        assert($head instanceof File);

        File::withTrashed()->whereKey($head->id)->update(['version_root_id' => null]);

        foreach ($this->walkForward($head) as $successor) {
            File::withTrashed()->whereKey($successor->id)->update(['version_root_id' => $head->id]);
        }
    }

    /**
     * Repair the chain around a file being deleted. Called from
     * File::booted(); not user-initiated, so it authorizes nothing.
     *
     * Heals rather than breaks: deleting v2 out of v1 <- v2 <- v3 leaves
     * v1 <- v3, which is true — v3 does revise v1. The alternative (null
     * out the successor's pointer) would silently amputate a history that
     * is still accurate.
     *
     * Two things make this load-bearing rather than tidying:
     *
     *  - A trashed row still occupies its original's unique
     *    previous_file_id slot. Without this, deleting a revision means the
     *    original can never be revised again, and the error message names a
     *    file the staffer can no longer see.
     *  - Deleting a ROOT would strand every revision below it with no
     *    recipients at all, since revisions hold none of their own. The
     *    promoted revision inherits the root's assignment rows here, inside
     *    the same transaction, before the row goes.
     */
    public function detachOnDelete(File $file): void
    {
        $previousId = $file->previous_file_id;

        $successor = File::withTrashed()->where('previous_file_id', $file->id)->first();

        if ($previousId === null && $successor === null) {
            return;
        }

        DB::transaction(function () use ($file, $previousId, $successor): void {
            // A root going means its successor becomes the new root and
            // must take the recipients with it, or the whole chain below
            // loses its audience. ($successor is non-null here: the
            // both-null case already returned above.)
            if ($previousId === null) {
                $this->copyAssignmentsFrom($file->id, $successor);
            }

            // MUST come first: this frees $file's claim on $previousId's
            // unique slot before $successor below tries to take it.
            File::withTrashed()->whereKey($file->id)
                ->update(['previous_file_id' => null, 'version_root_id' => null]);

            if ($successor !== null) {
                // Literal ids, never a subquery over `files` — MySQL
                // cannot reopen a table it is updating.
                File::withTrashed()->whereKey($successor->id)
                    ->update(['previous_file_id' => $previousId]);

                $this->recomputeRoots($successor->id);
            }
        });
    }

    /**
     * Every guard link() applies, in the order that produces the most
     * useful message.
     *
     * @throws ValidationException
     */
    private function guard(File $file, File $previous, User $actor): void
    {
        if ($previous->is($file)) {
            throw ValidationException::withMessages([
                'previous_file_id' => __('A file cannot be a revision of itself.'),
            ]);
        }

        // Both ends, always. The subject alone is not enough: linking
        // widens the ORIGINAL's audience, so reaching one is reaching both.
        Gate::forUser($actor)->authorize('setVersion', $file);
        Gate::forUser($actor)->authorize('setVersion', $previous);

        // A client links at upload time, when the file has no recipients.
        // Allowing it later would let them push their own file's audience
        // onto an original — the mirror of the escalation setVersion
        // blocks, so it is closed here rather than left to the UI.
        if (! $actor->isStaff() && $file->assignments()->exists()) {
            throw ValidationException::withMessages([
                'previous_file_id' => __('This file is already shared with other people, so it can\'t be marked as a new version. Ask your contact to do it for you.'),
            ]);
        }

        // Queried rather than read off $previous->nextVersion: a model
        // instance caches a relation once loaded, so a caller reusing one
        // across two link() calls would see a stale null here and get the
        // race-condition message from the unique index instead of this
        // one, which names the file in the way.
        $existing = File::query()->where('previous_file_id', $previous->id)->first();

        if ($existing !== null && ! $existing->is($file)) {
            throw ValidationException::withMessages([
                'previous_file_id' => __('That file has already been revised by ":name". Remove that link first.', ['name' => $existing->name]),
            ]);
        }

        $this->guardAgainstCycle($file, $previous);
    }

    /**
     * previous_file_id is unique and single-valued, so in-degree and
     * out-degree are both at most one and every component of the graph is
     * a path or a cycle. Pointing $file at $previous therefore closes a
     * cycle exactly when $file is already reachable by walking back from
     * $previous — which makes this walk exact, not a heuristic.
     *
     * A PHP loop rather than a recursive CTE on purpose: chains are a
     * handful of rows, and this stays identical on the sqlite test
     * database and on MySQL/Postgres.
     *
     * @throws ValidationException
     */
    private function guardAgainstCycle(File $file, File $previous): void
    {
        $steps = 0;

        foreach ($this->walkBack($previous) as $ancestor) {
            if ($ancestor->is($file)) {
                throw ValidationException::withMessages([
                    'previous_file_id' => __('That file is already part of this file\'s version history.'),
                ]);
            }

            $steps++;
        }

        // $steps counts $previous and everything it revises, so the chain
        // this link would produce is one longer.
        if ($steps >= self::MAX_CHAIN) {
            throw ValidationException::withMessages([
                'previous_file_id' => __('This version history is too long. Remove an older link first.'),
            ]);
        }
    }

    /**
     * $file's own recipients become the root's, then stop being $file's.
     */
    private function moveAssignmentsToRoot(File $file, int $rootId): void
    {
        $assignments = $file->assignments()->with('assignable')->get();

        foreach ($assignments as $assignment) {
            $target = $assignment->assignable;

            if (! $target instanceof User && ! $target instanceof Group) {
                continue;
            }

            $root = File::query()->find($rootId);

            if ($root === null) {
                continue;
            }

            // firstOrCreate inside, so a target the root already has is a
            // no-op rather than a duplicate notification.
            $this->sharing->assign($root, $target, $target->name);
        }

        $file->assignments()->delete();
    }

    /**
     * Give $target its own copy of $sourceFileId's recipients — used when a
     * file stops inheriting them (unlink) or when a promoted revision has
     * to take over as root (detachOnDelete).
     *
     * A direct insert rather than FileSharing::assign(): nobody is gaining
     * access here, the same people keep exactly what they had, so a
     * "shared with you" notification would be a lie.
     */
    private function copyAssignmentsFrom(int $sourceFileId, File $target): void
    {
        if ($sourceFileId === $target->id) {
            return;
        }

        $rows = FileAssignment::query()->where('file_id', $sourceFileId)->get();

        foreach ($rows as $row) {
            FileAssignment::query()->firstOrCreate([
                'file_id' => $target->id,
                'assignable_type' => $row->assignable_type,
                'assignable_id' => $row->assignable_id,
            ]);
        }
    }

    /**
     * $file and every file it revises, nearest first.
     *
     * withTrashed() throughout: these walks maintain structure, and a
     * trashed row still holds a pointer that has to be accounted for.
     * Display paths use the relations instead, which hide trashed rows.
     *
     * @return Collection<int, File>
     */
    private function walkBack(File $file): Collection
    {
        /** @var Collection<int, File> $chain */
        $chain = new Collection([$file]);
        $cursor = $file;
        $steps = 0;

        while ($cursor->previous_file_id !== null && ++$steps <= self::MAX_CHAIN) {
            $cursor = File::withTrashed()->find($cursor->previous_file_id);

            if ($cursor === null) {
                break;
            }

            $chain->push($cursor);
        }

        return $chain;
    }

    /**
     * Every file that revises $file, transitively, nearest first.
     *
     * @return Collection<int, File>
     */
    private function walkForward(File $file): Collection
    {
        /** @var Collection<int, File> $chain */
        $chain = new Collection;
        $cursor = $file;
        $steps = 0;

        while (++$steps <= self::MAX_CHAIN) {
            $next = File::withTrashed()->where('previous_file_id', $cursor->id)->first();

            if ($next === null) {
                break;
            }

            $chain->push($next);
            $cursor = $next;
        }

        return $chain;
    }
}
