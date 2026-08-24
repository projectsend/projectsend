<?php

declare(strict_types=1);

namespace App\Modules\Files\Delivery;

use App\Modules\Files\Models\File;
use App\Support\ContentDisposition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * A stored file's own bytes, put on the wire for whichever disk it lives
 * on.
 *
 * Every route that hands over a file reaches this after authorizing in
 * its own way — a policy, a share token, a public-listing check. It
 * authorizes nothing itself, and deliberately knows nothing about who is
 * asking. The one thing it knows is the thing each caller kept getting
 * wrong on its own: that `$file->disk` decides how the bytes travel.
 *
 * Local disk: X-Accel-Redirect, so nginx streams the file and PHP never
 * touches the bytes. Anything else — S3, GCS and friends — gets a
 * short-lived presigned URL carrying the disposition, which an object
 * store ranges just as well.
 *
 * That distinction matters most for inline(): a <video> seeking through
 * an hour of footage issues a long tail of Range requests, and nginx's
 * static handler answers those with 206s on its own, dropping the
 * Content-Length below in favour of the range it actually served.
 *
 * Callers of inline() must have established that the mime type is
 * inline-safe first; PreviewKind is the allowlist, and the reason there
 * is one.
 */
class StoredFileResponse
{
    /** Shown in place — a preview. */
    public function inline(File $file): Response|RedirectResponse
    {
        return $this->make($file, ContentDisposition::inline($file->original_name));
    }

    /** Handed over — a download. */
    public function attachment(File $file): Response|RedirectResponse
    {
        return $this->make($file, ContentDisposition::attachment($file->original_name));
    }

    private function make(File $file, string $disposition): Response|RedirectResponse
    {
        if ($file->disk !== 'files') {
            $url = Storage::disk($file->disk)->temporaryUrl(
                $file->path,
                now()->addHour(),
                ['ResponseContentDisposition' => $disposition],
            );

            return redirect()->away($url);
        }

        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$file->path,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) $file->size,
        ]);
    }
}
