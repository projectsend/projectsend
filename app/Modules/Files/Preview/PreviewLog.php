<?php

declare(strict_types=1);

namespace App\Modules\Files\Preview;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Cache;

/**
 * One log row per viewer per file per five minutes, for both preview
 * routes — FileThumbnailController::preview (signed in) and
 * PublicGroupsController::preview (anonymous).
 *
 * Watching a video is a single deliberate act that the browser turns into
 * dozens of Range requests, each arriving indistinguishable from someone
 * clicking preview again. Cache::add is the whole mechanism: it writes
 * only if the key is absent, so the first request through the window logs
 * and the rest are silent, without a read-then-write race between two of
 * them.
 *
 * Keyed by viewer, so one person's playback never suppresses another's
 * view of the same file. An anonymous visitor has no account to key on,
 * so the request IP stands in — the same substitute the API's rate
 * limiter makes for an unauthenticated caller. It is a cache key with a
 * five-minute life and never reaches the log, which keeps its own
 * decision about recording an IP (see ActivityLogger::shouldRecordIp and
 * Setting::DownloadIpLogging).
 *
 * Shared rather than restated, because the window is the rule: two copies
 * of "five minutes" are two things to change and one to forget.
 */
class PreviewLog
{
    private const WINDOW_MINUTES = 5;

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function record(Action $action, File $file, ?User $viewer): void
    {
        $viewerKey = $viewer !== null ? (string) $viewer->id : 'ip:'.request()->ip();

        if (Cache::add('file-preview-logged:'.$file->id.':'.$viewerKey, true, now()->addMinutes(self::WINDOW_MINUTES))) {
            $this->activity->log($action, subject: $file);
        }
    }
}
