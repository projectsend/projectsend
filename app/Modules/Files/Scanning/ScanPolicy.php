<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * What a verdict means for a file, on this installation.
 *
 * The scanner answers a question of fact — clean, infected, could not
 * open it, did not answer. Three of those four are only half an answer:
 * whether a file nobody could check may be handed to a client is a
 * decision about somebody's business, not about the file, so it is a
 * setting and it is applied here. Keeping that split is why ClamAvScanner
 * knows nothing about settings and this class knows nothing about
 * sockets.
 *
 * Every write to a file's scan columns goes through this class. They are
 * not fillable and nothing else sets them.
 */
class ScanPolicy
{
    public function __construct(
        private readonly ScanningConfig $config,
        private readonly FileAvailability $availability,
        private readonly ActivityLogger $activity,
        private readonly QuarantineNotifier $notifier,
    ) {}

    /**
     * Record a verdict, and return the state the file ended up in.
     *
     * Returns null when the verdict was "the scanner did not answer" and
     * this installation waits: nothing is written, the file stays
     * pending, and the caller retries.
     */
    public function record(File $file, ScanVerdict $verdict): ?ScanStatus
    {
        return match ($verdict->outcome) {
            ScanOutcome::Clean => $this->settle($file, ScanStatus::Clean, null, $verdict->engine),
            ScanOutcome::Infected => $this->quarantine($file, $verdict->detail ?? 'unknown', $verdict->engine),
            ScanOutcome::TooLarge => $this->unscannable($file, NotScannedReason::TooLarge, $verdict->engine),
            ScanOutcome::Encrypted => $this->unscannable($file, NotScannedReason::Encrypted, $verdict->engine),
            ScanOutcome::Unreadable => $this->missing($file),
            ScanOutcome::Unavailable => $this->unavailable($file, $verdict->detail),
        };
    }

    /**
     * The file existed before there was a scanner, or scanning is off.
     * Not a verdict, so it is never logged: nothing happened to this
     * file, it simply was never looked at.
     */
    public function markNeverScanned(File $file): void
    {
        $file->forceFill([
            'scan_status' => ScanStatus::NotScanned,
            'scan_note' => NotScannedReason::BeforeScanning->value,
        ])->save();
    }

    /**
     * A threat was found. The bytes stay — a scanner can be wrong, and an
     * administrator may release it — but nothing may reach them, and the
     * thumbnails already rendered from this file have to go: they are
     * derived from the same bytes and are served by their own routes.
     */
    private function quarantine(File $file, string $threat, ?string $engine): ScanStatus
    {
        $wasAvailable = $this->availability->isAvailable($file);

        $file->forceFill(['scan_was_available' => $wasAvailable])->save();

        $this->settle($file, ScanStatus::Infected, $threat, $engine);
        $this->purgeRenditions($file);

        $this->activity->logSystem(Action::FileQuarantined, [
            'id' => $file->id,
            'name' => $file->name,
            'threat' => $threat,
            // Said out loud because it changes what an administrator has
            // to do: a file that was downloadable while it waited for a
            // scanner may already be on somebody's machine, and its
            // download history is the only way to know.
            'was_available' => $wasAvailable,
        ]);

        $this->notifier->quarantined($file, $threat);

        return ScanStatus::Infected;
    }

    /**
     * The row is here and the bytes are not.
     *
     * Not a scanning verdict at all, and deliberately not run through the
     * unscannable policy: "allow files nobody could scan" is a decision
     * about risk, and there is no risk in a file that cannot be served.
     * What there is, is a problem somebody has to look at — see
     * MissingFileScanner and the Files → Missing screen.
     */
    private function missing(File $file): ScanStatus
    {
        $this->settle($file, ScanStatus::Missing, null, null);

        return ScanStatus::Missing;
    }

    /** The scanner could not open the file: too large, or encrypted. */
    private function unscannable(File $file, NotScannedReason $reason, ?string $engine): ScanStatus
    {
        if ($this->config->blocksUnscannable()) {
            $this->settle($file, ScanStatus::UnscannableBlocked, $reason->value, $engine);
            $this->purgeRenditions($file);

            $this->activity->logSystem(Action::FileQuarantined, [
                'id' => $file->id,
                'name' => $file->name,
                'threat' => $reason->label(),
                'was_available' => false,
            ]);

            $this->notifier->quarantined($file, $reason->label());

            return ScanStatus::UnscannableBlocked;
        }

        return $this->letThrough($file, $reason, $engine);
    }

    /** The scanner never answered. Either wait for it, or let the file go. */
    private function unavailable(File $file, ?string $reason): ?ScanStatus
    {
        if ($this->config->holdsWhileUnavailable()) {
            return null;
        }

        return $this->letThrough($file, NotScannedReason::ScannerUnavailable, null);
    }

    /**
     * Allowed through without being checked.
     *
     * Always logged, even though it is the configured behaviour: this is
     * the state where the installation looks protected and is not, and
     * the log is what makes "we were unprotected between these two dates"
     * answerable afterwards.
     */
    private function letThrough(File $file, NotScannedReason $reason, ?string $engine): ScanStatus
    {
        $this->settle($file, ScanStatus::NotScanned, $reason->value, $engine);

        $this->activity->logSystem(Action::FileNotScanned, [
            'id' => $file->id,
            'name' => $file->name,
            'reason' => $reason->value,
        ]);

        return ScanStatus::NotScanned;
    }

    private function settle(File $file, ScanStatus $status, ?string $note, ?string $engine): ScanStatus
    {
        // Asked before the write, because what the announcement means is
        // "this can now be had" and a file that could already be had has
        // nothing to announce. Without this, re-scanning a file that went
        // out unscanned would tell its recipients a second time.
        $wasAvailable = $this->availability->isAvailable($file);

        $file->forceFill([
            'scan_status' => $status,
            'scan_note' => $note,
            'scanned_at' => now(),
            'scan_engine' => $engine,
        ])->save();

        if (! $wasAvailable) {
            $this->availability->markAvailable($file);
        }

        return $status;
    }

    /**
     * Thumbnails and previews are cached copies of the same bytes, served
     * by routes of their own, so a quarantined file with a rendition
     * already on disk would still be showing part of itself.
     */
    private function purgeRenditions(File $file): void
    {
        foreach (ThumbnailGenerator::pathsFor($file->id, $file->mime_type) as $path) {
            Storage::disk('files')->delete($path);
        }
    }
}
