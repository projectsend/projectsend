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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Authorized downloads without the bytes ever traversing PHP: the app
 * checks the policy, and StoredFileResponse answers with either an
 * X-Accel-Redirect for nginx to stream from the protected location
 * (brief §3) or a presigned URL when the file lives on external storage,
 * since nginx has no way to serve bytes it doesn't have on disk.
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
