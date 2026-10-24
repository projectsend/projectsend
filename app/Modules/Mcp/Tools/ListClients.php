<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Models\User;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_clients')]
#[Title('List clients')]
#[Description('Lists the client accounts of this ProjectSend site: people who sign in to see what was shared with them. Use it to find a client\'s id before sending a file, to check whether someone already has an account before creating one, or when the person asks who their clients are. Not available on a free account, which has no client accounts: there, share_by_link is how a file reaches someone. Each client\'s id is what send_file_to_client and get_client take.')]
#[IsReadOnly]
class ListClients extends ToolBase
{
    protected function gatedForClients(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(120)->description('Matches name and email.'),
            'active_only' => $schema->boolean()->default(true)->description('Leave out deactivated accounts.'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('How many clients to return, 1 to 50.'),
            'cursor' => $schema->string()->description("From the previous response's next_cursor."),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'active_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        $this->requirePermission($account, 'manage_clients');

        $query = ClientAccountsQuery::clients($account)->orderBy('name')->orderBy('id');

        if (($validated['active_only'] ?? true) !== false) {
            $query->where('active', true);
        }

        if (isset($validated['query']) && $validated['query'] !== '') {
            $term = '%'.$validated['query'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $total = (clone $query)->count();
        $page = $query->cursorPaginate((int) ($validated['limit'] ?? 20), ['*'], 'cursor', $validated['cursor'] ?? null);

        /** @var list<User> $rows */
        $rows = array_values($page->items());
        $ids = array_map(fn (User $c): int => (int) $c->getAttribute('id'), $rows);
        $signIns = ClientAccountsQuery::lastSignIns($ids);
        $shared = ClientAccountsQuery::sharedCounts($ids);

        $clients = [];

        foreach ($rows as $client) {
            $id = (int) $client->getAttribute('id');
            $clients[] = Presenter::client($client) + [
                'created_at' => Presenter::iso($client->getAttribute('created_at')),
                'last_sign_in_at' => $signIns[$id] ?? null,
                'files_shared_count' => $shared[$id] ?? 0,
            ];
        }

        $seats = $account->seats();

        return Envelope::success(
            [
                'clients' => $clients,
                'next_cursor' => $page->nextCursor()?->encode(),
                'total' => $total,
                'seats' => $seats === null ? null : ['clients_used' => $seats['clients_used'], 'clients_limit' => $seats['clients_limit']],
            ],
            Web::clients(),
            "Use a client's id with send_file_to_client or get_client. If someone is missing, create_client adds them.",
        );
    }
}
