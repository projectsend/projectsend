<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Platform\Updates\UpdateInstallation;
use Illuminate\Console\OutputStyle;

/**
 * The real update with its artisan calls written down instead of run.
 *
 * Two reasons, and the second one is why every test that runs the command
 * uses this and not the genuine article.
 *
 * The ordering constraints inside UpdateInstallation are invisible in its
 * result and expensive when wrong — a queue:restart before a cache clear
 * leaves a worker on old code indefinitely, and config:cache breaks
 * TRUSTED_PROXIES silently. An ordered list of the commands it ran is the
 * only thing that can assert them, so the artisan call is a seam.
 *
 * And those commands are not local. `clear-compiled` deletes
 * bootstrap/cache/packages.php and bootstrap/cache/services.php, `view:clear`
 * empties storage/framework/views, `storage:link` rewrites public/storage —
 * one copy of each, shared by all eight workers of a parallel run. A worker
 * that boots its application in the window between the delete and the
 * rebuild reads an empty package manifest, registers no package service
 * providers at all, and dies on the next page it renders with
 * "Target [Inertia\Ssr\Gateway] is not instantiable". See the test in
 * UpdateCommandTest that pins this.
 *
 * Everything above the artisan call stays real: EnsureSystemRoles, the
 * settings writes, the activity log and the welcome marker all run, which
 * is what the tests in both files actually assert.
 */
class RecordingUpdate extends UpdateInstallation
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, int> */
    public array $exitCodes = [];

    /** @var array{route: bool, event: bool, config: bool} */
    public array $warm = ['route' => false, 'event' => false, 'config' => false];

    /** The test database is always migrated, so this cannot be observed for real. */
    public bool $existingInstall = true;

    protected function artisan(string $command, array $parameters = [], ?OutputStyle $output = null): int
    {
        $this->calls[] = $command;

        return $this->exitCodes[$command] ?? 0;
    }

    protected function warmCaches(): array
    {
        return $this->warm;
    }

    protected function hasRunMigrationsBefore(): bool
    {
        return $this->existingInstall;
    }
}
