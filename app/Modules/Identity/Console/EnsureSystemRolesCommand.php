<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Permissions\EnsureSystemRoles;
use Illuminate\Console\Command;

class EnsureSystemRolesCommand extends Command
{
    protected $signature = 'projectsend:ensure-roles';

    protected $description = 'Ensure the built-in system roles exist (idempotent; run on every boot)';

    public function handle(EnsureSystemRoles $roles): int
    {
        $roles->ensure();

        $this->info('System roles are in place.');

        return self::SUCCESS;
    }
}
