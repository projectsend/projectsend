<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Files\Jobs\ScanFileJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanningConfig;
use App\Modules\Files\Scanning\ScanStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sends files back to the scanner: the ones still waiting, the ones that
 * went through unscanned because it was down, and — when asked — the
 * library that was already here before any of this existed.
 *
 * Hourly rather than daily. A file stuck pending is a file nobody can
 * download, and an installation set to hold has no other way forward
 * once its worker restarted and the job with it.
 */
class ScanFilesCommand extends Command
{
    protected $signature = 'projectsend:scan-files
        {--existing : also work through files that were never scanned because scanning was off}';

    protected $description = 'Scan files that are waiting, were missed, or were never checked (runs hourly)';

    public function handle(ScanningConfig $config): int
    {
        if (! $config->enabled()) {
            $this->info('Virus scanning is switched off.');

            return self::SUCCESS;
        }

        $waiting = $this->dispatchFor(File::query()->where('scan_status', ScanStatus::Pending));

        // Allowed through while the scanner was unreachable. Now that it
        // may be back, they are asked again — a file found infected at
        // this point is quarantined like any other, and its quarantine
        // notice says it was available in the meantime.
        $missed = $this->dispatchFor(
            File::query()
                ->where('scan_status', ScanStatus::NotScanned)
                ->where('scan_note', NotScannedReason::ScannerUnavailable->value)
        );

        $this->info("Re-queued {$waiting} waiting file(s) and {$missed} that were missed while the scanner was down.");

        if ($this->option('existing')) {
            // Paced, because this can be a whole library at once and the
            // scanner is also serving today's uploads. An hour's worth per
            // run, since that is how often this command runs.
            $limit = $config->existingScanRatePerMinute() * 60;

            $old = $this->dispatchFor(
                File::query()
                    ->where('scan_status', ScanStatus::NotScanned)
                    ->where('scan_note', NotScannedReason::BeforeScanning->value),
                $limit,
            );

            $this->info("Queued {$old} file(s) that had never been scanned.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  Builder<File>  $query
     */
    private function dispatchFor(Builder $query, ?int $limit = null): int
    {
        if ($limit !== null) {
            $query->limit($limit);
        }

        $ids = $query->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            // Back to pending first: the job only acts on a pending file,
            // which is what stops two runs of this command from scanning
            // the same file twice.
            File::query()->whereKey($id)->update(['scan_status' => ScanStatus::Pending->value, 'scan_note' => null]);

            ScanFileJob::dispatch((int) $id);
        }

        return $ids->count();
    }
}
