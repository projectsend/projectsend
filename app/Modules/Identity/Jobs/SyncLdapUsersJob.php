<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jobs;

use App\Modules\Identity\Ldap\LdapSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * "Sync now" on the LDAP settings screen.
 *
 * A first sync of a large directory creates an account per person, which
 * takes longer than the default worker's 60s timeout allows one job. So
 * the run is cut into chunks of about $budgetSeconds: each reads the
 * directory, carries on from the entry the last one stopped at, and queues
 * the next until the whole listing is done. Same shape as
 * ImportOrphanFilesJob, and the same reason it can share the default queue.
 *
 * @phpstan-import-type Report from LdapSync
 */
class SyncLdapUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /**
     * @param  Report|null  $carried
     */
    public function __construct(
        private readonly int $offset = 0,
        private readonly ?array $carried = null,
        private readonly int $budgetSeconds = 45,
    ) {}

    public function handle(LdapSync $sync): void
    {
        $report = $sync->run(offset: $this->offset, carried: $this->carried, deadline: microtime(true) + $this->budgetSeconds);

        if ($report['status'] === 'running') {
            self::dispatch($report['next'], $report, $this->budgetSeconds);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(LdapSync::class)->fail($exception->getMessage());
    }
}
