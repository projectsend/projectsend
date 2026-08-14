<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Files\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Where a comment notification lands.
 *
 * A notification type has one url closure for every recipient, but a
 * comment's recipients are staff and clients at once, and they have no
 * page in common — staff open the file's own screen, clients their
 * portal. Resolving that here, per request, keeps the branch in one
 * obvious place instead of teaching the notification centre to care who
 * is reading it.
 */
class CommentDeepLinkController extends Controller
{
    public function __invoke(Request $request, File $file): RedirectResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);
        Gate::forUser($viewer)->authorize('view', $file);

        if ($viewer->isStaff()) {
            // The file's own page, which is also where the activity log's
            // "View" link lands — one destination for "show me this
            // conversation" rather than two. It works whatever page of the
            // library the file happens to be on, which the slide-over did
            // not.
            return redirect()->route('files.edit', [$file, 'tab' => 'comments']);
        }

        // The portal is a list, so this opens the file's own comment panel
        // from its row. A file that is not on the page the client lands on
        // (deep in a folder, or past the first page) simply does not open —
        // they still arrive somewhere sensible, which is why this is a
        // query parameter rather than a promise.
        return redirect()->route('my-files.index', ['comments' => $file->id]);
    }
}
