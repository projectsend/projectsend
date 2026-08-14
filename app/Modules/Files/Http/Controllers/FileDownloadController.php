<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Models\File;
use App\Support\ContentDisposition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Authorized downloads without the bytes ever traversing PHP: for a file
 * on the local disk, the app checks the policy and answers with
 * X-Accel-Redirect; nginx streams the file from the protected location
 * (brief §3). The cloud edition swaps this for presigned URLs behind the
 * same route. A file on the community-only external storage disk already
 * gets exactly that — a presigned URL redirect — since nginx has no way
 * to serve bytes it doesn't have on disk.
 */
class FileDownloadController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly DownloadAllowance $allowance,
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

        if ($file->disk !== 'files') {
            $url = Storage::disk($file->disk)->temporaryUrl(
                $file->path,
                now()->addHour(),
                ['ResponseContentDisposition' => ContentDisposition::attachment($file->original_name)],
            );

            return redirect()->away($url);
        }

        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$file->path,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ContentDisposition::attachment($file->original_name),
            'Content-Length' => (string) $file->size,
        ]);
    }
}
