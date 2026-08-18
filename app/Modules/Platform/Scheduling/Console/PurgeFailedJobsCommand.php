<?php

declare(strict_types=1);

namespace App\Modules\Platform\Scheduling\Console;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;

/**
 * Delete permanently failed queue jobs past the retention window.
 *
 * The Scheduler screen lists them and offers a "Delete all failed"
 * button, which is the right tool for a backlog somebody is looking at —
 * but it is the *only* thing that ever shrank this table. A mail server
 * down overnight can leave thousands of rows, each carrying a serialized
 * payload and an exception trace, on an installation whose administrator
 * has no reason to open that screen for months.
 *
 * Deliberately its own `projectsend:` command rather than a scheduled
 * `queue:prune-failed`, for two reasons: RecordsScheduledTaskRuns only
 * tracks our own signatures, so anything else runs invisibly; and the
 * retention window belongs in the settings with the rest of them rather
 * than in a number written into routes/console.php.
 */
class PurgeFailedJobsCommand extends Command
{
    protected $signature = 'projectsend:purge-failed-jobs';

    protected $description = 'Delete failed queue jobs older than the configured retention window (runs daily)';

    public function handle(Settings $settings, FailedJobProviderInterface $failer): int
    {
        $days = (int) $settings->get(Setting::FailedJobRetentionDays);

        // 0 means keep indefinitely — an explicit choice for somebody who
        // treats a failed job as evidence rather than as debris.
        if ($days <= 0) {
            $this->info('Failed job retention is disabled; nothing pruned.');

            return self::SUCCESS;
        }

        // Both providers this application can be configured with implement
        // it; the guard is here because the interface it is declared
        // against does not require it, and a queue driver that cannot
        // prune should say so rather than fail obscurely.
        if (! $failer instanceof PrunableFailedJobProvider) {
            $this->warn('This queue failure store cannot be pruned; nothing done.');

            return self::SUCCESS;
        }

        $deleted = $failer->prune(now()->subDays($days));

        $this->info("Pruned {$deleted} failed jobs older than {$days} days.");

        return self::SUCCESS;
    }
}
