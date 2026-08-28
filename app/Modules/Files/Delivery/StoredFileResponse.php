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
 * The two paths are not equally revocable, which is why the lifetimes
 * below differ. X-Accel-Redirect authorises one response: nginx serves
 * these bytes, now, to this request. A presigned URL is a bearer
 * credential — whoever holds it can fetch the file without passing the
 * caller's checks again, and it outlives them: a download cap that is
 * spent in the meantime, an expires_at that falls in between, an
 * assignment that is withdrawn. Nothing here can revoke one, so the only
 * dial is how long it lasts.
 *
 * A download needs to survive being followed, which is a redirect and a
 * request: a minute is generous. A preview is held by the player for as
 * long as somebody watches, and each seek outside the buffer is a fresh
 * Range request against the same URL, so it keeps the hour. That is the
 * trade, stated rather than left in a single number.
 *
 * Callers of inline() must have established that the mime type is
 * inline-safe first; PreviewKind is the allowlist, and the reason there
 * is one.
 */
class StoredFileResponse
{
    /**
     * Long enough for a browser, a download manager or a queued transfer
     * to follow the redirect and start the request. An object store
     * checks the signature when the request arrives, not while it runs,
     * so a transfer that begins inside this window finishes however long
     * it takes.
     */
    private const DOWNLOAD_LINK_SECONDS = 60;

    /**
     * A preview is watched, not fetched: the player holds this URL and
     * issues a Range request every time somebody seeks past the buffer,
     * so it has to outlive the viewing rather than the redirect.
     */
    private const PREVIEW_LINK_SECONDS = 3600;

    /** Shown in place — a preview. */
    public function inline(File $file): Response|RedirectResponse
    {
        return $this->make($file, ContentDisposition::inline($file->original_name), self::PREVIEW_LINK_SECONDS);
    }

    /** Handed over — a download. */
    public function attachment(File $file): Response|RedirectResponse
    {
        return $this->make($file, ContentDisposition::attachment($file->original_name), self::DOWNLOAD_LINK_SECONDS);
    }

    private function make(File $file, string $disposition, int $linkSeconds): Response|RedirectResponse
    {
        if ($file->disk !== 'files') {
            $url = Storage::disk($file->disk)->temporaryUrl(
                $file->path,
                now()->addSeconds($linkSeconds),
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
