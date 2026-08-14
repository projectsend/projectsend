<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Support\ContentDisposition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

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
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

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

        abort_if($fileCount === 0, 422, __('The selected folders are empty.'));
        abort_if($fileCount > self::MAX_FILES, 422, __('Too many files selected. Choose a smaller selection and try again.'));

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
            $this->logContainedDownloads($zipDownload, $user);
            $zipDownload->forceFill(['delivered_at' => now()])->save();
        }

        $size = Storage::disk('files')->size($path);

        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$path,
            'Content-Type' => 'application/zip',
            'Content-Disposition' => ContentDisposition::attachment($this->filenameFor($zipDownload)),
            'Content-Length' => (string) $size,
        ]);
    }

    /**
     * Every file actually bundled gets a FileDownloaded entry — otherwise
     * a file's download history/count would silently miss zip downloads.
     */
    private function logContainedDownloads(ZipDownload $zipDownload, User $requester): void
    {
        // Same per-file filter the job used to decide what actually went
        // into the archive, so the log records what was really downloaded
        // rather than everything that happened to sit in the folder.
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

        foreach ((clone $visible)->whereIn('id', $fileIds->unique())->whereNotIn('id', $skipped)->get() as $file) {
            $this->activity->log(Action::FileDownloaded, subject: $file);
        }
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
