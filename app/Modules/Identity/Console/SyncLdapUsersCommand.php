<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Ldap\LdapSettings;
use App\Modules\Identity\Ldap\LdapSync;
use Illuminate\Console\Command;

/**
 * The directory sync from the command line, and its daily run.
 *
 * The scheduler calls it with --scheduled every day, and it does nothing
 * unless an administrator switched daily sync on, so the schedule entry
 * can stay fixed while the choice lives in the settings screen.
 */
class SyncLdapUsersCommand extends Command
{
    protected $signature = 'projectsend:ldap-sync
        {--dry-run : Report what would change without changing anything}
        {--scheduled : Run only if daily sync is switched on}';

    protected $description = 'Create, update and optionally deactivate client accounts from the LDAP directory';

    public function handle(LdapSync $sync): int
    {
        if ($this->option('scheduled') && ! LdapSettings::current()->sync_daily) {
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $sync->run(dryRun: $dryRun);

        if ($report['status'] === 'failed') {
            $this->error((string) $report['error']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d found, %d %s, %d %s, %d %s, %d unchanged, %d skipped, %d no longer in the directory (%d %s), %d errors.',
            $report['found'],
            $report['created'], $dryRun ? 'to create' : 'created',
            $report['updated'], $dryRun ? 'to update' : 'updated',
            $report['restored'], $dryRun ? 'to restore' : 'restored',
            $report['unchanged'],
            array_sum($report['skipped']),
            $report['missing'],
            $report['deactivated'], $dryRun ? 'to deactivate' : 'deactivated',
            $report['errors'],
        ));

        return self::SUCCESS;
    }
}
