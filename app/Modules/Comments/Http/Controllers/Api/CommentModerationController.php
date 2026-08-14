<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Comments\FileComments;
use App\Modules\Comments\Http\Resources\Api\FileCommentResource;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\StaffLibraryScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Comments from visitors, waiting for a decision.
 *
 * Only anonymous comments ever land here — an account's comment is
 * published the moment it is written.
 *
 * This exists so the two halves of moderation match. Deleting somebody
 * else's comment was already reachable through `DELETE /comments/{id}`,
 * which runs the same policy the web does; approving one was not, so a
 * token carried the destructive half and none of the constructive one, and
 * `moderate_comments` was not selectable as an ability at all because no
 * route named it. Both are fixed by these two endpoints existing.
 */
class CommentModerationController extends Controller
{
    public function __construct(
        private readonly FileComments $comments,
        private readonly StaffLibraryScope $library,
    ) {}

    /**
     * List comments awaiting approval.
     *
     * Scoped by the same library boundary as everything else: a
     * client-scoped token sees pending comments only on files its owner
     * could already open. Oldest first, so working through the list means
     * working through the backlog.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('moderate', FileComment::class);

        $pending = FileComment::query()
            ->whereNull('approved_at')
            ->whereIn('file_id', $this->library->files($viewer)->select('id'))
            ->with(['author', 'clientContext'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return FileCommentResource::collection($pending);
    }

    /**
     * Approve a comment left by a visitor.
     *
     * Nobody can see it until this happens. Approving an already-approved
     * comment changes nothing and announces nothing, so a retried request
     * is safe — which matters more here than on the web, where a human does
     * not retry automatically.
     */
    public function approve(Request $request, FileComment $comment): FileCommentResource
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('moderate', FileComment::class);
        // Moderation rights are not a way around the library boundary.
        abort_unless($this->library->allowsFile($viewer, $comment->file), 403);

        $this->comments->approve($comment, $viewer);

        return new FileCommentResource($comment->fresh()?->load(['author', 'clientContext']) ?? $comment);
    }
}
