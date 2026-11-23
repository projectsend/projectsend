<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Files\Events\FileWasStored;
use Illuminate\Support\Facades\Event;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\ScanningConfig;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Carbon\Carbon;
use App\Modules\Files\Editing\FileExpiry;

/**
 * The single place a stored payload becomes a File record — shared by
 * the plain intake and the chunked-upload completion, so metadata
 * persistence and auditing never diverge.
 */
class StoreUploadedFile
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly ScanningConfig $scanning,
        private readonly Settings $settings,
        private readonly FileExpiry $expiry,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function create(
        User $uploader,
        string $originalName,
        string $path,
        string $mimeType,
        int $size,
        string $checksum,
        ?string $name = null,
        ?string $description = null,
        ?int $folderId = null,
        string $disk = 'files',
        Action $action = Action::FileUploaded,
    ): File {
        $originalName = self::sanitizeFilename($originalName);

        $scanning = $this->scanning->enabled();

        $file = File::query()->create([
            'uploaded_by' => $uploader->id,
            'folder_id' => $folderId,
            'name' => $name !== null && $name !== ''
                ? $name
                : pathinfo($originalName, PATHINFO_FILENAME),
            'description' => $description,
            'original_name' => $originalName,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'size' => $size,
            'checksum' => $checksum,
            'expires_at' => $this->defaultExpiry($uploader, $action),
            // Decided in the same insert as the row rather than a moment
            // later: a file is unavailable from the instant it exists, or
            // there is a window in which it is neither scanned nor
            // withheld. Every upload path arrives here, so this is the
            // only place that has to be right.
            'scan_status' => $scanning ? ScanStatus::Pending : ScanStatus::NotScanned,
            'scan_note' => $scanning ? null : NotScannedReason::BeforeScanning->value,
        ]);

        $this->activity->log($action, $uploader, $file);

        // Every upload path converges here — the chunked flow staff and
        // clients share, and the synchronous POST beside it — so a
        // listener sees each upload once without knowing which route
        // produced it. Dispatched after the row exists, so what it
        // receives is a complete File.
        Event::dispatch(new FileWasStored($file, $uploader));

        return $file;
    }

    /**
     * The installation's default expiry, for a file staff just uploaded.
     *
     * Not for a client's upload: they would lose access to their own file
     * when it ran out, and the setting is about what staff share. Not for
     * an orphan import either: those are often files being restored, and
     * with auto-delete on an expiry is a date on which they are deleted.
     *
     * Counted as calendar days from the uploader's own today, and ending
     * at the end of that day where they are, the same as picking the date
     * in the editor would.
     */
    private function defaultExpiry(User $uploader, Action $action): ?Carbon
    {
        $days = (int) $this->settings->get(Setting::DefaultFileExpiryDays);

        if ($days <= 0 || $action !== Action::FileUploaded || ! $uploader->isStaff()) {
            return null;
        }

        $day = Carbon::now($this->timezones->resolve($uploader))->addDays($days)->toDateString();

        return $this->expiry->instant($day, $uploader);
    }

    /**
     * An uploader picks original_name freely — it is validated for length
     * and nothing else — and it is echoed back in the Content-Disposition
     * header of every download, preview and thumbnail response. A CR or LF
     * in a header value is header injection; PHP's header() refuses to
     * emit one, so today that fails closed as a 500 rather than a split
     * response, but a filename should not be able to break the response at
     * all. Strip the whole C0/C1 control range once, here, rather than
     * hoping each of the five emission sites remembers to.
     */
    public static function sanitizeFilename(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        // And the bidirectional overrides and isolates, which reorder what
        // is shown without changing what is stored: "invoice\u{202E}fdp.exe"
        // reads as "invoiceexe.pdf" to whoever is deciding whether to open
        // it.
        $clean = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200E}\x{200F}]/u', '', $clean) ?? $clean;
        $clean = trim($clean);

        return $clean === '' ? 'file' : $clean;
    }
}
