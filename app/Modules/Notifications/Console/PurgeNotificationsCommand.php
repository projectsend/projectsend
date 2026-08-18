<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;

/**
 * Delete read notifications past the retention window.
 *
 * One row per recipient per event, and until now nothing ever removed
 * one. On an installation where every share notifies a handful of people
 * this is the fastest-growing table in the database, and the growth buys
 * nothing: nobody scrolls a year back through a notification list.
 *
 * **Unread notifications are never deleted, at any age.** A notification
 * nobody has looked at is the one thing in this table still doing its
 * job, and an installation whose owner was away for four months should
 * come back to their news rather than to a clean slate. The history is
 * not an audit trail either way — the activity log is, and it is never
 * pruned.
 */
class PurgeNotificationsCommand extends Command
{
    protected $signature = 'projectsend:purge-notifications';

    protected $description = 'Delete read notifications older than the configured retention window (runs daily)';

    public function handle(Settings $settings): int
    {
        $days = (int) $settings->get(Setting::NotificationRetentionDays);

        if ($days <= 0) {
            $this->info('Notification retention is disabled; nothing pruned.');

            return self::SUCCESS;
        }

        // Chunked for the same reason the API request log is: this is a
        // high-volume table, and a single unbounded DELETE on a busy
        // installation holds locks for as long as it takes.
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $batch = InAppNotification::query()
                ->whereNotNull('read_at')
                ->where('created_at', '<', $cutoff)
                ->limit(5000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} read notifications older than {$days} days.");

        return self::SUCCESS;
    }
}
