<?php

declare(strict_types=1);

namespace App\Modules\Platform\Installation\Console;

use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Seats\SeatAllowance;
use Illuminate\Console\Command;

/**
 * What this installation is, as a fact rather than a screen.
 *
 * Written for whatever watches a managed installation from outside the
 * container. Everything here is already visible to any signed-in
 * administrator — a version, an edition, which capabilities the edition
 * grants, how many accounts exist against how many are allowed. Nothing
 * is a secret and nothing is a credential.
 *
 * ### Why a command and not a shell one-liner
 *
 * A reconciler that observes tenants has to be able to say it never sends
 * instructions, only reads state. `docker exec … php -r '…'` is an
 * instruction with the caller's argv in it, however harmless the argv;
 * a named command is an observation, the same kind of thing as reading a
 * directory size. The distinction is the whole reason this exists rather
 * than a documented incantation.
 *
 * ### The counts are the enforcing code's own
 *
 * `used` comes from SeatAllowance, which is what refuses the account past
 * the limit. Two counts that merely agree will diverge eventually — over
 * an inactive account, or a soft-deleted one — and the divergence looks
 * like a billing fault rather than a counting one. So there is one
 * definition and this reads it.
 */
class StatusCommand extends Command
{
    protected $signature = 'projectsend:status {--json : Emit machine-readable JSON on stdout}';

    protected $description = 'Report this installation\'s version, edition, capabilities and seat usage';

    public function handle(CapabilityRegistry $capabilities, SeatAllowance $seats): int
    {
        $status = [
            'version' => (string) config('projectsend.version'),
            'edition' => $capabilities->edition()->value,
            'capabilities' => $capabilities->enabledKeys(),
            'seats' => [
                'staff' => [
                    'used' => $seats->staffUsed(),
                    // null is unlimited, and is emitted as null rather than
                    // as 0 or as an absent key: a reader that mistook one
                    // for the other would report an installation selling
                    // unlimited accounts as one that may hold none.
                    'limit' => $seats->staffLimit(),
                ],
                'clients' => [
                    'used' => $seats->clientUsed(),
                    'limit' => $seats->clientLimit(),
                ],
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line("ProjectSend {$status['version']} ({$status['edition']})");
        $this->line('Capabilities: '.(implode(', ', $status['capabilities']) ?: 'none'));
        $this->line('Staff seats:  '.$this->seatLine($status['seats']['staff']));
        $this->line('Clients:      '.$this->seatLine($status['seats']['clients']));

        return self::SUCCESS;
    }

    /**
     * @param  array{used: int, limit: int|null}  $seat
     */
    private function seatLine(array $seat): string
    {
        return $seat['limit'] === null
            ? "{$seat['used']} of unlimited"
            : "{$seat['used']} of {$seat['limit']}";
    }
}
