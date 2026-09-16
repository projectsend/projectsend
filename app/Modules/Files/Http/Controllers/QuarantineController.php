<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\FileAvailability;
use App\Modules\Files\Scanning\NotScannedReason;
use App\Modules\Files\Scanning\ScanStatus;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The files the virus scanner refused, and the one decision a person can
 * make about them.
 *
 * Nothing is deleted here automatically and nothing expires out of this
 * list: a quarantined file waits for somebody. Deleting one is the
 * ordinary file deletion, with its ordinary permission — this screen only
 * adds the other answer, which is that the scanner was wrong.
 *
 * Releasing is gated by a permission of its own that only the
 * administrator role holds by default, and by password confirmation on
 * top of it, because it is the one action in the application that
 * deliberately hands out a file something reported as malicious.
 */
class QuarantineController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly FileAvailability $availability,
    ) {}

    public function index(Request $request): Response
    {
        $files = File::query()
            ->whereIn('scan_status', [ScanStatus::Infected->value, ScanStatus::UnscannableBlocked->value])
            ->with('uploader')
            ->orderByDesc('scanned_at')
            ->paginate(25)
            ->withQueryString();

        $files->through(fn (File $file): array => [
            'id' => $file->id,
            'name' => $file->name,
            'original_name' => $file->original_name,
            'size' => $file->size,
            'uploader' => $file->uploader?->name,
            // The threat name, or — for a file nothing could open — what
            // stopped it being read.
            'threat' => $file->scan_status === ScanStatus::UnscannableBlocked
                ? __(NotScannedReason::tryFrom((string) $file->scan_note)?->label() ?? 'Could not be scanned')
                : $file->scan_note,
            'status' => $file->scan_status->value,
            'scanned_at' => $file->scanned_at?->toIso8601String(),
            // True only for a file that went out unscanned while the
            // scanner was unreachable and was caught later — which is the
            // one case where somebody may already have a copy.
            'was_available' => $file->scan_was_available,
            'downloads_count' => $file->downloads()->count(),
        ]);

        return Inertia::render('files/quarantine', [
            'files' => $files->items(),
            'pagination' => Pagination::meta($files),
        ]);
    }

    /**
     * Overrule the scanner for one file.
     *
     * The reason is required and is recorded against the person who gave
     * it. A release is not undone by a later scan: the file stays
     * released until somebody deletes it, which is the point — an
     * administrator who has decided a detection is wrong should not have
     * to decide it again every hour.
     */
    public function release(Request $request, File $file): RedirectResponse
    {
        abort_unless($file->scan_status->isQuarantined(), 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $actor = $request->user();
        assert($actor !== null);

        $file->forceFill([
            'scan_status' => ScanStatus::Released,
            'released_by' => $actor->id,
            'released_at' => now(),
        ])->save();

        $this->activity->log(Action::FileReleased, subject: $file, context: [
            'reason' => $validated['reason'],
            'threat' => $file->scan_note,
        ]);

        // Everything that was waiting on this file — a share email, a new
        // version notice — goes out now, exactly as it would have if the
        // scan had passed.
        $this->availability->markAvailable($file);

        return back()->with('success', __('The file has been released.'));
    }
}
