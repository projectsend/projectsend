<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Events;

use Closure;

/**
 * What the AI connector should say about plans on this installation.
 *
 * Dispatched once per request, the first time the connector needs to
 * know. Nobody listens on a self-hosted installation, so every field
 * keeps its default: there is no plan, nothing to upgrade to, and only
 * staff may connect. A hosted edition fills it in, and the connector
 * then offers what the plan lacks instead of only refusing it.
 *
 * Nothing here grants a permission. A client that may connect still
 * reaches only its own files, and every tool still asks the person's
 * live permissions.
 */
final class ResolvingConnectorPlan
{
    /**
     * Whether a client account may connect an assistant to its own
     * files. Where it may, client accounts are a plan of their own, and
     * the connector treats them as that plan's customers.
     */
    public bool $clientsMayConnect = false;

    /**
     * The plan this site is on, as the person would name it.
     *
     * @var array{code: string, name: string, price: string|null}|null
     */
    public ?array $plan = null;

    /**
     * The plan a client account would move to for what it lacks. When
     * null, a client asking for a staff feature is simply refused.
     *
     * @var array{code: string, name: string, price: string}|null
     */
    public ?array $upgradePlan = null;

    /**
     * What the upgrade adds, one short line each, for get_usage.
     *
     * @var list<string>
     */
    public array $upgradeAdds = [];

    public ?string $upgradeNote = null;

    /**
     * The sign-up link handed to an assistant, given the tool that asked
     * and the connector that was asking.
     *
     * @var (Closure(string, string): ?string)|null
     */
    public ?Closure $upgradeUrl = null;

    /**
     * The comparison of plans, given the same two.
     *
     * @var (Closure(string, string): ?string)|null
     */
    public ?Closure $plansUrl = null;

    /** Where this site's plan and seats are managed, when that is not the site itself. */
    public ?string $portalUrl = null;
}
