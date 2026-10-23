<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\PlanGate;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_download_status')]
#[Title('Who downloaded this')]
#[Description('Tells you whether and when a file was downloaded, through which link or by which client account, from the account\'s activity log. Use it when the person asks "did they get it?", after sharing or sending, or to follow up. Pass `since` to see only what is new. Do not call it on a timer more often than every few minutes; nothing here changes faster than a person opening an email. Downloads through a link show no identity: a link is not tied to a person. No IP addresses are returned.')]
#[IsReadOnly]
class GetDownloadStatus extends ToolBase
{
    /** The app's download actions, by value; the log is the only place downloads are counted. */
    private const VIA = [
        'file.downloaded' => 'client',
        'share_link.downloaded' => 'link',
        'public_file.downloaded' => 'public',
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'file_id' => $schema->integer()->min(1)->description('Downloads of this file. Give one of file_id, link_id or client_id.'),
            'link_id' => $schema->integer()->min(1)->description('Downloads through this link only.'),
            'client_id' => $schema->integer()->min(1)->description('Everything this client downloaded. Staff accounts only.'),
            'since' => $schema->string()->description('Only downloads at or after this moment (ISO 8601). Pass the last `at` you saw instead of polling.'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('How many entries to return, 1 to 50.'),
            'cursor' => $schema->string()->description("From the previous response's next_cursor."),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'file_id' => ['nullable', 'integer', 'min:1'],
            'link_id' => ['nullable', 'integer', 'min:1'],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:64'],
        ]);

        if (! isset($validated['file_id']) && ! isset($validated['link_id']) && ! isset($validated['client_id'])) {
            return ToolError::validationFailed(['file_id' => ['Give one of file_id, link_id or client_id.']]);
        }

        if (isset($validated['client_id']) && $account->isClient()) {
            return PlanGate::for($this->name(), $account)->with([
                'alternative_tool' => 'get_download_status',
                'next_call' => null,
            ]);
        }

        $this->requirePermission($account, 'view_actions_log');

        $query = app(ActivityLogScope::class)->apply(ActivityLog::query(), $account->user)
            ->whereIn('action', array_keys(self::VIA));
        $fileMorph = (new File)->getMorphClass();
        $query->where('subject_type', $fileMorph);

        $linkId = null;
        $webUrl = Web::files($account);

        if (isset($validated['file_id'])) {
            $file = Files::find($account, (int) $validated['file_id']);

            if ($file === null) {
                return ToolError::notFound('file');
            }

            $query->where('subject_id', (int) $file->getAttribute('id'));
            $webUrl = Web::file($account, (int) $file->getAttribute('id'));
        } elseif (isset($validated['link_id'])) {
            $link = ShareLink::query()->whereKey((int) $validated['link_id'])->first();
            $file = $link instanceof Model ? Files::find($account, (int) $link->getAttribute('shareable_id')) : null;

            if ($file === null) {
                return ToolError::notFound('link');
            }

            $linkId = (int) $validated['link_id'];
            $query->where('subject_id', (int) $file->getAttribute('id'))->where('action', 'share_link.downloaded');
            $webUrl = Web::file($account, (int) $file->getAttribute('id'));
        } else {
            $client = ClientAccountsQuery::byId($account, (int) $validated['client_id']);

            if ($client === null) {
                return ToolError::notFound('client');
            }

            $query->where('actor_id', (int) $client->getAttribute('id'))->where('action', 'file.downloaded');
            $webUrl = Web::client((int) $client->getAttribute('id'));
        }

        if ($account->isClient()) {
            // Their own files only, whichever way the question was asked.
            $query->whereIn('subject_id', Files::visibleTo($account)->select('id'));
        }

        if (isset($validated['since'])) {
            $query->where('created_at', '>=', Carbon::parse($validated['since']));
        }

        $summary = self::summary(clone $query);

        if (isset($validated['cursor']) && ctype_digit((string) $validated['cursor'])) {
            $query->where('id', '<', (int) $validated['cursor']);
        }

        $limit = (int) ($validated['limit'] ?? 20);
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $downloads = [];

        foreach ($rows as $row) {
            $action = $row->getAttribute('action');
            $actionValue = $action instanceof BackedEnum ? (string) $action->value : (string) $action;
            $via = self::VIA[$actionValue] ?? 'link';

            $downloads[] = [
                'at' => Presenter::iso($row->getAttribute('created_at')),
                'via' => $via,
                'link_id' => $via === 'link' ? $linkId : null,
                'who' => $via === 'client' && $row->getAttribute('actor_id') !== null
                    ? ['type' => 'client', 'id' => (int) $row->getAttribute('actor_id'), 'name' => $row->getAttribute('actor_name')]
                    : null,
                'file' => ['id' => (int) $row->getAttribute('subject_id'), 'name' => $row->getAttribute('subject_name')],
            ];
        }

        $last = $rows->last();

        return Envelope::success(
            [
                'summary' => $summary,
                'downloads' => $downloads,
                'next_cursor' => $hasMore && $last !== null ? (string) $last->getAttribute('id') : null,
            ],
            $webUrl,
            'Report the latest download plainly. To follow up later, call again with since set to the last `at`.',
        );
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return array{total: int, last_at: string|null, distinct_clients: int}
     */
    private static function summary(Builder $query): array
    {
        $total = (int) (clone $query)->count();
        $last = (clone $query)->orderByDesc('created_at')->first();
        $clients = (int) (clone $query)->where('action', 'file.downloaded')->whereNotNull('actor_id')->distinct()->count('actor_id');

        return [
            'total' => $total,
            'last_at' => $last !== null ? Presenter::iso($last->getAttribute('created_at')) : null,
            'distinct_clients' => $clients,
        ];
    }
}
