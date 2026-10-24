<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Prompts;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\McpContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('onboard-clients')]
#[Title('Set up client accounts')]
#[Description('Create client accounts from a list of names and addresses, without duplicates, and optionally share a first file with each of them.')]
class OnboardClients extends Prompt
{
    public const TEXT = <<<'TEXT'
Set up client accounts on this ProjectSend site from the list given.

1. Parse the list into name and email pairs. Show the pairs back and ask the person to confirm before creating anything.
2. For each pair, call create_client with idempotency_key set to the email. If client_email_taken comes back, keep the existing id and move on; do not create a duplicate.
3. If first_file_id was given, call send_file_to_client for each client.
4. Reply with one table: name, email, sign-in URL, and the generated password where one was returned. Say plainly that the passwords are shown once and should be stored now.
5. If seats_full comes back, stop, say how many were created and how many seats are left, and point to the portal for a larger plan. Do not retry.
TEXT;

    /** Staff only: a free account has no clients to set up. */
    public function shouldRegister(): bool
    {
        $account = McpContext::account() ?? Account::current();

        return $account === null || $account->isStaff();
    }

    public function arguments(): array
    {
        return [
            new Argument(name: 'clients', description: 'The clients to add, one per line: Name <email>', required: true),
            new Argument(name: 'first_file_id', description: 'A file to share with each of them right away.', required: false),
        ];
    }

    public function handle(Request $request): Response
    {
        $clients = trim((string) $request->get('clients', ''));
        $fileId = trim((string) $request->get('first_file_id', ''));

        $lead = $clients === '' ? '' : "The list:\n{$clients}\n";
        $lead .= $fileId === '' ? '' : "\nfirst_file_id: {$fileId}\n";

        return Response::text(trim($lead."\n".self::TEXT));
    }
}
