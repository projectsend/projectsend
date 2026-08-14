<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Models\User;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function __construct(
        private readonly int $zipDownloadId,
    ) {}

    public function handle(): void
    {
        $zipDownload = ZipDownload::query()->find($this->zipDownloadId);

        if ($zipDownload === null) {
            return;
        }

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

            // Counted rather than derived from $usedNames, which also
            // holds the folder entry names.
            $added = 0;

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
                $added++;
            }

            foreach (Folder::query()->whereIn('id', $zipDownload->folder_ids)->get() as $folder) {
                $totalSize += $this->addFolder($zip, $folder, $requester, $usedNames, $tempFiles, $visible, $skipped, $added);
            }

            $zip->close();

            foreach ($tempFiles as $tempFile) {
                @unlink($tempFile);
            }

            $zipDownload->update([
                'status' => ZipDownload::STATUS_READY,
                'path' => $relativePath,
                'total_size' => $totalSize,
                'file_count' => $added,
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
     */
    private function addFolder(ZipArchive $zip, Folder $folder, User $requester, array &$usedNames, array &$tempFiles, Builder $visible, array &$skipped, int &$added): int
    {
        $allowance = app(DownloadAllowance::class);

        $subtreeIds = $folder->subtreeFolderIds();
        /** @var Collection<int, Folder> $foldersById */
        $foldersById = Folder::query()->whereIn('id', $subtreeIds)->get()->keyBy('id');
        $rootEntryName = $this->dedupeName($usedNames, $this->entrySegment($folder->name));
        $totalSize = 0;

        foreach ((clone $visible)->whereIn('folder_id', $subtreeIds)->get() as $file) {
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
            $added++;
        }

        return $totalSize;
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
