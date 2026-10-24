<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\McpContext;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('clients')]
#[Title('Client accounts')]
#[Uri('projectsend://clients')]
#[MimeType('application/json')]
#[Description('The client accounts of this site, compact: id, name, email, active, last sign-in. Use list_clients to search or page.')]
class Clients extends Resource
{
    /** Staff only: a free account has no clients, so the resource is not listed there. */
    public function shouldRegister(): bool
    {
        $account = McpContext::account() ?? Account::current();

        return $account !== null && $account->isStaff() && $account->can('manage_clients');
    }

    public function handle(Request $request): Response
    {
        $account = McpContext::account() ?? Account::current();

        if ($account === null || $account->isClient()) {
            return Response::json(['clients' => [], 'total' => 0, 'seats' => null]);
        }

        $query = ClientAccountsQuery::clients($account)->orderBy('name')->orderBy('id');
        $total = (int) (clone $query)->count();

        /** @var list<Model> $rows */
        $rows = array_values($query->limit(200)->get()->all());
        $signIns = ClientAccountsQuery::lastSignIns(array_map(fn (Model $c): int => (int) $c->getAttribute('id'), $rows));

        $clients = [];

        foreach ($rows as $client) {
            $id = (int) $client->getAttribute('id');
            $clients[] = [
                'id' => $id,
                'name' => (string) $client->getAttribute('name'),
                'email' => (string) $client->getAttribute('email'),
                'active' => (bool) $client->getAttribute('active'),
                'last_sign_in_at' => $signIns[$id] ?? null,
            ];
        }

        $seats = $account->seats();

        return Response::json([
            'clients' => $clients,
            'total' => $total,
            'seats' => $seats === null ? null : ['clients_used' => $seats['clients_used'], 'clients_limit' => $seats['clients_limit']],
        ]);
    }
}
