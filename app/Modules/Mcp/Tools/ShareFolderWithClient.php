<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Folders;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('share_folder_with_client')]
#[Title('Share a folder with a client')]
#[Description('Shares a whole folder with a client account: the client sees every file in it and in its subfolders, including files added later, and is notified by email, exactly as when the person shares a folder on the web. Use it when the person wants a client to have a folder ("give Acme the 2026 folder") rather than one file at a time. Find the folder with list_folders and the client with list_clients, or create the client first. To share a single file, use send_file_to_client. Not available on a free account: there, share_by_link gives a private link for each file instead. Sharing the same folder with the same client twice changes nothing.')]
#[IsIdempotent]
class ShareFolderWithClient extends ToolBase
{
    protected function gatedForClients(): bool
    {
        return true;
    }

    protected function gateFeature(): string
    {
        return 'folders';
    }

    /** No file is shared by asking for a folder: the gate's alternative says what to do instead. */
    protected function gateNextCall(Request $request): ?array
    {
        return null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->integer()->min(1)->required()->description("The folder's id, from list_folders or a previous response."),
            'client_id' => $schema->integer()->min(1)->description("The client's id. Give this or client_email."),
            'client_email' => $schema->string()->max(255)->description('Find the client by email. If no client has it, the error tells you to create one first.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'integer', 'min:1'],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'client_email' => ['nullable', 'string', 'max:255'],
        ]);

        if (! isset($validated['client_id']) && ! isset($validated['client_email'])) {
            return ToolError::validationFailed(['client_id' => ['Give client_id or client_email.']]);
        }

        $folder = Folders::find($account, (int) $validated['folder_id']);

        if ($folder === null) {
            return Folders::notFound();
        }

        // FolderAssignmentsController authorizes `update` on the folder.
        Folders::requireUpdate($folder, $account);

        $client = isset($validated['client_id'])
            ? ClientAccountsQuery::byId($account, (int) $validated['client_id'])
            : ClientAccountsQuery::byEmail($account, (string) $validated['client_email']);

        if ($client === null) {
            return isset($validated['client_email'])
                ? ToolError::notFound(
                    'client',
                    ['tool' => 'create_client', 'arguments' => ['email' => strtolower(trim((string) $validated['client_email']))]],
                    'No client has this address. Ask the person for the name, then create_client with the email and the name, and share again with the new client_id.',
                )
                : ToolError::notFound('client');
        }

        // A client-scoped staff member shares only with their own clients,
        // as ResolvesShareTargets::guardAssignable holds them on the web.
        if (! app(StaffLibraryScope::class)->canAssignClient($account->user, $client)) {
            return ToolError::validationFailed(['client_id' => ['This account can only share with the clients assigned to it.']]);
        }

        $shared = Folders::shareWith($folder, $client);

        $folderId = (int) $folder->getAttribute('id');
        $folderName = (string) $folder->getAttribute('name');
        $clientId = (int) $client->getAttribute('id');
        $clientName = (string) $client->getAttribute('name');

        return Envelope::success(
            [
                'folder' => Presenter::folder($folder, Folders::paths([$folder])),
                'client' => Presenter::client($client),
                'already_shared' => ! $shared,
                'notified' => $shared,
                'files_count' => Folders::filesInSubtree($folder),
            ],
            Web::staffFolder($folderId),
            $shared
                ? "Tell the person {$clientName} now sees everything in \"{$folderName}\", subfolders and files added later included, and got an email. get_download_status with client_id {$clientId} shows what they download."
                : "{$clientName} already had \"{$folderName}\"; nothing changed and nobody was emailed again.",
        );
    }
}
