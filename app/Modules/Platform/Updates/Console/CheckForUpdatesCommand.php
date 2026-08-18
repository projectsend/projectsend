<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates\Console;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Updates\CheckForUpdates;
use Illuminate\Console\Command;

/**
 * Community edition only, once a day. The work itself lives in
 * CheckForUpdates, which the settings screen's "check now" button calls
 * too; this is the scheduled half, and the only thing it adds is the
 * setting that switches the schedule off.
 *
 * That setting governs *this* command and not the service on purpose: an
 * administrator who does not want a daily outbound call should still be
 * able to ask the question themselves.
 *
 * There is still no in-app self-updater — nothing here downloads or
 * applies anything. Applying an update is `update.sh` on a server
 * install, or a new image on a container one.
 */
class CheckForUpdatesCommand extends Command
{
    protected $signature = 'projectsend:check-for-updates';

    protected $description = 'Check for a newer ProjectSend release and notify admins (Community edition, runs daily)';

    public function __construct(
        private readonly Settings $settings,
        private readonly CheckForUpdates $check,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->settings->get(Setting::CheckForUpdates) !== true) {
            $this->info('Update checks are disabled.');

            return self::SUCCESS;
        }

        $result = $this->check->run();

        if (! $result['ok']) {
            $this->warn($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
