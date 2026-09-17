<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\ScanStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Rows whose bytes are not there — the other half of the orphan problem.
 *
 * OrphanFileScanner finds bytes with no row. This finds rows with no
 * bytes, which is the worse of the two: an orphan is disk space nobody
 * claimed, while this is a file somebody was told they had. It happens
 * when a volume is remounted somewhere else, when a backup is restored
 * without its storage, when an external bucket is swapped, and when
 * something deleted the bytes behind the application's back.
 *
 * Asked by listing each disk once and comparing, rather than by asking
 * "does this exist?" per row: on object storage that would be one request
 * per file, and a library of ten thousand files would answer with ten
 * thousand HEADs every day.
 *
 * Only disks this installation can enumerate are checked, which is the
 * same set the orphan scan walks. A row on any other disk is left alone
 * rather than declared missing — never having looked is not evidence.
 */
class MissingFileScanner
{
    public function __construct(
        private readonly OrphanFileScanner $orphans,
    ) {}

    /**
     * The files whose bytes are gone, as ids.
     *
     * @return list<int>
     */
    public function scan(): array
    {
        $missing = [];

        foreach (array_keys($this->orphans->scannedDisks()) as $diskName) {
            $onDisk = array_flip(Storage::disk($diskName)->allFiles());

            File::query()
                ->where('disk', $diskName)
                ->select(['id', 'path'])
                ->chunkById(500, function ($files) use ($onDisk, &$missing): void {
                    foreach ($files as $file) {
                        if (! isset($onDisk[$file->path])) {
                            $missing[] = (int) $file->id;
                        }
                    }
                });
        }

        return $missing;
    }

    /**
     * Files this installation has marked missing whose bytes are back.
     *
     * A remount, a restored backup, a bucket reconnected. Recovery is not
     * optional politeness: the alternative is an administrator who fixed
     * their storage and still has a library that says every file is gone.
     *
     * @return list<int>
     */
    public function recovered(): array
    {
        $back = [];

        foreach (array_keys($this->orphans->scannedDisks()) as $diskName) {
            $onDisk = array_flip(Storage::disk($diskName)->allFiles());

            File::query()
                ->where('disk', $diskName)
                ->where('scan_status', ScanStatus::Missing)
                ->select(['id', 'path'])
                ->chunkById(500, function ($files) use ($onDisk, &$back): void {
                    foreach ($files as $file) {
                        if (isset($onDisk[$file->path])) {
                            $back[] = (int) $file->id;
                        }
                    }
                });
        }

        return $back;
    }
}
