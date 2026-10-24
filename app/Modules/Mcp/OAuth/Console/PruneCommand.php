<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Console;

use App\Modules\Mcp\OAuth\Models\McpAuthorizationCode;
use App\Modules\Mcp\OAuth\Models\McpClient;
use App\Modules\Mcp\OAuth\Models\McpConnection;
use App\Modules\Mcp\OAuth\Tokens;
use Illuminate\Console\Command;

/**
 * What the OAuth tables no longer need (design §5.4): codes past their
 * minute, connections revoked or run out thirty days ago, registered
 * clients that never connected in ninety. Expired access tokens are the
 * app's to prune, with `sanctum:prune-expired`.
 */
final class PruneCommand extends Command
{
    protected $signature = 'mcp:prune';

    protected $description = 'Delete expired authorization codes, dead connections and orphaned client registrations of the AI connector';

    public function handle(): int
    {
        $codes = McpAuthorizationCode::query()->where('expires_at', '<', now()->subDay())->delete();

        // A connection that ran out still holds the id of an access token
        // that may outlive it by an hour: gone with the row.
        $expired = McpConnection::query()->whereNull('revoked_at')->where('refresh_expires_at', '<', now())->get();

        foreach ($expired as $connection) {
            Tokens::delete($connection->access_token_id);
            $connection->forceFill(['revoked_at' => now(), 'revoked_reason' => 'refresh token expired', 'access_token_id' => null])->save();
        }

        $connections = McpConnection::query()->whereNotNull('revoked_at')->where('revoked_at', '<', now()->subDays(30))->delete();

        $clients = McpClient::query()
            ->where('kind', McpClient::KIND_DYNAMIC)
            ->where('created_at', '<', now()->subDays(90))
            ->whereDoesntHave('connections', fn ($query) => $query->whereNull('revoked_at'))
            ->delete();

        $this->info("Pruned {$codes} codes, {$connections} connections and {$clients} client registrations; ".count($expired).' connections expired.');

        return self::SUCCESS;
    }
}
