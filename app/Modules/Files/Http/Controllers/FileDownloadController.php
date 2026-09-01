<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Delivery\StoredFileResponse;
use App\Modules\Files\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorized downloads: the app checks the policy, and StoredFileResponse
 * decides how the bytes travel — a presigned URL when the file lives on
 * external storage, and otherwise whichever local delivery method this
 * installation's web server understands (see FileDelivery). On nginx that
 * is an X-Accel-Redirect and the bytes never traverse PHP at all; on a
 * server with no such header PHP streams them, which is slower and works.
 */
class FileDownloadController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly DownloadAllowance $allowance,
        private readonly StoredFileResponse $bytes,
    ) {}

    public function __invoke(Request $request, File $file): Response|RedirectResponse
    {
        Gate::authorize('view', $file);

        // Separate from the policy on purpose: a spent download limit is
        // not "you may not see this file" — the file stays listed, and
        // the same person may still open its details. It is only the
        // taking of a copy that stops. See DownloadAllowance.
        abort_unless($this->allowance->allows($file, $request->user()), 403);

        $this->activity->log(Action::FileDownloaded, subject: $file);

        return $this->bytes->attachment($file);
    }
}
