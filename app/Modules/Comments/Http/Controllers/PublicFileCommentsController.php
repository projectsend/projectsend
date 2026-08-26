<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Comments\CommentingRules;
use App\Modules\Comments\CommentPresenter;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Comments\GuestCommentIdentity;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Rules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Comments on a publicly-listed file, for visitors who are not logged in.
 *
 * Separate from FileCommentsController because the gate is different in
 * kind, not degree: there is no account to authorize, so reachability of
 * the *file* is the whole of it, and every comment here is public by
 * construction. Keeping the two apart means the authenticated endpoint
 * never has to reason about a null viewer, and this one can never
 * accidentally serve a thread-scoped comment.
 *
 * A signed-in viewer who lands here is served as themselves — being
 * logged in should not show you less than a stranger sees, and their own
 * comments should be theirs to edit.
 */
class PublicFileCommentsController extends Controller
{
    public function __construct(
        private readonly FileComments $comments,
        private readonly CommentPresenter $presenter,
        private readonly CommentingRules $rules,
        private readonly Settings $settings,
        private readonly GuestCommentIdentity $guests,
    ) {}

    public function index(Request $request, string $publicSlug, File $file): JsonResponse
    {
        $this->guard($publicSlug, $file);

        return response()->json($this->thread($request->user(), $file));
    }

    public function store(Request $request, string $publicSlug, File $file): JsonResponse
    {
        $this->guard($publicSlug, $file);

        $viewer = $request->user();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            // A visitor has no account to take a name from, so they give
            // one. Ignored for a signed-in author, whose name is real.
            'guest_name' => [$viewer === null ? 'required' : 'nullable', 'string', 'max:80'],
            // Accepted and ignored: the shared composer sends the whole
            // form, and a visitor's only possible visibility is Everyone.
            'visibility' => ['nullable', 'string'],
            // Only a visitor is challenged — see CommentingRules. A signed
            // in viewer reaching this endpoint is served as themselves, and
            // proving they are human on a page that knows who they are
            // would be friction with nothing behind it.
            ...($this->rules->captchaRequiredFor($viewer) ? Rules::captcha(CaptchaForm::Comment) : []),
        ]);

        $comment = $this->comments->post(
            $file,
            $viewer,
            CommentVisibility::Everyone,
            $validated['body'],
            null,
            $validated['guest_name'] ?? null,
        );

        // So a visitor keeps seeing their own comment while it waits. The
        // only place this is recorded, because it is the only place a
        // comment is written without an account.
        if ($viewer === null) {
            $this->guests->remember($comment->id);
        }

        return response()->json($this->thread($viewer, $file), 201);
    }

    /**
     * The thread as this endpoint may serve it.
     *
     * guard() establishes the guest half of VisibleCommentScope's
     * precondition — the file is reachable without logging in — and that
     * is the whole of it for a visitor. It says nothing about an account,
     * and handing a signed-in viewer to the authenticated reading anyway
     * is what let any staff account read a public file's StaffOnly notes
     * and any client account read the messages addressed to that file's
     * clients. The file's own gate decides which reading applies; the one
     * it does not admit still reads what a visitor reads plus their own
     * comments, which is what this endpoint has always promised them.
     *
     * @return array<string, mixed>
     */
    private function thread(?User $viewer, File $file): array
    {
        return $this->presenter->thread(
            $viewer,
            $file,
            viewerMaySeeFile: $viewer !== null && Gate::forUser($viewer)->allows('view', $file),
        );
    }

    /**
     * The file must be reachable without logging in, and the public
     * listing itself must be switched on — the same two conditions
     * PublicGroupsController applies before rendering the page this
     * endpoint belongs to. Commenting being configured off 404s rather
     * than returning an empty thread: the endpoint should not exist.
     */
    private function guard(string $publicSlug, File $file): void
    {
        abort_unless($this->settings->get(Setting::PublicListingSlug) === $publicSlug, 404);
        abort_unless($file->isEffectivelyPublic() && ! $file->isExpired(), 404);
        abort_unless($this->rules->enabled(), 404);
    }
}
