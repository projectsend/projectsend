<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ConnectorPlan;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_usage')]
#[Title('Plan and space')]
#[IsReadOnly]
class GetUsage extends ToolBase
{
    public const DESCRIPTION = 'Returns how much space and how many client seats this account has used, with the same numbers the person sees on the web. Use it before a large upload, or when the person asks how much room they have, so you quote their real figures. Do not use it to check whether a file exists (use list_files) or to poll: the numbers change only when files are added or removed.';

    /** Said only where there is a plan to upgrade to. */
    public const DESCRIPTION_WITH_PLAN = 'Returns what plan this account is on and how much space and how many client seats it has used, with the same numbers the person sees on the web. Use it before a large upload, when the person asks how much room they have, or before mentioning the paid plan, so you quote their real figures. Do not use it to check whether a file exists (use list_files) or to poll: the numbers change only when files are added or removed. The response includes what a portal of their own would add and the upgrade_url to use if that ever comes up.';

    public function description(): string
    {
        return ConnectorPlan::current()->upgradePlan() === null ? self::DESCRIPTION : self::DESCRIPTION_WITH_PLAN;
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        return Envelope::success(
            self::data($account),
            Web::files($account),
            'Quote used and quota as given. If percent is 80 or more, say so once and offer to free space before anything else.',
        );
    }

    /**
     * What this tool and the usage resource both answer.
     *
     * @return array<string, mixed>
     */
    public static function data(Account $account): array
    {
        $data = [
            'account' => [
                'type' => $account->isFree() ? 'free' : ($account->isClient() ? 'client' : 'staff'),
                'name' => $account->name(),
                'email' => $account->email(),
                'hostname' => Web::hostname(),
            ],
            'plan' => $account->plan(),
            'storage' => $account->storage(),
            'files_count' => self::filesCount($account),
        ];

        if ($account->isClient()) {
            $upgrade = self::upgrade($account);

            if ($upgrade !== null) {
                $data['upgrade'] = $upgrade;
            }
        } else {
            $data['seats'] = $account->seats();
        }

        return $data;
    }

    /**
     * The block a free account carries, and the one resource that may
     * carry a sign-up link in a successful answer (spec §7). Null where
     * there is no plan to upgrade to.
     *
     * @return array<string, mixed>|null
     */
    public static function upgrade(Account $account): ?array
    {
        $plan = $account->upgradePlan();

        if ($plan === null) {
            return null;
        }

        return [
            'plan' => $plan,
            'adds' => ConnectorPlan::current()->upgradeAdds(),
            'note' => ConnectorPlan::current()->upgradeNote(),
            'url' => ConnectorPlan::current()->upgradeUrl('get_usage', $account->connector->slug),
        ];
    }

    public static function filesCount(Account $account): int
    {
        return (int) Files::visibleTo($account)->count();
    }
}
