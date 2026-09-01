<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Delivery\FileDelivery;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\ContentDisposition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * A folder's "Download as zip" button and the file listing's multi-select
 * bar both post here — one endpoint, resolved into a set of files either
 * way. Always queued (BuildZipDownloadJob), never built synchronously:
 * matches this app's established "everything async" queue philosophy and
 * avoids an unbounded web request for a large folder.
 */
class ZipDownloadsController extends Controller
{
    /**
     * A generous but real cap — abuse/foot-gun guard, not a tunable Setting.
     */
    private const MAX_FILES = 10000;

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly ViewableFileScope $viewable,
        private readonly DownloadAllowance $allowance,
        private readonly Settings $settings,
        private readonly FileDelivery $delivery,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        // One build at a time per requester. A zip holds the queue worker
        // for as long as it takes to write, and everything else — every
        // notification email — waits behind it, so a queue of them from
        // one person is everyone else's outage. An hour old is treated as
        // abandoned rather than in progress: BuildZipDownloadJob::failed()
        // resolves a row the worker gave up on, but a worker killed hard
        // enough never runs it, and nobody should be locked out forever by
        // a row nothing will ever finish.
        $inFlight = ZipDownload::query()
            ->where('requested_by', $user->id)
            ->where('status', ZipDownload::STATUS_PENDING)
            ->where('created_at', '>', now()->subHour())
            ->exists();

        abort_if($inFlight, 429, __('A zip download is already being prepared. Wait for that one to finish before starting another.'));

        $validated = $request->validate([
            'file_ids' => ['array'],
            'file_ids.*' => ['integer'],
            'folder_ids' => ['array'],
            'folder_ids.*' => ['integer'],
        ]);

        $requestedFileIds = array_map('intval', $validated['file_ids'] ?? []);
        $requestedFolderIds = array_map('intval', $validated['folder_ids'] ?? []);

        abort_if($requestedFileIds === [] && $requestedFolderIds === [], 422, __('Select at least one file or folder.'));

        // Silently drop anything the requester isn't allowed to see —
        // never reveal that a hidden item exists, matching this app's
        // existing listing/visibility conventions.
        $files = File::query()->whereIn('id', $requestedFileIds)->get()
            ->filter(fn (File $file): bool => Gate::forUser($user)->allows('view', $file));

        $folders = Folder::query()->whereIn('id', $requestedFolderIds)->get()
            ->filter(fn (Folder $folder): bool => $user->isClient()
                ? Folder::query()->whereKey($folder->id)->visibleToClient($user)->exists()
                : Gate::forUser($user)->allows('view', $folder));

        abort_if($files->isEmpty() && $folders->isEmpty(), 422, __('None of the selected items could be found.'));

        // A file whose download limit is spent is visible but not
        // takeable, so it drops out here rather than at the Gate above.
        // Told apart from "not found" deliberately: the difference
        // between a file that isn't there and one they have already had
        // as many times as they were meant to is the whole point of not
        // hiding exhausted files.
        $selected = $files->count();
        $files = $files->filter(fn (File $file): bool => $this->allowance->allows($file, $user));

        abort_if(
            $files->isEmpty() && $folders->isEmpty() && $selected > 0,
            422,
            __('Those files have reached their download limit.'),
        );

        // Holding a folder is not the same as being able to read everything
        // in it, so the count uses the same per-file filter the job applies
        // when it actually builds the archive — otherwise the MAX_FILES cap
        // and the "empty folder" check below would both be measuring a set
        // larger than what the user will receive.
        $visible = $this->viewable->for($user);

        $fileCount = $files->count() + $folders->sum(
            fn (Folder $folder): int => (clone $visible)->whereIn('folder_id', $folder->subtreeFolderIds())->count(),
        );

        // Measured the same way, and deliberately without the allowance
        // filter the loose-file branch applies: a folder's total can only
        // come out at or above what the archive will really weigh, and an
        // over-estimate is the safe direction for a cap.
        $totalSize = (int) $files->sum('size') + (int) $folders->sum(
            fn (Folder $folder): int => (int) (clone $visible)->whereIn('folder_id', $folder->subtreeFolderIds())->sum('size'),
        );

        abort_if($fileCount === 0, 422, __('The selected folders are empty.'));
        abort_if($fileCount > self::MAX_FILES, 422, __('Too many files selected. Choose a smaller selection and try again.'));

        // Bytes, not file count, are what a build costs — worker time, the
        // temp copies a remote disk needs, and the archive on disk. The
        // message names both numbers because "too big" without them leaves
        // someone guessing how much to deselect.
        $maxBytes = (int) $this->settings->get(Setting::MaxZipDownloadSizeMb) * 1024 * 1024;

        abort_if(
            $maxBytes > 0 && $totalSize > $maxBytes,
            422,
            __('That selection is :size. Zip downloads are limited to :limit — select fewer files and try again.', [
                'size' => Number::fileSize($totalSize, precision: 1),
                'limit' => Number::fileSize($maxBytes),
            ]),
        );

        $zipDownload = ZipDownload::query()->create([
            'requested_by' => $user->id,
            'status' => ZipDownload::STATUS_PENDING,
            'file_ids' => $files->pluck('id')->values()->all(),
            'folder_ids' => $folders->pluck('id')->values()->all(),
            'file_count' => $fileCount,
        ]);

        BuildZipDownloadJob::dispatch($zipDownload->id);

        return response()->json(['id' => $zipDownload->id]);
    }

    public function show(Request $request, ZipDownload $zipDownload): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $zipDownload->requested_by === $user->id, 404);

        return response()->json([
            'status' => $zipDownload->status,
            'file_count' => $zipDownload->file_count,
            'error' => $zipDownload->status === ZipDownload::STATUS_FAILED ? $zipDownload->error : null,
            'skipped_files' => $zipDownload->skipped_files ?? [],
        ]);
    }

    public function download(Request $request, ZipDownload $zipDownload): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $zipDownload->requested_by === $user->id, 404);
        $path = $zipDownload->path;
        abort_unless($zipDownload->status === ZipDownload::STATUS_READY && $path !== null, 404);

        // Only the first time. Re-fetching one prepared archive is the
        // same delivery, not a fresh download of everything inside it.
        if ($zipDownload->delivered_at === null) {
            $this->deliverOnce($zipDownload, $user);
        }

        $size = Storage::disk('files')->size($path);

        return $this->delivery->serve(
            $path,
            'application/zip',
            ContentDisposition::attachment($this->filenameFor($zipDownload)),
            $size,
        );
    }

    /**
     * Hand the archive over, once: refuse it if anything inside is out of
     * allowance, otherwise count everything it holds as downloaded.
     *
     * This is the only point that spends a download limit, which is why
     * it also has to be the point that enforces it. Building an archive
     * takes nothing, so ordering the same limited file into any number of
     * archives passes every check on the way — store() and the job both
     * look at an allowance nothing has drawn on yet — and collecting them
     * all afterwards would hand over more copies than the limit allows.
     *
     * One refused file refuses the whole delivery, because nothing can be
     * taken out of a finished archive without building it again. Ordering
     * the same selection afresh is the way through: the build leaves the
     * spent file out and names it in skipped_files.
     *
     * An archive from before the job recorded its contents is handed over
     * the way it always was, without this check. Its contents can only be
     * guessed at by resolving the selection again, and guessing is exactly
     * what must not decide a refusal: the same reconstruction both refuses
     * over files the archive does not hold and misses files it does. Those
     * rows stop existing within a day or two of an upgrade, and until then
     * they behave as they did before this change rather than worse.
     */
    private function deliverOnce(ZipDownload $zipDownload, User $requester): void
    {
        $recorded = $zipDownload->contained_file_ids;

        // What the job wrote down, read back as it stands — deliberately
        // not filtered by what the requester may see today. The bytes are
        // in the archive already, so a file that has since expired or left
        // their scope is still being given to them, and a count that
        // quietly dropped it would understate what was taken.
        $contained = $recorded === null
            ? $this->resolveSelection($zipDownload, $requester)
            : File::query()->whereIn('id', $recorded)->get();

        abort_if(
            $recorded !== null
                && $contained->contains(fn (File $file): bool => ! $this->allowance->allows($file, $requester)),
            403,
            __('Those files have reached their download limit.'),
        );

        // Atomic, so two fetches arriving together are still one delivery:
        // only the request that actually moves delivered_at logs anything.
        // Same reasoning as the conditional increment guarding a share
        // link's max_downloads in PublicShareController. The other request
        // still receives the archive — that is the re-fetch rule above.
        $claimed = ZipDownload::query()
            ->whereKey($zipDownload->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        // Every file actually bundled gets a FileDownloaded entry —
        // otherwise a file's download history/count would silently miss
        // zip downloads.
        foreach ($contained as $file) {
            $this->activity->log(Action::FileDownloaded, subject: $file);
        }
    }

    /**
     * What an archive built before the job recorded its contents is taken
     * to hold: the selection, resolved again, which is how this worked
     * throughout. Only reachable for rows written by an older release,
     * and PurgeZipDownloadsCommand removes those within a day.
     *
     * @return Collection<int, File>
     */
    private function resolveSelection(ZipDownload $zipDownload, User $requester): Collection
    {
        $visible = $this->viewable->for($requester);
        $fileIds = collect($zipDownload->file_ids);

        foreach ($zipDownload->folder_ids as $folderId) {
            $folder = Folder::query()->find($folderId);

            if ($folder !== null) {
                $fileIds = $fileIds->merge((clone $visible)->whereIn('folder_id', $folder->subtreeFolderIds())->pluck('id'));
            }
        }

        // Whatever the job left out is not in the archive and must not
        // be logged as delivered — otherwise a file refused for reaching
        // its limit would be recorded as downloaded again, pushing it
        // further past it.
        $skipped = collect($zipDownload->skipped_files ?? [])->pluck('id')->all();

        return (clone $visible)->whereIn('id', $fileIds->unique())->whereNotIn('id', $skipped)->get();
    }

    private function filenameFor(ZipDownload $zipDownload): string
    {
        if (count($zipDownload->folder_ids) === 1 && $zipDownload->file_ids === []) {
            $folder = Folder::query()->find($zipDownload->folder_ids[0]);

            if ($folder !== null) {
                // Folder names reach this header too, and a client can name
                // their own folders — same reasoning as
                // StoreUploadedFile::sanitizeFilename(). Quoted-string
                // escaping happens in ContentDisposition, not here.
                return StoreUploadedFile::sanitizeFilename($folder->name).'.zip';
            }
        }

        return 'download.zip';
    }
}
