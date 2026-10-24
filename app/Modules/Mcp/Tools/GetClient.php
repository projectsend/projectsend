<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Bytes;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_client')]
#[Title('Client details')]
#[Description('Returns one client account in full: contact details, whether they are active, their storage, the files shared with them and their recent activity (sign-ins and downloads). Use it before sending something, to answer "what does Acme have?", or to check by email whether a client exists. Not available on a free account. Do not use it to list clients (list_clients).')]
#[IsReadOnly]
class GetClient extends ToolBase
{
    protected function gatedForClients(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->min(1)->description("The client's id. Give this or email."),
            'email' => $schema->string()->max(255)->description('Find the client by their email address instead.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'string', 'max:255'],
        ]);

        if (! isset($validated['client_id']) && ! isset($validated['email'])) {
            return ToolError::validationFailed(['client_id' => ['Give client_id or email.']]);
        }

        $this->requirePermission($account, 'edit_clients');

        $client = isset($validated['client_id'])
            ? ClientAccountsQuery::byId($account, (int) $validated['client_id'])
            : ClientAccountsQuery::byEmail($account, (string) $validated['email']);

        if ($client === null) {
            return isset($validated['email'])
                ? ToolError::notFound(
                    'client',
                    ['tool' => 'create_client', 'arguments' => ['email' => strtolower(trim((string) $validated['email']))]],
                    'No client has this address. create_client adds one: ask the person for the name, then call it with the email and the name.',
                )
                : ToolError::notFound('client');
        }

        $id = (int) $client->getAttribute('id');

        return Envelope::success(
            [
                'client' => Presenter::client($client) + [
                    'created_at' => Presenter::iso($client->getAttribute('created_at')),
                    'expires_at' => Presenter::iso($client->getAttribute('expires_at')),
                ],
                'storage' => self::storage($client),
                'files_shared' => self::filesShared($id),
                'recent_activity' => self::recentActivity($id),
            ],
            Web::client($id),
            "send_file_to_client with client_id {$id} puts something in this account; get_download_status with client_id {$id} shows what they downloaded.",
        );
    }

    /**
     * @return array{used: string, quota: string}|null
     */
    private static function storage(User $client): ?array
    {
        try {
            $usage = app(ClientStorageUsage::class);
            $quota = $usage->quotaBytes($client);

            return [
                'used' => Bytes::human($usage->usedBytes($client)),
                'quota' => $quota > 0 ? Bytes::human($quota) : 'unlimited',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{count: int, latest: list<array{id: int, name: string, shared_at: string|null, downloaded: bool}>}
     */
    private static function filesShared(int $clientId): array
    {
        $assignments = FileAssignment::query()
            ->where('assignable_type', ClientAccountsQuery::userMorph())
            ->where('assignable_id', $clientId)
            ->orderByDesc('id');

        $count = (int) (clone $assignments)->count();
        $latest = [];

        foreach ((clone $assignments)->limit(10)->get() as $assignment) {
            /** @var Model $assignment */
            $file = File::query()->whereKey((int) $assignment->getAttribute('file_id'))->first();

            if (! $file instanceof Model) {
                continue;
            }

            $fileId = (int) $file->getAttribute('id');
            $downloaded = ActivityLog::query()
                ->where('action', 'file.downloaded')
                ->where('actor_id', $clientId)
                ->where('subject_id', $fileId)
                ->exists();

            $latest[] = [
                'id' => $fileId,
                'name' => (string) $file->getAttribute('name'),
                'shared_at' => Presenter::iso($assignment->getAttribute('created_at')),
                'downloaded' => $downloaded,
            ];
        }

        return ['count' => $count, 'latest' => $latest];
    }

    /**
     * @return list<array{at: string|null, action: string, file: array{id: int, name: string|null}|null}>
     */
    private static function recentActivity(int $clientId): array
    {
        $rows = ActivityLog::query()
            ->where('actor_id', $clientId)
            ->whereIn('action', ['auth.login', 'file.downloaded', 'file.previewed'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $out = [];

        foreach ($rows as $row) {
            /** @var Model $row */
            $action = $row->getAttribute('action');
            $subjectId = $row->getAttribute('subject_id');

            $out[] = [
                'at' => Presenter::iso($row->getAttribute('created_at')),
                'action' => is_object($action) && property_exists($action, 'value') ? (string) $action->value : (string) $action,
                'file' => $subjectId === null ? null : ['id' => (int) $subjectId, 'name' => $row->getAttribute('subject_name')],
            ];
        }

        return $out;
    }
}
