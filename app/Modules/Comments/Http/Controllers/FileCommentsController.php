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
use App\Modules\Files\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The comment thread as JSON, for both staff and clients — one endpoint,
 * because the two differ only in what the scope returns them, not in
 * anything this controller does.
 *
 * Note what this endpoint will not accept: a client id. Answering one
 * client is `reply_to`, a comment this viewer can already see, so the
 * worst a hand-rolled request can do is answer a conversation it was
 * shown. Nothing here can point a comment at an arbitrary client.
 */
class FileCommentsController extends Controller
{
    public function __construct(
        private readonly FileComments $comments,
        private readonly CommentPresenter $presenter,
        private readonly VisibleCommentScope $scope,
    ) {}

    public function index(Request $request, File $file): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);

        return response()->json($this->payload($viewer, $file));
    }

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

        $this->comments->post(
            $file,
            $viewer,
            CommentVisibility::from($validated['visibility']),
            $validated['body'],
            $this->replyTarget($viewer, $file, $validated['reply_to'] ?? null),
        );

        return response()->json($this->payload($viewer, $file), 201);
    }

    public function update(Request $request, FileComment $comment): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('update', $comment);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->comments->edit($comment, $validated['body']);

        return response()->json($this->payloadAfterChange($viewer, $comment->file));
    }

    public function destroy(Request $request, FileComment $comment): JsonResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('delete', $comment);

        $file = $comment->file;

        $this->comments->remove($comment);

        return response()->json($this->payloadAfterChange($viewer, $file));
    }

    /**
     * The thread for the two routes that bind a file, after their own
     * `view` authorization has passed.
     *
     * @return array<string, mixed>
     */
    private function payload(User $viewer, File $file): array
    {
        return $this->presenter->thread($viewer, $file);
    }

    /**
     * The thread that goes back with a change to one comment.
     *
     * update() and destroy() bind a comment rather than a file, so nothing
     * in the request has established that this viewer may read the file's
     * conversation — only that this one comment is theirs to change.
     * Somebody who commented through the public listing is exactly that
     * person, and refusing them on their own edit would be wrong, so the
     * reading they get back is the one the file's own gate allows them:
     * the public page's, if that is how they arrived.
     *
     * @return array<string, mixed>
     */
    private function payloadAfterChange(User $viewer, File $file): array
    {
        return $this->presenter->thread(
            $viewer,
            $file,
            viewerMaySeeFile: Gate::forUser($viewer)->allows('view', $file),
        );
    }

    /**
     * The comment being answered, resolved through the same scope that
     * decided what this viewer may read. A reply can therefore only ever
     * join a conversation they were already shown — an id they were not
     * given resolves to null and the comment is treated as a fresh one,
     * rather than 403ing on something they cannot be told exists.
     */
    private function replyTarget(User $viewer, File $file, ?int $id): ?FileComment
    {
        if ($id === null) {
            return null;
        }

        return $this->scope->for($viewer, $file)->whereKey($id)->first();
    }
}
