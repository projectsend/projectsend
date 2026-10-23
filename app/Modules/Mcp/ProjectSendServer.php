<?php

declare(strict_types=1);

namespace App\Modules\Mcp;

use App\Modules\Mcp\Prompts\OnboardClients;
use App\Modules\Mcp\Prompts\SendDeliverable;
use App\Modules\Mcp\Resources\ActivityRecent;
use App\Modules\Mcp\Resources\Clients;
use App\Modules\Mcp\Resources\Usage;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\CompleteUpload;
use App\Modules\Mcp\Tools\CreateClient;
use App\Modules\Mcp\Tools\CreateFileFromContent;
use App\Modules\Mcp\Tools\CreateFolder;
use App\Modules\Mcp\Tools\CreateUpload;
use App\Modules\Mcp\Tools\DeleteFile;
use App\Modules\Mcp\Tools\GetClient;
use App\Modules\Mcp\Tools\GetDownloadStatus;
use App\Modules\Mcp\Tools\GetFile;
use App\Modules\Mcp\Tools\GetUsage;
use App\Modules\Mcp\Tools\GetWebUploadLink;
use App\Modules\Mcp\Tools\ListClients;
use App\Modules\Mcp\Tools\ListFiles;
use App\Modules\Mcp\Tools\ListFolders;
use App\Modules\Mcp\Tools\MoveFile;
use App\Modules\Mcp\Tools\RevokeLink;
use App\Modules\Mcp\Tools\SendFileToClient;
use App\Modules\Mcp\Tools\ShareByLink;
use App\Modules\Mcp\Tools\ShareFolderWithClient;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The ProjectSend MCP server: nineteen tools, three resources, two
 * prompts, and one paragraph of instructions that changes only in its
 * last line, which names the account.
 *
 * Instantiated per request by `laravel/mcp`, so `boot()` runs with the
 * guard's user already resolved. That is where the account is read once
 * and remembered for the tools and for the activity log's origin hook.
 */
class ProjectSendServer extends Server
{
    protected string $name = 'ProjectSend';

    /** Set in boot() to the app's own version. */
    protected string $version = '';

    protected string $instructions = Instructions::BASE;

    /**
     * Every tool in the first page of `tools/list`. `laravel/mcp` pages at
     * fifteen, which this server outgrew with the folder tools, and a
     * client that does not follow nextCursor would never see the rest.
     */
    public int $defaultPaginationLength = 50;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        GetUsage::class,
        ListFiles::class,
        GetFile::class,
        CreateUpload::class,
        CompleteUpload::class,
        CreateFileFromContent::class,
        GetWebUploadLink::class,
        ShareByLink::class,
        RevokeLink::class,
        GetDownloadStatus::class,
        DeleteFile::class,
        ListClients::class,
        GetClient::class,
        CreateClient::class,
        SendFileToClient::class,
        // The staff library's folders: on a free account, the gate (decision 17).
        ListFolders::class,
        CreateFolder::class,
        MoveFile::class,
        ShareFolderWithClient::class,
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [
        Usage::class,
        Clients::class,
        ActivityRecent::class,
    ];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [
        SendDeliverable::class,
        OnboardClients::class,
    ];

    protected function boot(): void
    {
        $this->version = (string) config('projectsend.version');

        $account = Account::current();

        McpContext::set($account);

        $this->instructions = Instructions::text($account);

        // The activity log records a request carrying a Sanctum token as
        // "API" with the token's name. Here the token is the assistant's
        // credential and the right answer is "AI assistant" with the
        // connector's name, which the app offers through a hook it
        // only asks when no token is on the user. So the token is taken
        // off the user for the rest of this request: the scope and the
        // name were read from it above and nothing downstream needs it.
        if ($account !== null) {
            // @phpstan-ignore argument.type (Sanctum's own docblock is narrower than what it accepts)
            $account->user->withAccessToken(null);
        }
    }
}
