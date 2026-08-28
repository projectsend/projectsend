<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail\Console;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Mail\MailOAuthBrokers;
use App\Modules\Platform\Mail\MailOAuthConnection;
use App\Modules\Platform\Mail\MailOAuthException;
use App\Modules\Platform\Settings\MailConfigApplier;
use Illuminate\Console\Command;

/**
 * Keeps every connected OAuth mailbox able to send, and says so early
 * when one no longer can.
 *
 * Transports already refresh on demand at send time; what they cannot do
 * is refresh on an installation that sends rarely — and a delegated
 * refresh token dies of pure disuse (Microsoft's sliding inactivity
 * window). A daily refresh keeps the window sliding, and doubles as the
 * health check: the delegated flow's one real weakness is that a grant
 * can die silently (password reset, Conditional Access change), which
 * for a portal whose password-reset mails ride on this connection must
 * surface as a warning, not as a support ticket weeks later.
 */
class RefreshMailOAuthTokensCommand extends Command
{
    protected $signature = 'projectsend:refresh-mail-oauth-tokens';

    protected $description = 'Refresh connected OAuth mailbox tokens and flag connections that need to be reconnected (runs daily)';

    public function handle(MailOAuthBrokers $brokers, Notifier $notifier, PermissionChecker $permissions, MailConfigApplier $mailConfig): int
    {
        $connections = MailOAuthConnection::query()->get()->filter(
            fn (MailOAuthConnection $connection): bool => $connection->usable(),
        );

        if ($connections->isEmpty()) {
            $this->info('No connected OAuth mailboxes; nothing to refresh.');

            return self::SUCCESS;
        }

        foreach ($connections as $connection) {
            $hadError = $connection->last_error !== null;

            try {
                // Serialised against sends: refresh() on its own is the
                // other half of the race freshAccessToken()'s lock is
                // there to stop.
                $brokers->for($connection->provider)->refreshSerially($connection);

                $this->info("Refreshed {$connection->provider->value} ({$connection->account_email}).");

                // Back from the dead (an admin fixed things upstream
                // without reconnecting): the applier may have been
                // resolving "not ready" and must see the recovery.
                if ($hadError) {
                    $mailConfig->flush();
                }
            } catch (MailOAuthException $e) {
                $this->error("Could not refresh {$connection->provider->value}: {$e->getMessage()}");

                if (! $e->needsReconnect) {
                    continue;
                }

                // Only on the transition into the broken state — the
                // notification would otherwise repeat daily for as long
                // as nobody reconnects, and a nagging alert trains
                // people to ignore the one that matters.
                if (! $hadError) {
                    $recipients = array_values(User::query()->where('type', UserType::Staff)->get()
                        ->filter(fn (User $staff): bool => $permissions->allows($staff, Permission::EditSettings))
                        ->all());

                    $notifier->send('mail_oauth_connection_broken', $recipients, data: [
                        'provider' => $connection->provider->label(),
                        'account' => (string) $connection->account_email,
                    ]);
                }

                $mailConfig->flush();
            }
        }

        return self::SUCCESS;
    }
}
