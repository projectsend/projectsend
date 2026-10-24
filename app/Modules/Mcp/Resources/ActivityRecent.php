<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Resources;

use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Support\Presenter;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('activity-recent')]
#[Title('Recent activity')]
#[Uri('projectsend://activity/recent')]
#[MimeType('application/json')]
#[Description('The last 50 things that happened on this site: uploads, shares, downloads, sign-ins, with who did them and how (web, API or AI assistant). No IP addresses.')]
class ActivityRecent extends Resource
{
    /** Staff with the log permission only; not listed otherwise. */
    public function shouldRegister(): bool
    {
        $account = McpContext::account() ?? Account::current();

        return $account !== null && $account->isStaff() && $account->can('view_actions_log');
    }

    public function handle(Request $request): Response
    {
        $account = McpContext::account() ?? Account::current();

        if ($account === null || $account->isClient() || ! $account->can('view_actions_log')) {
            return Response::json(['entries' => []]);
        }

        // The log page's own scope: a client-scoped staff member reads
        // what is about their library and their clients, and their own.
        $rows = app(ActivityLogScope::class)->apply(ActivityLog::query(), $account->user)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get();
        $entries = [];

        foreach ($rows as $row) {
            /** @var Model $row */
            $entries[] = [
                'id' => (int) $row->getAttribute('id'),
                'action' => self::value($row->getAttribute('action')),
                'at' => Presenter::iso($row->getAttribute('created_at')),
                'actor' => $row->getAttribute('actor_id') === null ? null : [
                    'type' => $row->getAttribute('actor_type'),
                    'name' => $row->getAttribute('actor_name'),
                ],
                // "AI assistant", "API", "Web": the label the log shows,
                // never the IP address beside it.
                'origin' => self::value($row->getAttribute('origin')),
                'credential' => $row->getAttribute('api_token_name'),
                'subject' => $row->getAttribute('subject_id') === null ? null : [
                    'type' => class_basename((string) $row->getAttribute('subject_type')),
                    'id' => (int) $row->getAttribute('subject_id'),
                    'name' => $row->getAttribute('subject_name'),
                ],
                'context' => is_array($row->getAttribute('context')) ? $row->getAttribute('context') : null,
            ];
        }

        return Response::json(['entries' => $entries]);
    }

    private static function value(mixed $enumOrString): ?string
    {
        if ($enumOrString === null) {
            return null;
        }

        return is_object($enumOrString) && property_exists($enumOrString, 'value') ? (string) $enumOrString->value : (string) $enumOrString;
    }
}
