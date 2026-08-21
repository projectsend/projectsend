<?php

declare(strict_types=1);

namespace App\Modules\Files\Delivery;

use App\Modules\Files\Models\File;
use App\Support\ContentDisposition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * A stored file's own bytes, served to be looked at rather than saved.
 *
 * The two preview endpoints — FileThumbnailController::preview for
 * someone signed in, PublicGroupsController::preview for a visitor —
 * reach this after they have each authorized in their own way. It
 * authorizes nothing itself; it only knows how to put bytes on the wire
 * for whichever disk the file lives on.
 *
 * Local disk: X-Accel-Redirect, so nginx streams the file and PHP never
 * touches the bytes. That matters more here than it does for a download,
 * because a <video> seeking through an hour of footage issues a long tail
 * of Range requests; nginx's static handler answers those with 206s on
 * its own, and drops the Content-Length below in favour of the range it
 * actually served. Anything else — S3 and friends — gets a short-lived
 * presigned URL carrying an inline disposition, which the object store
 * ranges just as well.
 *
 * Callers must have established that the mime type is inline-safe first;
 * PreviewKind is the allowlist, and the reason there is one.
 */
class InlineFileResponse
{
    public function make(File $file): Response|RedirectResponse
    {
        if ($file->disk !== 'files') {
            $url = Storage::disk($file->disk)->temporaryUrl(
                $file->path,
                now()->addHour(),
                ['ResponseContentDisposition' => ContentDisposition::inline($file->original_name)],
            );

            return redirect()->away($url);
        }

        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$file->path,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ContentDisposition::inline($file->original_name),
            'Content-Length' => (string) $file->size,
        ]);
    }
}
