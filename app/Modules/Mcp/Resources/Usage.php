<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Resources;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ConnectorPlan;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\GetUsage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('usage')]
#[Title('Plan and space')]
#[Uri('projectsend://usage')]
#[MimeType('application/json')]
class Usage extends Resource
{
    public function description(): string
    {
        return ConnectorPlan::current()->upgradePlan() === null
            ? "This account's space used and, for staff, client seats. Same numbers the person sees on the web."
            : "This account's plan, space used and, on a free account, what a portal of their own adds and the upgrade_url to use if it comes up. Same numbers the person sees on the web.";
    }

    public function handle(Request $request): Response
    {
        $account = McpContext::account() ?? Account::current();

        if ($account === null) {
            return Response::json(['error' => 'not signed in']);
        }

        return Response::json(GetUsage::data($account));
    }
}
