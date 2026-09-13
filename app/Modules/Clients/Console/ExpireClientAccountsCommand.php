<?php

declare(strict_types=1);

namespace App\Modules\Clients\Console;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\UserType;
use Illuminate\Console\Command;

/**
 * Switches off client accounts whose expiry date has passed.
 *
 * Not what stops an expired client getting in — User::maySignIn() does
 * that the moment the date passes, with or without this. What this does is
 * make `active` tell the truth, so everything that reads the flag rather
 * than asking the account agrees: the client list and its status filter,
 * the API's `active` field, and a managed plan's seat count. Hourly for the
 * same reason purge-stale-uploads is: a seat held by an account that can
 * no longer use it is a seat somebody else cannot have.
 */
class ExpireClientAccountsCommand extends Command
{
    protected $signature = 'projectsend:expire-client-accounts';

    protected $description = 'Deactivate client accounts whose expiry date has passed (runs hourly)';

    public function handle(ActivityLogger $activity): int
    {
        $now = now();
        $expired = 0;

        $due = User::query()
            ->where('type', UserType::Client)
            ->where('active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get(['id', 'name', 'expires_at']);

        foreach ($due as $client) {
            // Conditional on the row still being due, not a plain save():
            // an administrator who moved the date forward between the read
            // above and this write has just decided the account should keep
            // working, and must not be overruled by a list that is a few
            // milliseconds old.
            $switchedOff = User::query()
                ->whereKey($client->id)
                ->where('active', true)
                ->where('expires_at', '<=', $now)
                ->update(['active' => false]);

            if ($switchedOff === 1) {
                $activity->logSystem(Action::ClientExpired, ['name' => $client->name, 'id' => $client->id]);
                $expired++;
            }
        }

        $this->info("Deactivated {$expired} expired client account(s).");

        return self::SUCCESS;
    }
}
