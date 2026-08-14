<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Comments\Http\Resources\Api\FileCommentResource;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Comments on a file.
 *
 * Comments are scoped to who is asking, exactly as they are in the
 * application: a token acts as its owner, so a token belonging to a
 * client-scoped staff member returns that member's threads and no others.
 * Listing a file you cannot see returns 403, not an empty list.
 */
class FileCommentsController extends Controller
{
    public function __construct(
        private readonly FileComments $comments,
        private readonly VisibleCommentScope $scope,
    ) {}

    /**
     * List a file's comments.
     *
     * Returns the comments the authenticated token's owner may read,
     * oldest first. A comment left by a visitor and not yet approved is
     * included only for a token whose owner may moderate.
     */
    public function index(Request $request, File $file): AnonymousResourceCollection
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);

        $comments = $this->scope->for($viewer, $file)
            ->with(['author', 'clientContext'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return FileCommentResource::collection($comments);
    }

    /**
     * Post a comment on a file.
     *
     * `visibility` is one of `only_me` (a private note), `staff_only` (the
     * team, no client), `clients` (**the team and** every client the file
     * is shared with — the name says who it adds, since staff can already
     * see everything on a file they can open) or `everyone` (all of the
     * above plus anyone who opens the file without logging in).
     * Which of them are available depends on the installation's comment
     * settings and on whether the file is publicly visible; asking for one
     * that is not available returns 403.
     *
     * `reply_to` is the id of a comment being answered. A reply inherits
     * that comment's audience, which is the only way a comment becomes
     * addressed to one client rather than all of them — there is no field
     * that names a client directly. An id this token cannot already read
     * is ignored, and the comment is posted as a fresh one.
     */
    public function store(Request $request, File $file): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::enum(CommentVisibility::class)],
            'reply_to' => ['nullable', 'integer'],
        ]);

        $replyTo = isset($validated['reply_to'])
            ? $this->scope->for($viewer, $file)->whereKey($validated['reply_to'])->first()
            : null;

        $comment = $this->comments->post(
            $file,
            $viewer,
            CommentVisibility::from($validated['visibility']),
            $validated['body'],
            $replyTo,
        );

        return (new FileCommentResource($comment->load(['author', 'clientContext'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Edit a comment.
     *
     * Only its own author may, and only within the installation's editing
     * window. Moderation rights do not extend to rewriting somebody
     * else's words.
     */
    public function update(Request $request, FileComment $comment): FileCommentResource
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('update', $comment);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->comments->edit($comment, $validated['body']);

        return new FileCommentResource($comment->fresh()?->load(['author', 'clientContext']) ?? $comment);
    }

    /**
     * Delete a comment.
     *
     * Its author may, within the editing window; a moderator may at any
     * time. The comment is soft-deleted, so a later dispute is not left
     * with a hole where the conversation was.
     */
    public function destroy(Request $request, FileComment $comment): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('delete', $comment);

        $this->comments->remove($comment);

        return response()->json(status: 204);
    }
}
