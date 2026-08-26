<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentPresenter;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every comment on the installation, in one place.
 *
 * This was a queue of visitors' comments awaiting approval and nothing
 * else, which meant the only way to find a comment somebody had already
 * posted was to remember which file it was on. Approving is now one of the
 * things this screen does rather than the only one.
 *
 * **It is not a way around the visibility model.** The list comes from
 * VisibleCommentScope::across(), the same predicate a single file's thread
 * uses, so a moderator sees here exactly what they would see by opening
 * each file in turn: no other person's "only me" note, and no conversation
 * belonging to a client they are not assigned to.
 */
class CommentsController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly FileComments $comments,
        private readonly CommentPresenter $presenter,
        private readonly VisibleCommentScope $scope,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);

        $filters = $this->validatedFilters($request);

        $comments = $this->filteredQuery($filters, $viewer)
            ->with(['file', 'author', 'clientContext'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('comments/index', [
            'entries' => $comments->getCollection()
                ->map(fn (FileComment $comment): array => $this->present($viewer, $comment))
                ->values()->all(),
            'pagination' => [
                'page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'prev' => $comments->previousPageUrl(),
                'next' => $comments->nextPageUrl(),
                'total' => $comments->total(),
            ],
            'filters' => $filters,
            // The count of everything waiting, not of what this page shows:
            // it is what the "Awaiting approval" filter would find, and the
            // reason to reach for it.
            'pending_total' => $this->scope->pendingTotal($viewer),
            'visibilities' => array_map(fn (CommentVisibility $visibility): array => [
                'value' => $visibility->value,
                'label' => $visibility->label(forStaff: true),
            ], CommentVisibility::cases()),
        ]);
    }

    /**
     * One action, two representations: this screen posts and expects to
     * land back on itself, the details panel fetches and expects the
     * thread it is showing. A second route for the same decision would be
     * a second place for it to drift.
     */
    public function approve(Request $request, FileComment $comment): RedirectResponse|JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        // Moderation rights are not a way around the library boundary; the
        // policy weighs the comment's file, so name the comment.
        Gate::forUser($viewer)->authorize('moderate', $comment);

        $this->comments->approve($comment, $viewer);

        if ($request->wantsJson()) {
            return response()->json($this->presenter->thread($viewer, $comment->file));
        }

        return back()->with('success', __('Comment approved.'));
    }

    public function destroy(Request $request, FileComment $comment): RedirectResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('delete', $comment);

        $this->comments->remove($comment);

        return back()->with('success', __('Comment deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $viewer, FileComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author_name' => $comment->authorName(),
            'author_type' => $this->authorType($comment),
            'visibility' => $comment->visibility->value,
            'visibility_label' => $comment->visibility->label(forStaff: true),
            // Whose conversation this is, when it is one client's. Staff
            // only by construction — this whole screen is.
            'conversation' => $comment->clientContext?->name,
            'pending' => $comment->isPending(),
            // Only ever recorded for a visitor, and the one handle that
            // makes repeat spam actionable.
            'ip_address' => $comment->ip_address,
            'created_at' => $comment->created_at?->toIso8601String(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'file' => ['id' => $comment->file->id, 'name' => $comment->file->name],
            // Deep-links to the file's own Comments tab: deciding about one
            // comment usually means reading the rest of the conversation.
            'file_url' => route('files.edit', ['file' => $comment->file, 'tab' => 'comments'], false),
            'can_approve' => $comment->isPending(),
            'can_delete' => Gate::forUser($viewer)->allows('delete', $comment),
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

    /**
     * @return array{status: ?string, visibility: ?string, author_type: ?string, search: ?string, file: ?string, from: ?string, to: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved'])],
            'visibility' => ['nullable', Rule::enum(CommentVisibility::class)],
            'author_type' => ['nullable', Rule::in(['staff', 'client', 'guest'])],
            'search' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'visibility' => $validated['visibility'] ?? null,
            'author_type' => $validated['author_type'] ?? null,
            'search' => $validated['search'] ?? null,
            'file' => $validated['file'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * @param  array{status: ?string, visibility: ?string, author_type: ?string, search: ?string, file: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<FileComment>
     */
    private function filteredQuery(array $filters, User $viewer): Builder
    {
        $timezone = $this->timezones->resolve($viewer);

        return $this->scope->across($viewer)
            ->when($filters['status'], fn (Builder $query, string $status) => $status === 'pending'
                ? $query->whereNull('approved_at')
                : $query->whereNotNull('approved_at'))
            ->when($filters['visibility'], fn (Builder $query, string $visibility) => $query->where('visibility', $visibility))
            ->when($filters['author_type'], fn (Builder $query, string $type) => match ($type) {
                'guest' => $query->whereNull('author_id'),
                default => $query->whereHas('author', fn (Builder $author) => $author->where('type', $type)),
            })
            // One box over the two things a comment is remembered by: what
            // it said, and who said it. A visitor's name lives on the
            // comment itself, an account's on the account, so both are
            // asked — searching only one of them finds half the comments
            // and gives no sign that it did.
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $any) => $any
                ->where('body', 'like', "%{$search}%")
                ->orWhere('guest_name', 'like', "%{$search}%")
                ->orWhereHas('author', fn (Builder $author) => $author->where('name', 'like', "%{$search}%"))))
            ->when($filters['file'], fn (Builder $query, string $file) => $query
                ->whereHas('file', fn (Builder $on) => $on->where('name', 'like', "%{$file}%")))
            // The viewer's calendar day, not the server's — see LocalDay.
            ->when(
                $filters['from'] !== null ? LocalDay::start($filters['from'], $timezone) : null,
                fn (Builder $query, Carbon $from) => $query->where('file_comments.created_at', '>=', $from),
            )
            ->when(
                $filters['to'] !== null ? LocalDay::end($filters['to'], $timezone) : null,
                fn (Builder $query, Carbon $to) => $query->where('file_comments.created_at', '<=', $to),
            )
            // Held comments first whatever the sort: they are the ones with
            // a decision waiting on them, and the reason somebody opens this
            // screen without a filter in mind.
            ->orderByRaw('approved_at is null desc')
            ->orderByDesc('file_comments.created_at')
            ->orderByDesc('file_comments.id');
    }
}
