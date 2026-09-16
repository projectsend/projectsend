<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

use App\Modules\Files\Events\FileBecameAvailable;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Event;

/**
 * Whether a file may be seen and served, and what happens the moment it
 * may be.
 *
 * The one predicate every other rule asks. Three states mean yes and
 * three mean no (see ScanStatus), and the reason this is a class rather
 * than a comparison at each call site is that the list of "yes" states
 * has already changed once — `released` was added when quarantine gained
 * an override — and the day it changes again, it has to change in one
 * place or a file becomes downloadable through one route and not another.
 *
 * "Available" is about everyone *other than* staff and the uploader. Staff
 * see their library at all times, with each file's state on it; what
 * availability governs is whether recipients and visitors see a file at
 * all, and whether its bytes may leave the server.
 */
class FileAvailability
{
    public function isAvailable(File $file): bool
    {
        return $file->scan_status->isAvailable();
    }

    /**
     * Refuse to serve a file's bytes unless it is available.
     *
     * Called by every route that puts bytes on the wire — the download,
     * the thumbnail, the preview, the share link, the public listing and
     * the zip builder. Not by the listings: a staff member's library shows
     * a pending file with its state on it, and the uploader sees their own.
     * What this governs is the bytes.
     *
     * It refuses everybody, including staff and the file's own uploader.
     * A file the scanner has not cleared is not one this application
     * hands out, and an administrator who wants it anyway has a way to say
     * so on the record: release it from quarantine.
     *
     * 423 rather than 403: the refusal is about the file's state and it is
     * temporary in the pending case, which is exactly what "Locked" means
     * and what "Forbidden" does not. ProblemDetails renders it as JSON for
     * the API, which shares these controllers.
     */
    public function guardDelivery(File $file): void
    {
        if ($this->isAvailable($file)) {
            return;
        }

        abort(423, $file->scan_status === ScanStatus::Pending
            ? __('This file is still being checked for viruses.')
            : __('This file is not available.'));
    }

    /**
     * A file has finished being checked, one way or another.
     *
     * Three roads lead here and they are not interchangeable: the scan
     * passed, the scanner could not be reached and this installation lets
     * files through, or an administrator released it from quarantine. What
     * they share is the only thing this announces — the file can now be
     * had by the people it was shared with, which is when everything that
     * was waiting on it (a share email, a new-version notice) is allowed
     * to go out.
     */
    public function markAvailable(File $file): void
    {
        if (! $this->isAvailable($file)) {
            return;
        }

        Event::dispatch(new FileBecameAvailable($file));
    }
}
