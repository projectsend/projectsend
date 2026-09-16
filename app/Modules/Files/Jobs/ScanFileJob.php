<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\ScanningConfig;
use App\Modules\Files\Scanning\ScanOutcome;
use App\Modules\Files\Scanning\ScanPolicy;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\ScanVerdict;
use App\Modules\Files\Scanning\VirusScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads one file to the scanner and records what comes back.
 *
 * On its own queue (`scans`) with its own worker, for the reason
 * BuildZipDownloadJob has one: a 5 GB file streaming to a scanner would
 * otherwise sit in front of every notification email on the default
 * queue.
 *
 * Retries are about the scanner being down, not about the file. While it
 * is unreachable the job puts itself back with a growing delay, and only
 * once this installation's patience runs out does the configured policy
 * decide the file's fate. An installation set to "hold" never runs out:
 * the file stays pending and ScanFilesCommand keeps this job coming back.
 */
class ScanFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Unlimited attempts, bounded by time instead — see retryUntil(). A
     * fixed count would give up on a scanner that is merely being
     * restarted, and the file would be decided by a timeout rather than
     * by the policy.
     */
    public int $tries = 0;

    public function __construct(
        public readonly int $fileId,
    ) {
        $this->onQueue('scans');
    }

    /**
     * A day. Long enough that an overnight outage is survived by a
     * "hold" installation, short enough that a job for a file somebody
     * deleted does not live forever.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(
        VirusScanner $scanner,
        ScanPolicy $policy,
        ScanningConfig $config,
    ): void {
        $file = File::query()->find($this->fileId);

        // Deleted while it waited, or already decided by an earlier run
        // (this job is dispatched from an upload and from the hourly
        // sweep, and both can land on the same file).
        if ($file === null || $file->scan_status !== ScanStatus::Pending) {
            return;
        }

        if (! $config->enabled()) {
            $policy->markNeverScanned($file);

            return;
        }

        // A file identical to one already quarantined needs no second
        // opinion, and asking for one would send the same malware past
        // the scanner again. Checksums are already computed at upload.
        $known = File::query()
            ->where('checksum', $file->checksum)
            ->where('scan_status', ScanStatus::Infected)
            ->whereKeyNot($file->id)
            ->first();

        if ($known !== null) {
            $policy->record($file, ScanVerdict::infected((string) $known->scan_note));

            return;
        }

        $verdict = $this->read($file, $scanner);

        if ($verdict->outcome === ScanOutcome::Unavailable && $this->keepWaiting($file, $config)) {
            $file->forceFill(['scan_attempts' => $file->scan_attempts + 1])->save();

            // 30 seconds, then a minute, then two, up to five. Long
            // enough not to hammer a scanner that is starting up; short
            // enough that a brief blip does not hold an upload for the
            // whole patience window.
            $this->release(min(300, 30 * (2 ** min(4, $file->scan_attempts))));

            return;
        }

        $policy->record($file, $verdict);
    }

    /**
     * Whether the file should wait rather than be decided now.
     *
     * "Hold" waits forever, by design. Otherwise the wait is measured
     * from when the file was stored, not from this attempt: what the
     * setting promises is that nobody's upload sits unavailable for
     * longer than that, however many times the job has run.
     */
    private function keepWaiting(File $file, ScanningConfig $config): bool
    {
        if ($config->holdsWhileUnavailable()) {
            return true;
        }

        $storedAt = $file->created_at ?? now();

        return $storedAt->copy()->addMinutes($config->unavailableWaitMinutes())->isFuture();
    }

    private function read(File $file, VirusScanner $scanner): ScanVerdict
    {
        try {
            $stream = Storage::disk($file->disk)->readStream($file->path);
        } catch (Throwable $e) {
            $stream = null;
            Log::warning("Could not open file {$file->id} for scanning: ".$e->getMessage());
        }

        if ($stream === null) {
            // Not the scanner's fault and not a verdict about the file:
            // treated as "could not be checked", so the installation's
            // own policy decides, rather than calling a file nobody read
            // clean.
            return ScanVerdict::unavailable(__('The file could not be read from storage.'));
        }

        try {
            return $scanner->scan($stream, $file->size);
        } finally {
            fclose($stream);
        }
    }
}
