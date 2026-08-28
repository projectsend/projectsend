<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Delivery\StoredFileResponse;
use App\Modules\Files\Models\File;
use App\Modules\Files\Preview\PreviewKind;
use App\Modules\Files\Thumbnails\Events\ResolvingImageRendering;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Thumbnails\LocalSourceFile;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\ContentDisposition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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
 * with the File's stored mime type, so both are restricted to an
 * allowlist — but not the same one, because they are asking different
 * questions. `thumbnail()` is bounded by
 * ThumbnailGenerator::SUPPORTED_MIME_TYPES, the raster formats this app
 * decodes and re-encodes itself, since a thumbnail *is* a rendition.
 * `preview()` is bounded by PreviewKind, which additionally admits the
 * video, audio and PDF types a browser plays natively and this app never
 * touches. PreviewKind's docblock carries the rule in full; the short
 * version is that neither list may ever grow a type a browser executes
 * script from, and neither may be derived from the upload
 * allowed-extensions setting, which matches on the *extension* while
 * mime_type is detected from the *bytes*
 * (ChunkedUploadsController::complete).
 *
 * Serving media inline is also why `preview()` logs at most one
 * Action::FilePreviewed per viewer per file per five minutes: a `<video>`
 * seeking through a recording issues a long tail of Range requests
 * against this same URL, and one row each would bury the log under a
 * single deliberate act.
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
        private readonly StoredFileResponse $bytes,
        private readonly LocalSourceFile $source,
        private readonly Settings $settings,
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
     * For an image, a preview is not the file — it is a rendered view of
     * it, which is why it may be decorated at all. But rendering one is
     * expensive (decoding and re-encoding a full-size photograph) where
     * serving the stored bytes is nearly free, so core only pays that
     * cost when a listener says this particular viewer must be served a
     * rendering: ResolvingImageRendering asks, and defaults to no. On an
     * installation that watermarks, a client gets a bounded, watermarked
     * render and staff get the original; on one that does not, everyone
     * gets exactly what this endpoint has always returned.
     *
     * For video, audio and PDF there is no rendering to resolve — this
     * app cannot decode any of them, so it has no rendition to cache, no
     * watermark to stamp, and nothing to ask about. Those go straight to
     * the bytes.
     */
    public function preview(Request $request, File $file): Response|RedirectResponse
    {
        Gate::authorize('view', $file);

        // The inline allowlist. See the class docblock and PreviewKind —
        // the stored mime type is sniffed from the bytes, so an allowed
        // extension is not evidence of a safe-to-render payload.
        $kind = PreviewKind::forMime($file->mime_type);

        abort_if($kind === null, 404);

        // Staff are never gated: this switch exists so an installation can
        // decide what its *clients* may do with a file short of taking it.
        // 404 rather than 403 because with the setting off the endpoint is
        // not a thing that exists for this viewer.
        abort_if(
            $request->user()?->isStaff() !== true && ! $this->settings->get(Setting::ClientsCanPreviewFiles),
            404,
        );

        // A preview is not counted as a download, but it is refused once
        // the download limit is spent — because unless a listener asks
        // for a rendering (nothing does by default, and nothing ever does
        // for media), the branches below serve the *original bytes* at
        // full size. Without this a cap would be one URL away from
        // meaningless for every previewable file on the install.
        // thumbnail() needs no such guard: a 300px rendition is not the
        // file.
        abort_unless($this->allowance->allows($file, $request->user()), 403);

        $this->logPreview($file, $request);

        if ($kind === PreviewKind::Image) {
            $audience = ImageAudience::forViewer($request->user());

            $decision = new ResolvingImageRendering($audience, ImageRendition::Preview, $file->mime_type);
            Event::dispatch($decision);

            if ($decision->required) {
                $path = $this->render($file, $audience, ImageRendition::Preview);

                abort_if($path === null, 404);

                return $this->serve($file, $path);
            }
        }

        return $this->bytes->inline($file);
    }

    /**
     * One log row per viewer per file per five minutes.
     *
     * Watching a video is a single deliberate act that the browser turns
     * into dozens of Range requests against this route, and each one
     * arrives here indistinguishable from someone clicking preview again.
     * Cache::add is the whole mechanism: it writes only if the key is
     * absent, so the first request through the window logs and the rest
     * are silent, without a read-then-write race between two of them.
     *
     * Keyed by viewer, so one client's playback never suppresses another
     * person's preview of the same file. Anonymous viewers do not reach
     * this route at all — see PublicGroupsController::preview.
     */
    private function logPreview(File $file, Request $request): void
    {
        $key = 'file-preview-logged:'.$file->id.':'.($request->user()->id ?? 'guest');

        if (Cache::add($key, true, now()->addMinutes(5))) {
            $this->activity->log(Action::FilePreviewed, subject: $file);
        }
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

        // Existence is the cache, and an empty file is not a rendition: it
        // is what a render that died before writing anything leaves behind,
        // and serving it hands the viewer a broken image for as long as the
        // file lives — nothing invalidates a rendition once it is there.
        // ThumbnailGenerator writes through a temporary file now, so this
        // state can no longer be created here; it can still be inherited
        // from an installation that ran an older version.
        if ($disk->exists($path)) {
            if ($disk->size($path) > 0) {
                return $path;
            }

            $disk->delete($path);
        }

        $disk->makeDirectory(dirname($path));

        $this->source->use($file, fn (string $sourcePath) => $this->thumbnails->generate(
            $sourcePath,
            $disk->path($path),
            $file->mime_type,
            $audience,
            $rendition,
        ));

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
}
