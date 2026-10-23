<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

/**
 * What a client account meets when it asks for something only staff
 * have (spec §7).
 *
 * Where client accounts are a free plan, the answer is the same object
 * every time — client accounts or folders, whichever was asked for —
 * with the alternative first, the paid plan as ConnectorPlan names it,
 * and a sign-up link that says which tool asked and which assistant was
 * asking. Where there is no plan to offer, it is a plain refusal.
 */
final class PlanGate
{
    /**
     * @param  array<string, mixed>|null  $nextCall  the same request on share_by_link, when a file_id was given
     * @param  'clients'|'folders'  $feature  what the free plan lacks, for the sentence
     */
    public static function for(string $tool, Account $account, ?array $nextCall = null, string $feature = 'clients'): ToolError
    {
        $plan = $account->upgradePlan();

        if ($plan === null) {
            return ToolError::staffOnly(Web::files($account));
        }

        $connector = $account->connector->slug;

        return ToolError::featureRequiresPlan(
            $tool,
            $plan,
            ConnectorPlan::current()->upgradeUrl($tool, $connector),
            ConnectorPlan::current()->plansUrl($tool, $connector),
            $nextCall,
            $feature,
        );
    }

    /** The sign-up link for a storage error on a free account, or null anywhere else. */
    public static function upgradeUrl(string $tool, Account $account): ?string
    {
        return $account->isFree() ? ConnectorPlan::current()->upgradeUrl($tool, $account->connector->slug) : null;
    }
}
