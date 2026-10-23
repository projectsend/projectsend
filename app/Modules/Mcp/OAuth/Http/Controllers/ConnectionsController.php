<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Controllers;

use App\Modules\Mcp\Http\Middleware\EnsureConnectorCapability;
use App\Modules\Mcp\OAuth\Grants;
use App\Modules\Mcp\OAuth\Models\McpConnection;
use App\Modules\Mcp\OAuth\Scopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → AI connections (design §8.1): what is connected to this
 * account, and the one button that cuts it. Only the person's own rows,
 * whoever they are; a client sees the same list on the shared instance,
 * because without it "revoke" is writing to support.
 */
final class ConnectionsController
{
    public function index(Request $request): Response
    {
        abort_unless(EnsureConnectorCapability::granted(), 404);

        $user = $request->user();
        assert($user !== null);

        $connections = McpConnection::query()
            ->with('client')
            ->where('user_id', $user->getAuthIdentifier())
            ->live()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (McpConnection $connection): array => [
                'id' => $connection->getKey(),
                'client_name' => $connection->client->name,
                'identity' => $connection->client->identity(),
                'verified' => $connection->client->isVerified(),
                'access' => Scopes::describe($connection->scopes),
                'connected_at' => $connection->created_at->toIso8601String(),
                'last_used_at' => $connection->last_used_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('settings/ai-connections', [
            'connections' => $connections,
            'docs_url' => (string) config('mcp-connector.docs_url'),
        ]);
    }

    public function destroy(Request $request, Grants $grants, int $connection): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $row = McpConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->findOrFail($connection);

        $grants->revoke($row, 'disconnected from settings');

        return redirect()->route('mcp.connections.index');
    }
}
