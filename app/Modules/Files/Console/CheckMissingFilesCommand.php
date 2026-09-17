<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\MissingFileScanner;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanningConfig;
use App\Modules\Files\Scanning\ScanStatus;
use Illuminate\Console\Command;

/**
 * Checks daily that the files this installation lists are actually there.
 *
 * Its own command rather than part of scanning, because a missing file is
 * not a virus question and an installation with no scanner has exactly
 * the same problem. It was only ever noticed when something tried to read
 * the bytes — a download, or a scan — which means the first person to
 * find out was a client clicking a link.
 *
 * Recovery is part of the job: storage comes back, and a library that
 * kept insisting every file was gone would be its own bug.
 */
class CheckMissingFilesCommand extends Command
{
    protected $signature = 'projectsend:check-missing-files';

    protected $description = 'Check that every file in the database is still on disk (runs daily)';

    public function handle(MissingFileScanner $scanner, ActivityLogger $activity, ScanningConfig $scanning): int
    {
        $gone = $scanner->scan();
        $newlyGone = 0;

        foreach (array_chunk($gone, 200) as $chunk) {
            foreach (File::query()->whereIn('id', $chunk)->where('scan_status', '!=', ScanStatus::Missing)->get() as $file) {
                // Stamped like any other verdict: this is the moment the
                // file was last looked at, and without it a missing file
                // never appears in the Activity list — which is exactly
                // where somebody watching would look for it.
                $file->forceFill([
                    'scan_status' => ScanStatus::Missing,
                    'scan_note' => null,
                    'scanned_at' => now(),
                ])->save();

                $activity->logSystem(Action::FileMissing, ['id' => $file->id, 'name' => $file->name]);
                $newlyGone++;
            }
        }

        $back = $scanner->recovered();

        foreach (array_chunk($back, 200) as $chunk) {
            // Back to the start rather than to whatever it was before:
            // nothing here knows what the scanner had decided about bytes
            // that have since been away, and re-checking them is cheap
            // next to trusting a verdict about a file that left.
            File::query()->whereIn('id', $chunk)->update($scanning->enabled()
                ? ['scan_status' => ScanStatus::Pending->value, 'scan_note' => null]
                : ['scan_status' => ScanStatus::NotScanned->value, 'scan_note' => NotScannedReason::BeforeScanning->value]);
        }

        $this->info(sprintf(
            '%d file(s) are missing from storage (%d newly), %d came back.',
            count($gone),
            $newlyGone,
            count($back),
        ));

        return self::SUCCESS;
    }
}
