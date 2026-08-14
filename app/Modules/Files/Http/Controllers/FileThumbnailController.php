<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\Events\ResolvingImageRendering;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use App\Support\ContentDisposition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Two inline (never `attachment`) views of a file, same X-Accel-Redirect
 * pattern as FileDownloadController: a bounded thumbnail for listing rows,
 * and a larger view opened in a new tab when a thumbnail is clicked.
 * `thumbnail()` stays unlogged — it fires automatically as an `<img src>`
 * for every row on every listing render, not a deliberate action, and
 * logging it would flood the activity log with non-events. `preview()`
 * logs Action::FilePreviewed, since a user explicitly chose to view the
 * file's contents — a real, audit-worthy action, just not a "download."
 *
 * SECURITY: both methods serve bytes inline, from this app's own origin,
 * with the File's stored mime type — so both are restricted to
 * ThumbnailGenerator::SUPPORTED_MIME_TYPES, the raster formats this app
 * renders itself. That list is the allowlist; nothing else is ever served
 * inline. Do NOT widen it to text/html, image/svg+xml, or anything else a
 * browser executes script from, and do not reach for the upload
 * allowed-extensions setting as a substitute: that setting matches on the
 * *extension*, while mime_type is detected from the *bytes*
 * (ChunkedUploadsController::complete), so a .txt holding HTML is stored
 * as text/html and would render as a page here. Serving a file inline as
 * a type the browser executes is same-origin script execution with the
 * viewer's session.
 *
 * Renditions always cache on the local "files" disk regardless of where
 * the source file lives — they're a derived artifact, not the original,
 * so there's no reason to push them to external storage too. Generating
 * one from a source on a non-local disk needs a temp local copy first,
 * since ThumbnailGenerator needs a real path to read from.
 *
 * Both methods cache one file per ImageAudience, because both routes are
 * reached by the staff file manager and the client portal alike and a
 * RenderingImage listener may render the two differently.
 */
class FileThumbnailController extends Controller
{
    public function __construct(
        private readonly ThumbnailGenerator $thumbnails,
        private readonly ActivityLogger $activity,
        private readonly DownloadAllowance $allowance,
    ) {}

    public function thumbnail(Request $request, File $file): Response
    {
        Gate::authorize('view', $file);

        // This one route serves both the staff file manager and the client
        // portal — the same URL, told apart only by who is asking. A client
        // and a staff member looking at the same file get different cached
        // bytes; see ImageAudience.
        $audience = ImageAudience::forViewer($request->user());

        $path = $this->render($file, $audience, ImageRendition::Thumbnail);

        abort_if($path === null, 404);

        return $this->serve($file, $path);
    }

    /**
     * A file opened to be looked at.
     *
     * A preview is not the file — it is a rendered view of it, which is
     * why it may be decorated at all. But rendering one is expensive
     * (decoding and re-encoding a full-size photograph) where serving the
     * stored bytes is nearly free, so core only pays that cost when a
     * listener says this particular viewer must be served a rendering:
     * ResolvingImageRendering asks, and defaults to no. On an
     * installation that watermarks, a client gets a bounded, watermarked
     * render and staff get the original; on one that does not, everyone
     * gets exactly what this endpoint has always returned.
     */
    public function preview(Request $request, File $file): Response|RedirectResponse
    {
        Gate::authorize('view', $file);

        // Only types this app renders itself may be served inline; anything
        // else is a download, not a preview. See the class docblock — the
        // stored mime type is sniffed from the bytes, so an allowed
        // extension is not evidence of a safe-to-render payload.
        abort_unless(ThumbnailGenerator::supports($file->mime_type), 404);

        // A preview is not counted as a download, but it is refused once
        // the download limit is spent — because unless a listener asks
        // for a rendering (nothing does by default), the branches below
        // serve the *original bytes* at full size. Without this a cap
        // would be one URL away from meaningless for every image on the
        // install. thumbnail() needs no such guard: a 300px rendition is
        // not the file.
        abort_unless($this->allowance->allows($file, $request->user()), 403);

        $this->activity->log(Action::FilePreviewed, subject: $file);

        $audience = ImageAudience::forViewer($request->user());

        $decision = new ResolvingImageRendering($audience, ImageRendition::Preview, $file->mime_type);
        Event::dispatch($decision);

        if ($decision->required) {
            $path = $this->render($file, $audience, ImageRendition::Preview);

            abort_if($path === null, 404);

            return $this->serve($file, $path);
        }

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

    /**
     * The cached rendition's path on the local disk, generating it first
     * if this is the first time anyone has asked for it. Null only when
     * the mime type has no rendition at all.
     */
    private function render(File $file, ImageAudience $audience, ImageRendition $rendition): ?string
    {
        $path = ThumbnailGenerator::pathFor($file->id, $file->mime_type, $audience, $rendition);

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('files');

        if ($disk->exists($path)) {
            return $path;
        }

        $disk->makeDirectory(dirname($path));
        $sourcePath = $this->localSourcePathFor($file);

        try {
            $this->thumbnails->generate($sourcePath, $disk->path($path), $file->mime_type, $audience, $rendition);
        } finally {
            if ($file->disk !== 'files') {
                @unlink($sourcePath);
            }
        }

        return $path;
    }

    private function serve(File $file, string $path): Response
    {
        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$path,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ContentDisposition::inline($file->original_name),
        ]);
    }

    /**
     * A local-disk file's real path (fast path). Anything else is
     * stream-copied to a temp file first — the caller unlinks it once
     * rendering is done.
     */
    private function localSourcePathFor(File $file): string
    {
        if ($file->disk === 'files') {
            return Storage::disk('files')->path($file->path);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'thumb-src-');

        if ($tempPath === false) {
            throw new \RuntimeException('Could not create a temp file for '.$file->original_name);
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);
        $out = fopen($tempPath, 'wb');

        if ($stream === null || $out === false) {
            throw new \RuntimeException('Could not read '.$file->original_name.' from its storage disk.');
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $tempPath;
    }
}
