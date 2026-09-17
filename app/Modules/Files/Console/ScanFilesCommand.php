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
        {--existing : also work through files that were never scanned because scanning was off}
        {--all : check every file again, whatever it said last}';

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
                ->where('scan_note', NotScannedReason::ScannerUnavailable->value),
            rescan: true,
        );

        $this->info("Re-queued {$waiting} waiting file(s) and {$missed} that were missed while the scanner was down.");

        if ($this->option('all')) {
            // Every file somebody can have today. Not a file waiting for
            // its first verdict, not one with no bytes, and not one in or
            // released from quarantine — a scan is not how a file leaves
            // quarantine, and a release is not undone by one. Files keep
            // their current state, and stay downloadable, until a new
            // verdict arrives.
            $limit = $config->existingScanRatePerMinute() * 60;

            $checked = $this->dispatchFor(
                File::query()->whereIn('scan_status', ScanFileJob::rescannableValues()),
                $limit,
                rescan: true,
            );

            $this->info("Queued {$checked} file(s) to be checked again.");

            return self::SUCCESS;
        }

        if ($this->option('existing')) {
            // Paced, because this can be a whole library at once and the
            // scanner is also serving today's uploads. An hour's worth per
            // run, since that is how often this command runs.
            $limit = $config->existingScanRatePerMinute() * 60;

            $old = $this->dispatchFor(File::query()->neverScanned(), $limit, rescan: true);

            $this->info("Queued {$old} file(s) that had never been scanned.");
        }

        return self::SUCCESS;
    }

    /**
     * Nothing here changes a file's state before the scanner has spoken.
     *
     * An earlier version marked each file pending first, which reads as
     * tidy and is wrong twice over: pending means "withheld", so a
     * backfill would have hidden an entire library from its clients for
     * as long as it ran, and every file would then have been announced to
     * its recipients a second time when it came back. The job knows which
     * state it expects instead — see its $rescan.
     *
     * @param  Builder<File>  $query
     */
    private function dispatchFor(Builder $query, ?int $limit = null, bool $rescan = false): int
    {
        if ($limit !== null) {
            $query->limit($limit);
        }

        $ids = $query->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            ScanFileJob::dispatch((int) $id, $rescan);
        }

        return $ids->count();
    }
}
