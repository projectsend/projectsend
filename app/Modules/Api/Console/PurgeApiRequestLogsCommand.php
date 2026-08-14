<?php

declare(strict_types=1);

namespace App\Modules\Api\Console;

use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;

class PurgeApiRequestLogsCommand extends Command
{
    protected $signature = 'projectsend:purge-api-request-logs';

    protected $description = 'Delete API request telemetry older than the configured retention window (runs daily)';

    public function handle(Settings $settings): int
    {
        $days = (int) $settings->get(Setting::ApiRequestLogRetentionDays);

        // 0 means keep indefinitely — an explicit choice an operator can
        // make, and the reason this reads the setting rather than assuming.
        if ($days <= 0) {
            $this->info('API request log retention is disabled; nothing pruned.');

            return self::SUCCESS;
        }

        // Deleted in chunks: this is the highest-volume table in the
        // application, and a single unbounded DELETE on a busy install
        // would hold locks for as long as it took.
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $batch = ApiRequestLog::query()->where('created_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} API request log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
