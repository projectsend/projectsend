<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Modules\Mcp\Events\ResolvingConnectorPlan;

/**
 * The connector's one question about plans, asked once per request.
 *
 * See ResolvingConnectorPlan: on a self-hosted installation the answer
 * is "no plan", and every method here returns its empty value.
 */
final class ConnectorPlan
{
    private ?ResolvingConnectorPlan $resolved = null;

    public static function current(): self
    {
        return app(self::class);
    }

    public function clientsMayConnect(): bool
    {
        return $this->resolve()->clientsMayConnect;
    }

    /**
     * @return array{code: string, name: string, price: string|null}|null
     */
    public function plan(): ?array
    {
        return $this->resolve()->plan;
    }

    /**
     * @return array{code: string, name: string, price: string}|null
     */
    public function upgradePlan(): ?array
    {
        return $this->resolve()->upgradePlan;
    }

    /**
     * @return list<string>
     */
    public function upgradeAdds(): array
    {
        return $this->resolve()->upgradeAdds;
    }

    public function upgradeNote(): ?string
    {
        return $this->resolve()->upgradeNote;
    }

    public function upgradeUrl(string $tool, string $connector): ?string
    {
        $url = $this->resolve()->upgradeUrl;

        return $url === null ? null : self::httpOnly($url($tool, $connector));
    }

    public function plansUrl(string $tool, string $connector): ?string
    {
        $url = $this->resolve()->plansUrl;

        return $url === null ? null : self::httpOnly($url($tool, $connector));
    }

    public function portalUrl(): ?string
    {
        return self::httpOnly($this->resolve()->portalUrl);
    }

    private function resolve(): ResolvingConnectorPlan
    {
        if ($this->resolved === null) {
            $this->resolved = new ResolvingConnectorPlan;
            event($this->resolved);
        }

        return $this->resolved;
    }

    /**
     * Every link here ends up in front of a person, through an
     * assistant. Anything but http(s) is dropped rather than handed on.
     */
    private static function httpOnly(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
