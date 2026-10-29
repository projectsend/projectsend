<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Scanning\FileAvailability;
use App\Modules\Files\Sharing\FileSharing;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
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

#[Name('send_file_to_client')]
#[Title('Send a file to a client')]
#[Description('Shares a file with a client account: it appears in their ProjectSend site and they are notified by email, exactly as when the person does it on the web. Use it when the recipient has (or should have) their own client account, so you can later see that they, specifically, downloaded it. Pass an existing file_id, or content you wrote plus a filename to save and send in one step. Not available on a free account: there, share_by_link gives a private link to send instead. Sending the same file to the same client twice changes nothing.')]
#[IsIdempotent]
class SendFileToClient extends ToolBase
{
    protected function gatedForClients(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->min(1)->description("The client's id. Give this or client_email."),
            'client_email' => $schema->string()->max(255)->description('Find the client by email. If no client has it, the error tells you to create one first.'),
            'file_id' => $schema->integer()->min(1)->description('An existing file. Give this, or content plus filename.'),
            'content' => $schema->string()->description('Or save content you wrote as a new file and send it in the same call, under the same rules as create_file_from_content (up to 1 MB).'),
            'filename' => $schema->string()->max(255)->description('The name for the file when content is given, with its extension.'),
            'encoding' => $schema->string()->enum(['utf-8', 'base64'])->default('utf-8')->description('How content is encoded.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'min:1'],
            'client_email' => ['nullable', 'string', 'max:255'],
            'file_id' => ['nullable', 'integer', 'min:1'],
            'content' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
            'encoding' => ['nullable', 'in:utf-8,base64'],
        ]);

        if (! isset($validated['client_id']) && ! isset($validated['client_email'])) {
            return ToolError::validationFailed(['client_id' => ['Give client_id or client_email.']]);
        }

        if (! isset($validated['file_id']) && (! isset($validated['content']) || ! isset($validated['filename']))) {
            return ToolError::validationFailed(['file_id' => ['Give file_id, or content together with filename.']]);
        }

        $client = isset($validated['client_id'])
            ? ClientAccountsQuery::byId($account, (int) $validated['client_id'])
            : ClientAccountsQuery::byEmail($account, (string) $validated['client_email']);

        if ($client === null) {
            return isset($validated['client_email'])
                ? ToolError::notFound(
                    'client',
                    ['tool' => 'create_client', 'arguments' => ['email' => strtolower(trim((string) $validated['client_email']))]],
                    'No client has this address. Ask the person for the name, then create_client with the email and the name, and send again with the new client_id.',
                )
                : ToolError::notFound('client');
        }

        if (isset($validated['file_id'])) {
            $file = Files::find($account, (int) $validated['file_id']);

            if ($file === null) {
                return ToolError::notFound('file');
            }

            Files::requireSharingRoot($file, $this->name(), ['client_id' => (int) $client->getAttribute('id')]);
        } else {
            // A new file, so the key every other way of making one asks.
            $this->requirePermission($account, 'upload');

            $file = CreateFileFromContent::save(
                $account,
                (string) $validated['filename'],
                (string) $validated['content'],
                (string) ($validated['encoding'] ?? 'utf-8'),
                null,
                null,
                $this->name(),
            );
        }

        Files::requireUpdate($file, $account);

        $clientId = (int) $client->getAttribute('id');
        $clientName = (string) $client->getAttribute('name');
        $fileId = (int) $file->getAttribute('id');

        $already = FileAssignment::query()
            ->where('file_id', $fileId)
            ->where('assignable_type', ClientAccountsQuery::userMorph())
            ->where('assignable_id', $clientId)
            ->exists();

        if (! $already) {
            // The app's own sharing: the assignment, the activity entry
            // and the notification (held back while a file is still being
            // scanned, exactly as on the web).
            app(FileSharing::class)->assign($file, $client, $clientName);
        }

        $notified = ! $already && app(FileAvailability::class)->isAvailable($file);

        return Envelope::success(
            [
                'file' => Presenter::file($file, $account),
                'client' => Presenter::client($client),
                'already_shared' => $already,
                'notified' => $notified,
            ],
            Web::file($account, $fileId),
            "Tell the person it was sent and that {$clientName} will get an email. get_download_status with client_id {$clientId} shows when they download it.",
            $notified || $already ? [] : ['The file is still being checked; the client will be notified as soon as it is available.'],
        );
    }
}
