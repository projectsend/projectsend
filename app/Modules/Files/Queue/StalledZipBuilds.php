<?php

declare(strict_types=1);

namespace App\Modules\Files\Queue;

use App\Modules\Files\Models\ZipDownload;
use Illuminate\Support\Carbon;

/**
 * Whether anything is consuming the `zips` queue.
 *
 * The application cannot see its own worker processes; it can only see
 * whether work gets done. So the question is asked from the other end —
 * a build that was requested a while ago and that no worker ever picked
 * up means nobody is listening to that queue.
 *
 * Which is a real configuration, not a hypothetical one. Zip building
 * moved onto its own queue, and a manual install whose worker command
 * still reads a plain `queue:work` consumes `default` and nothing else.
 * It goes on sending every email perfectly while no zip download ever
 * finishes, and nothing in any log says why — the worst shape a
 * misconfiguration can take, and the reason this is worth a banner
 * rather than a line in a release note.
 *
 * Two conditions, because one of them alone cries wolf:
 *
 *  - a build has been waiting past GRACE and was never started; and
 *  - no other build is in hand right now.
 *
 * The second matters because one worker builds one archive at a time. A
 * queue behind a large build is a healthy queue, and its waiting rows
 * look exactly like abandoned ones until you notice something running.
 * "In hand" is itself bounded by the job's own timeout: a build that
 * started three hours ago is not in progress, it is a worker that died
 * holding it.
 */
class StalledZipBuilds
{
    /**
     * Long enough that an ordinary wait never trips it, short enough to
     * be found on the day the install is upgraded rather than the week.
     */
    private const GRACE_MINUTES = 5;

    /**
     * Matches BuildZipDownloadJob::$timeout. Past it, a build that
     * started is not running any more — the worker died holding it, and
     * the queue is as unattended as if it had never begun.
     */
    private const IN_HAND_MINUTES = 60;

    /**
     * The oldest build nothing ever picked up, or null when the queue is
     * being served.
     */
    public function oldestUnstarted(): ?Carbon
    {
        if ($this->buildInHand()) {
            return null;
        }

        $waiting = ZipDownload::query()
            ->where('status', ZipDownload::STATUS_PENDING)
            ->whereNull('started_at')
            ->where('created_at', '<', now()->subMinutes(self::GRACE_MINUTES))
            ->min('created_at');

        return $waiting === null ? null : Carbon::parse($waiting);
    }

    private function buildInHand(): bool
    {
        return ZipDownload::query()
            ->where('status', ZipDownload::STATUS_PENDING)
            ->whereNotNull('started_at')
            ->where('started_at', '>', now()->subMinutes(self::IN_HAND_MINUTES))
            ->exists();
    }
}
