<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Models\User;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * This app's first real background Job (every other queued unit of work
 * so far is a Notification) — building a zip can take a while for a
 * large folder, so it always runs async regardless of size, matching
 * this app's already-established "everything async" queue philosophy.
 *
 * Loose files are placed at the zip root; each selected folder becomes
 * a subfolder preserving its own internal subtree structure. The zip's
 * own output always lands on the local "files" disk regardless of where
 * its source files live (matches how thumbnails always cache locally).
 * A source file on the local disk is added via its real path (fast path,
 * ZipArchive::addFile); a source file on any other disk (the community
 * external storage module's "files_external") is stream-copied to a temp
 * file first, since ZipArchive can't add a non-local stream directly.
 */
class BuildZipDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A zip build is not usefully retryable — a source file that went
     * missing mid-build, or an allowance spent while the job waited, makes
     * a second attempt no likelier to succeed — so a failure is recorded
     * once and surfaced to the requester rather than silently retried.
     */
    public int $tries = 1;

    /**
     * Building the archive is the whole job, and a large selection (up to
     * ZipDownloadsController::MAX_FILES sources, some stream-copied from a
     * remote disk) runs well past the queue worker's default 60s timeout.
     * Without room the worker kills the process mid-build before the catch
     * can run, stranding the row as PENDING forever; failed() is the
     * backstop for when the kill lands anyway.
     */
    public int $timeout = 3600;

    public function __construct(
        private readonly int $zipDownloadId,
    ) {
        // Its own queue, because $timeout is an hour and every shipped
        // topology runs one worker: on the default queue a single large
        // build holds up every notification email behind it. Set in the
        // constructor rather than at the dispatch site so a second caller
        // cannot forget it.
        //
        // A worker has to be listening. The images run a second one; a
        // manual install whose worker command still says plain
        // `queue:work` consumes `default` only, so INSTALL.md documents
        // `--queue=default,zips` for the single-worker case — see the
        // upgrade note in CHANGELOG.md.
        $this->onQueue('zips');
    }

    public function handle(): void
    {
        $zipDownload = ZipDownload::query()->find($this->zipDownloadId);

        if ($zipDownload === null) {
            return;
        }

        // Stamped before any of the work, because the only thing this is
        // for is telling "a worker has this in hand" apart from "nobody
        // is listening to the zips queue". A build that waits and never
        // starts is the second, which is what a manual install whose
        // worker command predates that queue looks like from here. See
        // StalledZipBuilds.
        $zipDownload->forceFill(['started_at' => now()])->save();

        // Authorization is re-derived here, against the requester, rather
        // than trusted from what the controller stored: a folder id only
        // says "this user may open this folder", never "this user may read
        // everything inside it" (an expired file is invisible to a client
        // even in a folder they hold). Re-deriving also closes the gap
        // between request time and run time — access can be revoked while
        // the job sits in the queue.
        $requester = User::query()->find($zipDownload->requested_by);

        if ($requester === null) {
            $zipDownload->update([
                'status' => ZipDownload::STATUS_FAILED,
                'error' => 'The requesting account no longer exists.',
            ]);

            return;
        }

        $visible = app(ViewableFileScope::class)->for($requester);
        $allowance = app(DownloadAllowance::class);

        try {
            $relativePath = 'zips/'.$zipDownload->id.'.zip';
            Storage::disk('files')->makeDirectory('zips');
            $absolutePath = Storage::disk('files')->path($relativePath);

            $zip = new ZipArchive;
            if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create the zip archive.');
            }

            $usedNames = [];
            $totalSize = 0;
            $tempFiles = [];
            $skipped = [];

            // Collected rather than derived from $usedNames, which also
            // holds the folder entry names. Recording the ids, not just a
            // count, is what lets the download action log exactly what it
            // hands over instead of resolving the selection a second time
            // against a scope that may have moved since.
            //
            // Keyed by id rather than appended to a list, because it is
            // also what keeps a file out of the archive twice. The loose
            // selection cannot repeat itself — one whereIn on the primary
            // key — but a selected folder can hold a file that was also
            // named loosely, and the cap is 10000 sources, so the check
            // has to be a lookup rather than a scan.
            $added = [];

            foreach ((clone $visible)->whereIn('id', $zipDownload->file_ids)->get() as $file) {
                // Re-checked here for the same reason visibility is: the
                // archive is built some time after it was asked for, and
                // the allowance may have been spent in between.
                if (! $allowance->allows($file, $requester)) {
                    $skipped[] = ['id' => $file->id, 'name' => $file->name];

                    continue;
                }

                $entryName = $this->dedupeName($usedNames, $this->entrySegment($file->original_name));
                $zip->addFile($this->localPathFor($file, $tempFiles), $entryName);
                $totalSize += $file->size;
                $added[$file->id] = true;
            }

            foreach ($this->outermostFolders($zipDownload->folder_ids) as $folder) {
                $totalSize += $this->addFolder($zip, $folder, $requester, $usedNames, $tempFiles, $visible, $skipped, $added);
            }

            // Re-checked here, not only in ZipDownloadsController: the
            // selection is re-derived at build time, so a folder that grew
            // while the job sat in the queue could otherwise fill the disk
            // with an archive nobody is allowed to ask for. unchangeAll()
            // drops every pending entry, so close() writes nothing rather
            // than writing an archive we would delete a line later.
            $maxBytes = (int) app(Settings::class)->get(Setting::MaxZipDownloadSizeMb) * 1024 * 1024;

            if ($maxBytes > 0 && $totalSize > $maxBytes) {
                $zip->unchangeAll();
                @$zip->close();

                foreach ($tempFiles as $tempFile) {
                    @unlink($tempFile);
                }

                $this->fail($zipDownload, $relativePath, 'The selection grew past the maximum zip download size before the archive could be built.', $skipped);

                return;
            }

            // ZipArchive defers every write to close(): a source file
            // deleted after its addFile() (a concurrent staff delete runs
            // FileDiskCleanup at once) or a full disk only surfaces here,
            // as a false return. Its low-level warning is silenced (as with
            // the @unlink cleanup below) so the return value is the signal
            // we act on, deterministically, rather than an exception whose
            // firing depends on the error_reporting level. An archive that
            // ended up with no entries is the same kind of non-result —
            // libzip writes no file for one at all, even though close()
            // still returns true. Either way there is nothing to serve, so
            // the row must not be marked ready over a missing or empty
            // archive: the download controller would X-Accel a file that
            // isn't there.
            $written = @$zip->close();

            foreach ($tempFiles as $tempFile) {
                @unlink($tempFile);
            }

            if ($written !== true || $added === []) {
                if ($written !== true) {
                    // What the requester sees stays generic: a libzip
                    // string means nothing to them and can name a server
                    // path. An operator needs the opposite — "disk full"
                    // and "the source file vanished" are different
                    // problems — so the reason goes to the log instead.
                    Log::error('A zip download could not be written.', [
                        'zip_download_id' => $zipDownload->id,
                        'reason' => $zip->getStatusString(),
                    ]);
                }

                // Nothing written is told apart from nothing added, and
                // "every file had already been downloaded as often as it
                // was meant to be" from "there was nothing left to send".
                // They are different problems for the person who asked,
                // and fail() carries the skipped list either way, so
                // "which files?" stays answerable from the row.
                $this->fail($zipDownload, $relativePath, match (true) {
                    $written !== true => 'The zip archive could not be written.',
                    $skipped !== [] => 'Every selected file had already reached its download limit.',
                    default => 'None of the selected files were available to add to the archive.',
                }, $skipped);

                return;
            }

            $zipDownload->update([
                'status' => ZipDownload::STATUS_READY,
                'path' => $relativePath,
                'total_size' => $totalSize,
                'file_count' => count($added),
                'contained_file_ids' => array_keys($added),
                'skipped_files' => $skipped === [] ? null : $skipped,
            ]);
        } catch (Throwable $e) {
            foreach ($tempFiles ?? [] as $tempFile) {
                @unlink($tempFile);
            }

            $zipDownload->update([
                'status' => ZipDownload::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * One way out for every build that cannot produce an archive: drop
     * whatever landed on disk, and leave the row saying what happened and
     * what was left out.
     *
     * @param  list<array{id: int, name: string}>  $skipped
     */
    private function fail(ZipDownload $zipDownload, string $relativePath, string $message, array $skipped): void
    {
        Storage::disk('files')->delete($relativePath);

        $zipDownload->update([
            'status' => ZipDownload::STATUS_FAILED,
            'error' => $message,
            'skipped_files' => $skipped === [] ? null : $skipped,
        ]);
    }

    /**
     * Runs when the queue gives up on the job — most importantly when the
     * worker kills it for exceeding $timeout, which skips handle()'s own
     * catch and would otherwise leave the row PENDING forever, polled by
     * the frontend with no end. Only a row still pending is touched: a
     * build that already resolved itself (ready or failed) is left alone.
     */
    public function failed(?Throwable $exception): void
    {
        $zipDownload = ZipDownload::query()->find($this->zipDownloadId);

        if ($zipDownload === null || $zipDownload->status !== ZipDownload::STATUS_PENDING) {
            return;
        }

        $zipDownload->update([
            'status' => ZipDownload::STATUS_FAILED,
            'error' => 'The zip archive could not be built.',
        ]);
    }

    /**
     * A local-disk file is added by its real path (fast path). Anything
     * else gets stream-copied to a temp file first — ZipArchive::addFile()
     * needs a real local path, it can't read a remote stream directly.
     * Temp files are collected and cleaned up by the caller once the zip
     * is closed (ZipArchive keeps the path open until then).
     *
     * @param  array<int, string>  $tempFiles
     */
    private function localPathFor(File $file, array &$tempFiles): string
    {
        if ($file->disk === 'files') {
            return Storage::disk('files')->path($file->path);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'zip-src-');

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

        $tempFiles[] = $tempPath;

        return $tempPath;
    }

    /**
     * @param  array<int, string>  $usedNames
     * @param  array<int, string>  $tempFiles
     * @param  Builder<File>  $visible  every file the requester may read
     * @param  list<array{id: int, name: string}>  $skipped
     * @param  array<int, true>  $added  every file really written into the archive, keyed by id
     */
    private function addFolder(ZipArchive $zip, Folder $folder, User $requester, array &$usedNames, array &$tempFiles, Builder $visible, array &$skipped, array &$added): int
    {
        $allowance = app(DownloadAllowance::class);

        $subtreeIds = $folder->subtreeFolderIds();
        /** @var Collection<int, Folder> $foldersById */
        $foldersById = Folder::query()->whereIn('id', $subtreeIds)->get()->keyBy('id');
        $rootEntryName = $this->dedupeName($usedNames, $this->entrySegment($folder->name));
        $totalSize = 0;

        foreach ((clone $visible)->whereIn('folder_id', $subtreeIds)->get() as $file) {
            // Already in the archive under another part of the selection —
            // named loosely, or inside a folder selected before this one.
            // Skipped rather than added again: a second entry is a second
            // copy of the same bytes, and delivery charges one download
            // however many copies went out.
            if (isset($added[$file->id])) {
                continue;
            }

            // Holding the folder does not entitle the requester to a file
            // inside it whose own allowance is spent — same reason the
            // per-file visibility filter is re-derived rather than
            // inherited from the folder.
            if (! $allowance->allows($file, $requester)) {
                $skipped[] = ['id' => $file->id, 'name' => $file->name];

                continue;
            }

            $relative = $this->relativeFolderPath($folder, $foldersById, $file->folder_id);
            $entryPath = implode('/', array_filter([$rootEntryName, $relative, $this->entrySegment($file->original_name)], fn (string $segment): bool => $segment !== ''));
            $entryPath = $this->dedupeName($usedNames, $entryPath);
            $zip->addFile($this->localPathFor($file, $tempFiles), $entryPath);
            $totalSize += $file->size;
            $added[$file->id] = true;
        }

        return $totalSize;
    }

    /**
     * The selected folders with the redundant ones dropped: one that sits
     * inside another selected folder is already covered by it.
     *
     * Zipping both would reach the same file twice, and which of the two
     * paths the surviving entry ended up under would be decided by
     * whatever order the database returned the rows in. Keeping the outer
     * folder keeps the fuller path — Reports/Q1/report.pdf rather than
     * Q1/report.pdf — and gives the same archive on every run.
     *
     * @param  list<int>  $folderIds
     * @return Collection<int, Folder>
     */
    private function outermostFolders(array $folderIds): Collection
    {
        /** @var Collection<int, Folder> $folders */
        $folders = Folder::query()->whereIn('id', $folderIds)->orderBy('id')->get();

        return $folders
            ->reject(fn (Folder $folder): bool => $folders->contains(
                fn (Folder $other): bool => $other->id !== $folder->id
                    && str_starts_with($folder->path, $other->subtreePathPrefix()),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, Folder>  $foldersById  Every folder in the root's subtree, keyed by id.
     */
    private function relativeFolderPath(Folder $root, Collection $foldersById, ?int $folderId): string
    {
        if ($folderId === null || $folderId === $root->id) {
            return '';
        }

        $segments = [];
        $current = $foldersById->get($folderId);

        while ($current !== null && $current->id !== $root->id) {
            array_unshift($segments, $this->entrySegment($current->name));
            $current = $current->parent_id !== null ? $foldersById->get($current->parent_id) : null;
        }

        return implode('/', $segments);
    }

    /**
     * One safe path component for the archive. An uploader chooses
     * original_name freely (validated only for length), so it must never be
     * able to steer where an entry lands: `../../.bashrc` as a zip entry
     * name is the classic "zip slip", and while modern extractors refuse
     * traversal entries, this app should not be the one producing them.
     * Directory separators are stripped, not escaped, since a filename is
     * a single component by definition.
     */
    private function entrySegment(string $name): string
    {
        $name = str_replace(['/', '\\', "\0"], '_', $name);
        $name = trim(preg_replace('/^\.+/', '', $name) ?? $name);

        return $name === '' ? 'file' : $name;
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    private function dedupeName(array &$usedNames, string $path): string
    {
        if (! in_array($path, $usedNames, true)) {
            $usedNames[] = $path;

            return $path;
        }

        $info = pathinfo($path);
        $dir = isset($info['dirname']) && $info['dirname'] !== '.' ? $info['dirname'].'/' : '';
        $filename = $info['filename'];
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';

        $i = 2;
        do {
            $candidate = "{$dir}{$filename} ({$i}){$extension}";
            $i++;
        } while (in_array($candidate, $usedNames, true));

        $usedNames[] = $candidate;

        return $candidate;
    }
}
