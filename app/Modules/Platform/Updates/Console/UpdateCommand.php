<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates\Console;

use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Installation\InstallationKind;
use App\Modules\Platform\Updates\UpdateInstallation;
use Illuminate\Console\Command;

/**
 * Bring this installation in line with the code that is on disk.
 *
 * No options, deliberately: the container entrypoints, update.sh and an
 * administrator typing it all invoke the identical string, which is what
 * makes "one definition of an update" true rather than aspirational.
 * UpdateInstallation holds the sequence and the reasoning.
 */
class UpdateCommand extends Command
{
    protected $signature = 'projectsend:update';

    protected $description = 'Bring the database, roles and caches in line with the installed code (idempotent; run on every boot)';

    public function handle(UpdateInstallation $update, Installation $installation): int
    {
        $result = $update->run($this->output);

        if (! $result['ok']) {
            foreach ($result['warnings'] as $warning) {
                $this->error($warning);
            }

            return self::FAILURE;
        }

        $this->info('System roles are in place.');

        if ($result['cleared'] !== []) {
            $this->info('Cleared the compiled configuration, events, routes and views.');
        }

        if ($result['rewarmed'] !== []) {
            $this->info('Rebuilt the route, event and view caches — they were in place before.');
        }

        $this->info('Asked the background workers to restart.');

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->newLine();
        $this->info($this->summary($result['from'], $result['to']));

        // A container is replaced rather than reloaded, and its operator
        // has no systemd to reload anything with — printing this there
        // would send them looking for a service that does not exist.
        if ($installation->kind() === InstallationKind::Manual) {
            $this->newLine();
            $this->warn('The web server is still running the code it compiled before this ran.');
            $this->warn('Reload PHP-FPM now, or it will keep serving it:  sudo systemctl reload php8.4-fpm');
        }

        return self::SUCCESS;
    }

    private function summary(string $from, string $to): string
    {
        if ($from === '') {
            return "Applied {$to}.";
        }

        if ($from === $to) {
            return "Re-applied {$to}.";
        }

        return "Updated from {$from} to {$to}.";
    }
}
